<?php
/*
Plugin Name: Advanced Product Media
Description: Use video and audio files instead of images for WooCommerce product featured media and gallery.
Version: 1.1.0
Author: Amin Amini
Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit;
}

define('APM_VERSION', '1.1.0');
define('APM_PATH', plugin_dir_path(__FILE__));
define('APM_URL', plugin_dir_url(__FILE__));

function apm_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and active.');
    }
}
add_action('admin_init', 'apm_check_woocommerce');

function apm_allow_video_audio_upload($mimes) {
    $mimes['mp4']  = 'video/mp4';
    $mimes['webm'] = 'video/webm';
    $mimes['ogg']  = 'video/ogg';
    $mimes['mp3']  = 'audio/mpeg';
    $mimes['m4a']  = 'audio/mp4';
    $mimes['wav']  = 'audio/wav';
    return $mimes;
}
add_filter('upload_mimes', 'apm_allow_video_audio_upload');

function apm_get_placeholder_url($type) {
    if ($type === 'video') {
        return APM_URL . 'assets/vid.png';
    } else {
        return APM_URL . 'assets/mus.png';
    }
}

function apm_get_placeholder_path($type) {
    if ($type === 'video') {
        return APM_PATH . 'assets/vid.png';
    } else {
        return APM_PATH . 'assets/mus.png';
    }
}

function apm_generate_media_thumbnail($metadata, $attachment_id) {
    $mime_type = get_post_mime_type($attachment_id);
    if (!$mime_type) {
        return $metadata;
    }

    $type = false;
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    } else {
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

    if (!is_array($metadata)) {
        $metadata = array();
    }
    if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
        $metadata['sizes'] = array();
    }

    $metadata['width']  = $image_size[0];
    $metadata['height'] = $image_size[1];

    $sizes = array('thumbnail', 'medium', 'large', 'woocommerce_thumbnail', 'woocommerce_single', 'full');
    foreach ($sizes as $sz) {
        $metadata['sizes'][$sz] = array(
            'file'      => $filename,
            'width'     => $image_size[0],
            'height'    => $image_size[1],
            'mime-type' => 'image/png',
        );
    }

    update_post_meta($attachment_id, '_apm_media_type', $type);
    if (!get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', ucfirst($type) . ' file');
    }

    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'apm_generate_media_thumbnail', 10, 2);

function apm_fix_attachment_image_src($image, $attachment_id, $size) {
    $mime_type = get_post_mime_type($attachment_id);
    if (!$mime_type) {
        return $image;
    }

    $type = false;
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    } else {
        return $image;
    }

    $placeholder_url  = apm_get_placeholder_url($type);
    $placeholder_path = apm_get_placeholder_path($type);
    if (file_exists($placeholder_path)) {
        $image_size = @getimagesize($placeholder_path);
        if ($image_size) {
            $image = array($placeholder_url, $image_size[0], $image_size[1], false);
        }
    }

    return $image;
}
add_filter('wp_get_attachment_image_src', 'apm_fix_attachment_image_src', 10, 3);

function apm_fix_post_thumbnail_html($html, $post_id, $post_thumbnail_id) {
    if (!$post_thumbnail_id) {
        return $html;
    }
    $mime_type = get_post_mime_type($post_thumbnail_id);
    if (!$mime_type) {
        return $html;
    }

    if (strpos($mime_type, 'video') !== false) {
        $placeholder_url = apm_get_placeholder_url('video');
        $html = '<img src="' . esc_url($placeholder_url) . '" alt="Video" />';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $placeholder_url = apm_get_placeholder_url('audio');
        $html = '<img src="' . esc_url($placeholder_url) . '" alt="Audio" />';
    }

    return $html;
}
add_filter('post_thumbnail_html', 'apm_fix_post_thumbnail_html', 10, 3);

function apm_fix_attachment_metadata($data, $attachment_id) {
    $mime_type = get_post_mime_type($attachment_id);
    if (!$mime_type) {
        return $data;
    }

    if (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false) {
        $type = strpos($mime_type, 'video') !== false ? 'video' : 'audio';
        $placeholder_path = apm_get_placeholder_path($type);

        if (file_exists($placeholder_path)) {
            $image_size = @getimagesize($placeholder_path);
            if ($image_size) {
                if (!$data || !is_array($data)) {
                    $data = array();
                }
                $data['width']  = $image_size[0];
                $data['height'] = $image_size[1];
            }
        }
    }

    return $data;
}
add_filter('wp_get_attachment_metadata', 'apm_fix_attachment_metadata', 10, 2);

function apm_prepare_attachment_for_js($response, $attachment, $meta) {
    $mime_type = get_post_mime_type($attachment->ID);
    if (!$mime_type) {
        return $response;
    }

    $type = false;
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    } else {
        return $response;
    }

    $placeholder_url  = apm_get_placeholder_url($type);
    $placeholder_path = apm_get_placeholder_path($type);

    if (file_exists($placeholder_path)) {
        $image_size = @getimagesize($placeholder_path);
        if ($image_size) {
            $response['sizes'] = isset($response['sizes']) && is_array($response['sizes']) ? $response['sizes'] : array();
            $response['sizes']['thumbnail'] = array(
                'url'    => $placeholder_url,
                'width'  => $image_size[0],
                'height' => $image_size[1],
                'mime'   => 'image/png',
            );
            $response['sizes']['medium'] = $response['sizes']['thumbnail'];
            $response['icon'] = $placeholder_url;
        }
    }

    return $response;
}
add_filter('wp_prepare_attachment_for_js', 'apm_prepare_attachment_for_js', 10, 3);

function apm_allow_all_media_in_featured_image() {
    if (!function_exists('get_current_screen')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'product') {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(function($){
        if (typeof wp === 'undefined' || !wp.media) return;

        function allowAllTypes(frame) {
            try {
                if (!frame) return;
                var state = frame.state && frame.state();
                var library = state && state.get && state.get('library');
                if (library && library.props) {
                    if (typeof library.props.unset === 'function') {
                        library.props.unset('type');
                        library.props.unset('media_type');
                        library.props.unset('post_mime_type');
                    }
                    if (library.props.attributes) {
                        delete library.props.attributes.type;
                        delete library.props.attributes.media_type;
                        delete library.props.attributes.post_mime_type;
                    }
                }
            } catch(e){}
        }

        if (wp.media.featuredImage && typeof wp.media.featuredImage.frame === 'function') {
            var fiFrame = wp.media.featuredImage.frame();
            fiFrame.on('open', function(){
                allowAllTypes(fiFrame);
            });
        }

        function hookWooGalleryFrame() {
            if (wp.media.frames && wp.media.frames.product_gallery) {
                var pgFrame = wp.media.frames.product_gallery;
                pgFrame.on('open', function(){
                    allowAllTypes(pgFrame);
                });
            }
        }

        $(document).on('click', '#add_product_images', function(){
            setTimeout(hookWooGalleryFrame, 100);
        });

        hookWooGalleryFrame();
    });
    </script>
    <?php
}
add_action('admin_footer', 'apm_allow_all_media_in_featured_image');

function apm_filter_media_library($query) {
    if (is_admin() && isset($_REQUEST['action']) && $_REQUEST['action'] === 'query-attachments') {
        $post_id = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
        $post    = $post_id ? get_post($post_id) : null;

        if ($post && $post->post_type === 'product') {
            unset($query['post_mime_type']);
            unset($query['type']);
            unset($query['media_type']);
        }
    }
    return $query;
}
add_filter('ajax_query_attachments_args', 'apm_filter_media_library', 999);

function apm_generate_media_html($attachment_id, $is_featured) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return false;
    }

    $mime_type = get_post_mime_type($attachment_id);
    if (!$mime_type) {
        return false;
    }

    $type = false;
    if (strpos($mime_type, 'video') !== false) {
        $type = 'video';
    } elseif (strpos($mime_type, 'audio') !== false) {
        $type = 'audio';
    } else {
        return false;
    }

    $file_url = wp_get_attachment_url($attachment_id);
    if (!$file_url) {
        return false;
    }

    $placeholder_url = apm_get_placeholder_url($type);
    $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    if (!$alt) {
        $alt = ucfirst($type);
    }

    $classes = 'woocommerce-product-gallery__image apm-' . $type;
    if ($is_featured) {
        $classes .= ' apm-featured';
    }

    if ($type === 'video') {
        $media_markup = '<video controls preload="metadata" style="width:100%;height:auto;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></video>';
    } else {
        $media_markup = '<div class="apm-audio" style="width:100%;padding:20px;background:#f5f5f5;text-align:center;"><audio controls preload="metadata" style="width:100%;max-width:500px;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></audio></div>';
    }

    return '<div class="' . esc_attr($classes) . '" data-thumb="' . esc_url($placeholder_url) . '" data-thumb-alt="' . esc_attr($alt) . '">' . $media_markup . '</div>';
}

function apm_replace_featured_image_with_media($html, $post_id) {
    $product = wc_get_product($post_id);
    if (!$product) {
        return $html;
    }

    $attachment_id = $product->get_image_id();
    if (!$attachment_id) {
        return $html;
    }

    $media_html = apm_generate_media_html($attachment_id, true);
    return $media_html ? $media_html : $html;
}
add_filter('woocommerce_single_product_image_html', 'apm_replace_featured_image_with_media', 10, 2);

function apm_replace_gallery_image_with_media($html, $attachment_id) {
    $media_html = apm_generate_media_html($attachment_id, false);
    return $media_html ? $media_html : $html;
}
add_filter('woocommerce_single_product_image_thumbnail_html', 'apm_replace_gallery_image_with_media', 10, 2);

function apm_media_playback_control() {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var mediaElements = document.querySelectorAll('.woocommerce-product-gallery video, .woocommerce-product-gallery audio');
        mediaElements.forEach(function(media) {
            media.addEventListener('play', function() {
                mediaElements.forEach(function(otherMedia) {
                    if (otherMedia !== media && !otherMedia.paused) {
                        otherMedia.pause();
                    }
                });
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'apm_media_playback_control', 999);
