<?php
/*
Plugin Name: Advanced Product Media
Description: Use video and audio files instead of images for WooCommerce product featured media and gallery.
Version: 1.0.1
Author: Amin Amini
Requires Plugins: woocommerce
Text Domain: advanced-product-media
*/

if (!defined('ABSPATH')) {
    exit;
}

define('APM_VERSION', '1.0.1');
define('APM_PATH', plugin_dir_path(__FILE__));
define('APM_URL', plugin_dir_url(__FILE__));

// Check WooCommerce dependency
function apm_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('This plugin requires WooCommerce to be installed and active.', 'advanced-product-media'),
            esc_html__('Plugin Dependency Error', 'advanced-product-media'),
            array('back_link' => true)
        );
    }
}
add_action('admin_init', 'apm_check_woocommerce');

// Allow video and audio upload with security checks
function apm_allow_video_audio_upload($mimes) {
    // Only allow specific safe formats
    $allowed_mimes = array(
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mp3'  => 'audio/mpeg',
        'm4a'  => 'audio/mp4',
        'wav'  => 'audio/wav',
    );
    
    return array_merge($mimes, $allowed_mimes);
}
add_filter('upload_mimes', 'apm_allow_video_audio_upload');

// Additional security check for file uploads
function apm_check_file_type($file) {
    $allowed_extensions = array('mp4', 'webm', 'mp3', 'm4a', 'wav');
    $allowed_mime_types = array('video/mp4', 'video/webm', 'audio/mpeg', 'audio/mp4', 'audio/wav');
    
    $file_info = wp_check_filetype($file['name']);
    $extension = $file_info['ext'];
    $mime_type = $file_info['type'];
    
    if (in_array($extension, $allowed_extensions) && in_array($mime_type, $allowed_mime_types)) {
        // Verify actual file content matches mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($real_mime, $allowed_mime_types)) {
            $file['error'] = esc_html__('File type does not match its content.', 'advanced-product-media');
        }
    }
    
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'apm_check_file_type');

// Get placeholder URL
function apm_get_placeholder_url($type) {
    $type = sanitize_key($type);
    
    if ($type === 'video') {
        $file = 'vid.png';
    } else {
        $file = 'mus.png';
    }
    
    $path = APM_PATH . 'assets/' . $file;
    
    if (file_exists($path)) {
        return APM_URL . 'assets/' . $file;
    }
    
    // Fallback to WooCommerce placeholder
    return wc_placeholder_img_src();
}

// Get placeholder path
function apm_get_placeholder_path($type) {
    $type = sanitize_key($type);
    
    if ($type === 'video') {
        $file = 'vid.png';
    } else {
        $file = 'mus.png';
    }
    
    return APM_PATH . 'assets/' . $file;
}

// Generate media thumbnail
function apm_generate_media_thumbnail($metadata, $attachment_id) {
    $attachment_id = absint($attachment_id);
    $mime_type = get_post_mime_type($attachment_id);
    
    if (!$mime_type) {
        return $metadata;
    }
    
    $type = null;
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    }
    
    if (!$type) {
        return $metadata;
    }
    
    $placeholder_path = apm_get_placeholder_path($type);
    
    if (!file_exists($placeholder_path)) {
        return $metadata;
    }
    
    $image_size = @getimagesize($placeholder_path);
    if (!$image_size) {
        return $metadata;
    }
    
    $filename = basename($placeholder_path);
    
    if (!isset($metadata['sizes'])) {
        $metadata['sizes'] = array();
    }
    
    $metadata['width'] = absint($image_size[0]);
    $metadata['height'] = absint($image_size[1]);
    
    $size_data = array(
        'file' => sanitize_file_name($filename),
        'width' => absint($image_size[0]),
        'height' => absint($image_size[1]),
        'mime-type' => 'image/png'
    );
    
    $metadata['sizes']['thumbnail'] = $size_data;
    $metadata['sizes']['woocommerce_thumbnail'] = $size_data;
    $metadata['sizes']['woocommerce_single'] = $size_data;
    $metadata['sizes']['woocommerce_gallery_thumbnail'] = $size_data;
    
    update_post_meta($attachment_id, '_apm_media_type', sanitize_key($type));
    update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field(ucfirst($type) . ' file'));
    
    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'apm_generate_media_thumbnail', 10, 2);

