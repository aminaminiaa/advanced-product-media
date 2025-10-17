<?php
/*
Plugin Name: Advanced Product Media
Description: Use video and audio files instead of images for WooCommerce product featured media and gallery.
Version: 1.0.0
Author: Amin Amini
Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit;
}

define('CUSTOM_CODES_VERSION', '1.0.0');
define('CUSTOM_CODES_PATH', plugin_dir_path(__FILE__));
define('CUSTOM_CODES_URL', plugin_dir_url(__FILE__));

function custom_codes_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and active.');
    }
}
add_action('admin_init', 'custom_codes_check_woocommerce');

function custom_codes_allow_video_audio_upload($mimes) {
    $mimes['mp4'] = 'video/mp4';
    $mimes['webm'] = 'video/webm';
    $mimes['ogg'] = 'video/ogg';
    $mimes['mp3'] = 'audio/mpeg';
    $mimes['m4a'] = 'audio/mp4';
    $mimes['wav'] = 'audio/wav';
    return $mimes;
}
add_filter('upload_mimes', 'custom_codes_allow_video_audio_upload');

function custom_codes_get_placeholder_url($type) {
    if ($type === 'video') {
        return CUSTOM_CODES_URL . 'assets/vid.png';
    } else {
        return CUSTOM_CODES_URL . 'assets/mus.png';
    }
}

function custom_codes_get_placeholder_path($type) {
    if ($type === 'video') {
        return CUSTOM_CODES_PATH . 'assets/vid.png';
    } else {
        return CUSTOM_CODES_PATH . 'assets/mus.png';
    }
}

function custom_codes_generate_media_thumbnail($metadata, $attachment_id) {
    $mime_type = get_post_mime_type($attachment_id);
    
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    } else {
        return $metadata;
    }
    
    $placeholder_path = custom_codes_get_placeholder_path($type);
    
    if (file_exists($placeholder_path)) {
        $image_size = getimagesize($placeholder_path);
        $filename = basename($placeholder_path);
        
        if (!isset($metadata['sizes'])) {
            $metadata['sizes'] = array();
        }
        
        $metadata['width'] = $image_size[0];
        $metadata['height'] = $image_size[1];
        
        $metadata['sizes']['thumbnail'] = array(
            'file' => $filename,
            'width' => $image_size[0],
            'height' => $image_size[1],
            'mime-type' => 'image/png'
        );
        
        $metadata['sizes']['woocommerce_thumbnail'] = array(
            'file' => $filename,
            'width' => $image_size[0],
            'height' => $image_size[1],
            'mime-type' => 'image/png'
        );
        
        $metadata['sizes']['woocommerce_single'] = array(
            'file' => $filename,
            'width' => $image_size[0],
            'height' => $image_size[1],
            'mime-type' => 'image/png'
        );
        
        update_post_meta($attachment_id, '_custom_codes_media_type', $type);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', ucfirst($type) . ' file');
    }
    
    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'custom_codes_generate_media_thumbnail', 10, 2);

function custom_codes_fix_attachment_image_src($image, $attachment_id, $size) {
    $mime_type = get_post_mime_type($attachment_id);
    
    if (strpos($mime_type, 'video') !== false) {
        $placeholder_url = custom_codes_get_placeholder_url('video');
        $placeholder_path = custom_codes_get_placeholder_path('video');
        if (file_exists($placeholder_path)) {
            $image_size = getimagesize($placeholder_path);
            $image = array($placeholder_url, $image_size[0], $image_size[1], false);
        }
    } elseif (strpos($mime_type, 'audio') !== false) {
        $placeholder_url = custom_codes_get_placeholder_url('audio');
        $placeholder_path = custom_codes_get_placeholder_path('audio');
        if (file_exists($placeholder_path)) {
            $image_size = getimagesize($placeholder_path);
            $image = array($placeholder_url, $image_size[0], $image_size[1], false);
        }
    }
    
    return $image;
}
add_filter('wp_get_attachment_image_src', 'custom_codes_fix_attachment_image_src', 10, 3);

function custom_codes_fix_post_thumbnail_html($html, $post_id, $post_thumbnail_id) {
    $mime_type = get_post_mime_type($post_thumbnail_id);
    
    if (strpos($mime_type, 'video') !== false) {
        $placeholder_url = custom_codes_get_placeholder_url('video');
        $html = '<img src="' . esc_url($placeholder_url) . '" alt="Video" />';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $placeholder_url = custom_codes_get_placeholder_url('audio');
        $html = '<img src="' . esc_url($placeholder_url) . '" alt="Audio" />';
    }
    
    return $html;
}
add_filter('post_thumbnail_html', 'custom_codes_fix_post_thumbnail_html', 10, 3);

function custom_codes_fix_attachment_metadata($data, $attachment_id) {
    $mime_type = get_post_mime_type($attachment_id);
    
    if (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false) {
        $type = strpos($mime_type, 'video') !== false ? 'video' : 'audio';
        $placeholder_path = custom_codes_get_placeholder_path($type);
        
        if (file_exists($placeholder_path)) {
            $image_size = getimagesize($placeholder_path);
            $data['width'] = $image_size[0];
            $data['height'] = $image_size[1];
        }
    }
    
    return $data;
}
add_filter('wp_get_attachment_metadata', 'custom_codes_fix_attachment_metadata', 10, 2);

function custom_codes_allow_all_media_in_featured_image() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'product') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                if (typeof wp !== 'undefined' && wp.media && wp.media.view) {
                    var originalMediaFrameSelect = wp.media.view.MediaFrame.Select;
                    wp.media.view.MediaFrame.Select = originalMediaFrameSelect.extend({
                        initialize: function() {
                            originalMediaFrameSelect.prototype.initialize.apply(this, arguments);
                            this.on('content:render:browse', function(view) {
                                if (view.collection) {
                                    view.collection.props.set({type: ''});
                                }
                            });
                        }
                    });
                    
                    $(document).on('click', '#set-post-thumbnail', function(e) {
                        var frame = wp.media.frames.file_frame;
                        if (frame) {
                            frame.off('open');
                        }
                        
                        wp.media.featuredImage.frame().on('open', function() {
                            var selection = wp.media.featuredImage.frame().state().get('selection');
                            var library = wp.media.featuredImage.frame().state().get('library');
                            if (library) {
                                library.props.set({type: ''});
                            }
                        });
                    });
                }
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'custom_codes_allow_all_media_in_featured_image');

function custom_codes_filter_media_library($query) {
    if (is_admin() && isset($_POST['action']) && $_POST['action'] === 'query-attachments') {
        if (isset($_POST['post_id'])) {
            $post = get_post($_POST['post_id']);
            if ($post && $post->post_type === 'product') {
                unset($query['post_mime_type']);
            }
        }
    }
    return $query;
}
add_filter('ajax_query_attachments_args', 'custom_codes_filter_media_library', 999);

function custom_codes_start_output_buffer() {
    if (is_product()) {
        ob_start('custom_codes_replace_media_in_output');
    }
}
add_action('template_redirect', 'custom_codes_start_output_buffer', 1);

function custom_codes_replace_media_in_output($buffer) {
    if (!is_product()) {
        return $buffer;
    }
    
    global $product;
    if (!$product) {
        return $buffer;
    }
    
    $replacements = array();
    
    $thumbnail_id = $product->get_image_id();
    if ($thumbnail_id) {
        $mime_type = get_post_mime_type($thumbnail_id);
        if (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false) {
            $file_url = wp_get_attachment_url($thumbnail_id);
            $placeholder_url = strpos($mime_type, 'video') !== false ? custom_codes_get_placeholder_url('video') : custom_codes_get_placeholder_url('audio');
            
            if (strpos($mime_type, 'video') !== false) {
                $media_tag = '<video width="100%" height="auto" controls preload="metadata" style="display:block;max-width:100%;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></video>';
            } else {
                $media_tag = '<div style="width:100%;padding:20px;background:#f5f5f5;text-align:center;"><audio controls preload="metadata" style="width:100%;max-width:500px;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></audio></div>';
            }
            
            $replacements[$thumbnail_id] = array(
                'placeholder' => $placeholder_url,
                'media_tag' => $media_tag,
                'url' => $file_url
            );
        }
    }
    
    $gallery_ids = $product->get_gallery_image_ids();
    if (!empty($gallery_ids)) {
        foreach ($gallery_ids as $attachment_id) {
            $mime_type = get_post_mime_type($attachment_id);
            if (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false) {
                $file_url = wp_get_attachment_url($attachment_id);
                $placeholder_url = strpos($mime_type, 'video') !== false ? custom_codes_get_placeholder_url('video') : custom_codes_get_placeholder_url('audio');
                
                if (strpos($mime_type, 'video') !== false) {
                    $media_tag = '<video width="100%" height="auto" controls preload="metadata" style="display:block;max-width:100%;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></video>';
                } else {
                    $media_tag = '<div style="width:100%;padding:20px;background:#f5f5f5;text-align:center;"><audio controls preload="metadata" style="width:100%;max-width:500px;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></audio></div>';
                }
                
                $replacements[$attachment_id] = array(
                    'placeholder' => $placeholder_url,
                    'media_tag' => $media_tag,
                    'url' => $file_url
                );
            }
        }
    }
    
    if (empty($replacements)) {
        return $buffer;
    }
    
    foreach ($replacements as $attachment_id => $data) {
        $pattern = '/<div[^>]*class="[^"]*woocommerce-product-gallery__image[^"]*"[^>]*data-thumb-alt="[^"]*"[^>]*>.*?<\/div>/s';
        $buffer = preg_replace_callback($pattern, function($matches) use ($data) {
            if (strpos($matches[0], 'data-thumb-alt=""') !== false) {
                return '<div class="woocommerce-product-gallery__image" data-thumb-alt="">' . $data['media_tag'] . '</div>';
            }
            return $matches[0];
        }, $buffer);
        
        $pattern = '/<div[^>]*class="[^"]*woocommerce-product-gallery__image[^"]*"[^>]*>\s*<a[^>]*href="[^"]*' . preg_quote(basename($data['url']), '/') . '[^"]*"[^>]*>.*?<\/a>\s*<\/div>/s';
        $buffer = preg_replace($pattern, '<div class="woocommerce-product-gallery__image">' . $data['media_tag'] . '</div>', $buffer);
        
        $escaped_placeholder = preg_quote($data['placeholder'], '/');
        $pattern = '/<a[^>]*href="[^"]*"[^>]*>\s*<img[^>]*src="[^"]*' . preg_quote(basename($data['placeholder']), '/') . '[^"]*"[^>]*>\s*<\/a>/s';
        $buffer = preg_replace($pattern, $data['media_tag'], $buffer);
        
        $buffer = preg_replace(
            '/<a[^>]*class="[^"]*woocommerce-product-gallery__trigger[^"]*"[^>]*>.*?<\/a>/s',
            '',
            $buffer
        );
    }
    
    return $buffer;
}

function custom_codes_end_output_buffer() {
    if (is_product() && ob_get_level() > 0) {
        ob_end_flush();
    }
}
add_action('shutdown', 'custom_codes_end_output_buffer', 999);