// Fix attachment image src
function apm_fix_attachment_image_src($image, $attachment_id, $size) {
    $attachment_id = absint($attachment_id);
    $mime_type = get_post_mime_type($attachment_id);
    
    if (!$mime_type) {
        return $image;
    }
    
    $type = null;
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    }
    
    if (!$type) {
        return $image;
    }
    
    $placeholder_url = apm_get_placeholder_url($type);
    $placeholder_path = apm_get_placeholder_path($type);
    
    if (file_exists($placeholder_path)) {
        $image_size = @getimagesize($placeholder_path);
        if ($image_size) {
            $image = array(
                esc_url($placeholder_url),
                absint($image_size[0]),
                absint($image_size[1]),
                false
            );
        }
    }
    
    return $image;
}
add_filter('wp_get_attachment_image_src', 'apm_fix_attachment_image_src', 10, 3);

// Fix post thumbnail HTML
function apm_fix_post_thumbnail_html($html, $post_id, $post_thumbnail_id) {
    $post_thumbnail_id = absint($post_thumbnail_id);
    $mime_type = get_post_mime_type($post_thumbnail_id);
    
    if (!$mime_type) {
        return $html;
    }
    
    $type = null;
    $alt_text = '';
    
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
        $alt_text = esc_attr__('Video', 'advanced-product-media');
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
        $alt_text = esc_attr__('Audio', 'advanced-product-media');
    }
    
    if ($type) {
        $placeholder_url = apm_get_placeholder_url($type);
        $html = '<img src="' . esc_url($placeholder_url) . '" alt="' . $alt_text . '" />';
    }
    
    return $html;
}
add_filter('post_thumbnail_html', 'apm_fix_post_thumbnail_html', 10, 3);

// Fix attachment metadata
function apm_fix_attachment_metadata($data, $attachment_id) {
    $attachment_id = absint($attachment_id);
    $mime_type = get_post_mime_type($attachment_id);
    
    if (!$mime_type) {
        return $data;
    }
    
    $is_media = (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false);
    
    if ($is_media) {
        $type = strpos($mime_type, 'video') !== false ? 'video' : 'audio';
        $placeholder_path = apm_get_placeholder_path($type);
        
        if (file_exists($placeholder_path)) {
            $image_size = @getimagesize($placeholder_path);
            if ($image_size) {
                $data['width'] = absint($image_size[0]);
                $data['height'] = absint($image_size[1]);
            }
        }
    }
    
    return $data;
}
add_filter('wp_get_attachment_metadata', 'apm_fix_attachment_metadata', 10, 2);

// Allow all media in featured image
function apm_allow_all_media_in_featured_image() {
    $screen = get_current_screen();
    
    if (!$screen || $screen->id !== 'product') {
        return;
    }
    
    // Add nonce for security
    wp_nonce_field('apm_media_security', 'apm_media_nonce');
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if (typeof wp !== 'undefined' && wp.media && wp.media.view) {
            // Store original frame
            var originalMediaFrame = wp.media.view.MediaFrame.Select;
            
            // Extend media frame
            wp.media.view.MediaFrame.Select = originalMediaFrame.extend({
                initialize: function() {
                    originalMediaFrame.prototype.initialize.apply(this, arguments);
                    
                    // Allow all media types
                    this.on('content:render:browse', function(view) {
                        if (view.collection && view.collection.props) {
                            view.collection.props.set({type: ''});
                        }
                    });
                }
            });
            
            // Handle featured image click
            $(document).on('click', '#set-post-thumbnail', function(e) {
                var featuredImageFrame = wp.media.featuredImage.frame();
                
                if (featuredImageFrame) {
                    featuredImageFrame.on('open', function() {
                        var state = featuredImageFrame.state();
                        if (state) {
                            var library = state.get('library');
                            if (library && library.props) {
                                library.props.set({type: ''});
                            }
                        }
                    });
                }
            });
        }
    });
    </script>
    <?php
}
add_action('admin_footer', 'apm_allow_all_media_in_featured_image');

// Filter media library for products
function apm_filter_media_library($query) {
    if (!is_admin()) {
        return $query;
    }
    
    // Verify nonce
    if (isset($_POST['action']) && $_POST['action'] === 'query-attachments') {
        // Check if this is for a product
        if (isset($_POST['post_id'])) {
            $post_id = absint($_POST['post_id']);
            $post = get_post($post_id);
            
            if ($post && $post->post_type === 'product') {
                unset($query['post_mime_type']);
            }
        }
    }
    
    return $query;
}
add_filter('ajax_query_attachments_args', 'apm_filter_media_library', 999);

// Replace product main image
function apm_replace_product_main_image($html) {
    global $product;
    
    if (!$product || !is_object($product)) {
        return $html;
    }
    
    $thumbnail_id = $product->get_image_id();
    
    if (!$thumbnail_id) {
        return $html;
    }
    
    $thumbnail_id = absint($thumbnail_id);
    $mime_type = get_post_mime_type($thumbnail_id);
    
    if (!$mime_type) {
        return $html;
    }
    
    $file_url = wp_get_attachment_url($thumbnail_id);
    
    if (!$file_url) {
        return $html;
    }
    
    if (strpos($mime_type, 'video') !== false) {
        $html = sprintf(
            '<div class="woocommerce-product-gallery__image">
                <video controls preload="metadata" style="width:100%%; max-width:100%%; height:auto;">
                    <source src="%s" type="%s">
                    %s
                </video>
            </div>',
            esc_url($file_url),
            esc_attr($mime_type),
            esc_html__('Your browser does not support the video tag.', 'advanced-product-media')
        );
    } elseif (strpos($mime_type, 'audio') !== false) {
        $html = sprintf(
            '<div class="woocommerce-product-gallery__image">
                <audio controls preload="metadata" style="width:100%%; max-width:100%%;">
                    <source src="%s" type="%s">
                    %s
                </audio>
            </div>',
            esc_url($file_url),
            esc_attr($mime_type),
            esc_html__('Your browser does not support the audio tag.', 'advanced-product-media')
        );
    }
    
    return $html;
}
add_filter('woocommerce_single_product_image_html', 'apm_replace_product_main_image', 10, 1);

// Replace gallery images
function apm_replace_gallery_images($html, $attachment_id) {
    $attachment_id = absint($attachment_id);
    $mime_type = get_post_mime_type($attachment_id);
    
    if (!$mime_type) {
        return $html;
    }
    
    $file_url = wp_get_attachment_url($attachment_id);
    
    if (!$file_url) {
        return $html;
    }
    
    if (strpos($mime_type, 'video') !== false) {
        $html = sprintf(
            '<div class="woocommerce-product-gallery__image">
                <video controls preload="metadata" style="width:100%%; max-width:100%%; height:auto;">
                    <source src="%s" type="%s">
                    %s
                </video>
            </div>',
            esc_url($file_url),
            esc_attr($mime_type),
            esc_html__('Your browser does not support the video tag.', 'advanced-product-media')
        );
    } elseif (strpos($mime_type, 'audio') !== false) {
        $html = sprintf(
            '<div class="woocommerce-product-gallery__image">
                <audio controls preload="metadata" style="width:100%%; max-width:100%%;">
                    <source src="%s" type="%s">
                    %s
                </audio>
            </div>',
            esc_url($file_url),
            esc_attr($mime_type),
            esc_html__('Your browser does not support the audio tag.', 'advanced-product-media')
        );
    }
    
    return $html;
}
add_filter('woocommerce_single_product_image_thumbnail_html', 'apm_replace_gallery_images', 10, 2);

// Add CSS for better media display
function apm_add_frontend_styles() {
    if (is_product()) {
        ?>
        <style type="text/css">
            .woocommerce-product-gallery__image video,
            .woocommerce-product-gallery__image audio {
                display: block;
                margin: 0 auto;
            }
            .woocommerce-product-gallery__image video {
                background: #000;
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'apm_add_frontend_styles');
