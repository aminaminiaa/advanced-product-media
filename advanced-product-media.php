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

define('CUSTOM_CODES_VERSION', '1.1.0');
define('CUSTOM_CODES_PATH', plugin_dir_path(__FILE__));
define('CUSTOM_CODES_URL', plugin_dir_url(__FILE__));

/**
 * Ensure WooCommerce is active
 */
function custom_codes_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and active.');
    }
}
add_action('admin_init', 'custom_codes_check_woocommerce');

/**
 * Allow video/audio uploads to Media Library
 */
function custom_codes_allow_video_audio_upload($mimes) {
    $mimes['mp4']  = 'video/mp4';
    $mimes['webm'] = 'video/webm';
    $mimes['ogg']  = 'video/ogg';
    $mimes['mp3']  = 'audio/mpeg';
    $mimes['m4a']  = 'audio/mp4';
    $mimes['wav']  = 'audio/wav';
    return $mimes;
}
add_filter('upload_mimes', 'custom_codes_allow_video_audio_upload');

/**
 * Placeholder helpers (make sure assets/vid.png and assets/mus.png exist)
 */
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

/**
 * Generate metadata for non-image attachments with placeholder sizes
 * (kept minimal, safe-guards added)
 */
function custom_codes_generate_media_thumbnail($metadata, $attachment_id) {
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

    $placeholder_path = custom_codes_get_placeholder_path($type);
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

    update_post_meta($attachment_id, '_custom_codes_media_type', $type);
    if (!get_post_meta($attachment_id, '_wp_attachment_image_alt', true)) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', ucfirst($type) . ' file');
    }

    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'custom_codes_generate_media_thumbnail', 10, 2);

/**
 * Ensure image src for audio/video returns placeholder image
 */
function custom_codes_fix_attachment_image_src($image, $attachment_id, $size) {
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

    $placeholder_url  = custom_codes_get_placeholder_url($type);
    $placeholder_path = custom_codes_get_placeholder_path($type);
    if (file_exists($placeholder_path)) {
        $image_size = @getimagesize($placeholder_path);
        if ($image_size) {
            $image = array($placeholder_url, $image_size[0], $image_size[1], false);
        }
    }

    return $image;
}
add_filter('wp_get_attachment_image_src', 'custom_codes_fix_attachment_image_src', 10, 3);

/**
 * Replace featured image HTML for audio/video with placeholder <img>
 */
function custom_codes_fix_post_thumbnail_html($html, $post_id, $post_thumbnail_id) {
    if (!$post_thumbnail_id) {
        return $html;
    }
    $mime_type = get_post_mime_type($post_thumbnail_id);
    if (!$mime_type) {
        return $html;
    }

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

/**
 * Fix attachment metadata width/height for audio/video
 */
function custom_codes_fix_attachment_metadata($data, $attachment_id) {
    $mime_type = get_post_mime_type($attachment_id);
    if (!$mime_type) {
        return $data;
    }

    if (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false) {
        $type = strpos($mime_type, 'video') !== false ? 'video' : 'audio';
        $placeholder_path = custom_codes_get_placeholder_path($type);

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
add_filter('wp_get_attachment_metadata', 'custom_codes_fix_attachment_metadata', 10, 2);

/**
 * Prepare attachment for JS in Media Library to ensure preview shows
 */
function custom_codes_prepare_attachment_for_js($response, $attachment, $meta) {
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

    $placeholder_url  = custom_codes_get_placeholder_url($type);
    $placeholder_path = custom_codes_get_placeholder_path($type);

    if (file_exists($placeholder_path)) {
        $image_size = @getimagesize($placeholder_path);
        if ($image_size) {
            // Ensure grid has something to render
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
add_filter('wp_prepare_attachment_for_js', 'custom_codes_prepare_attachment_for_js', 10, 3);

/**
 * Admin: Allow all media types in Featured Image and WooCommerce Product Gallery
 * IMPORTANT: Do NOT override wp.media.view.MediaFrame.Select globally.
 */
function custom_codes_allow_all_media_in_featured_image() {
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

        // Featured Image frame
        if (wp.media.featuredImage && typeof wp.media.featuredImage.frame === 'function') {
            var fiFrame = wp.media.featuredImage.frame();
            fiFrame.on('open', function(){
                allowAllTypes(fiFrame);
            });
        }

        // WooCommerce Product Gallery frame
        function hookWooGalleryFrame() {
            if (wp.media.frames && wp.media.frames.product_gallery) {
                var pgFrame = wp.media.frames.product_gallery;
                pgFrame.on('open', function(){
                    allowAllTypes(pgFrame);
                });
            }
        }

        // When clicking "Add product gallery images" button, Woo creates the frame
        $(document).on('click', '#add_product_images', function(){
            setTimeout(hookWooGalleryFrame, 100);
        });

        // If already created (reloads, etc.)
        hookWooGalleryFrame();
    });
    </script>
    <?php
}
add_action('admin_footer', 'custom_codes_allow_all_media_in_featured_image');

/**
 * Server-side: Remove restrictive filters for product media queries
 */
function custom_codes_filter_media_library($query) {
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
add_filter('ajax_query_attachments_args', 'custom_codes_filter_media_library', 999);

/**
 * Frontend: Replace WooCommerce product images in output with video/audio players
 * Uses output buffering and regex-based replacement (brittle but contained).
 */
function custom_codes_start_output_buffer() {
    if (function_exists('is_product') && is_product()) {
        ob_start('custom_codes_replace_media_in_output');
    }
}
add_action('template_redirect', 'custom_codes_start_output_buffer', 1);

function custom_codes_replace_media_in_output($buffer) {
    if (!function_exists('is_product') || !is_product()) {
        return $buffer;
    }

    global $product;
    if (!$product || !is_a($product, 'WC_Product')) {
        return $buffer;
    }

    $replacements = array();

    // Featured
    $thumbnail_id = $product->get_image_id();
    if ($thumbnail_id) {
        $mime_type = get_post_mime_type($thumbnail_id);
        if ($mime_type && (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false)) {
            $file_url = wp_get_attachment_url($thumbnail_id);
            $type     = strpos($mime_type, 'video') !== false ? 'video' : 'audio';

            if ($type === 'video') {
                $media_tag = '<video width="100%" height="auto" controls preload="metadata" style="display:block;max-width:100%;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></video>';
            } else {
                $media_tag = '<div style="width:100%;padding:20px;background:#f5f5f5;text-align:center;"><audio controls preload="metadata" style="width:100%;max-width:500px;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></audio></div>';
            }

            $replacements[$thumbnail_id] = array(
                'media_tag' => $media_tag,
                'url'       => $file_url,
                'type'      => $type,
            );
        }
    }

    // Gallery
    $gallery_ids = $product->get_gallery_image_ids();
    if (!empty($gallery_ids)) {
        foreach ($gallery_ids as $attachment_id) {
            $mime_type = get_post_mime_type($attachment_id);
            if ($mime_type && (strpos($mime_type, 'video') !== false || strpos($mime_type, 'audio') !== false)) {
                $file_url = wp_get_attachment_url($attachment_id);
                $type     = strpos($mime_type, 'video') !== false ? 'video' : 'audio';

                if ($type === 'video') {
                    $media_tag = '<video width="100%" height="auto" controls preload="metadata" style="display:block;max-width:100%;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></video>';
                } else {
                    $media_tag = '<div style="width:100%;padding:20px;background:#f5f5f5;text-align:center;"><audio controls preload="metadata" style="width:100%;max-width:500px;"><source src="' . esc_url($file_url) . '" type="' . esc_attr($mime_type) . '"></audio></div>';
                }

                $replacements[$attachment_id] = array(
                    'media_tag' => $media_tag,
                    'url'       => $file_url,
                    'type'      => $type,
                );
            }
        }
    }

    if (empty($replacements)) {
        return $buffer;
    }

    foreach ($replacements as $attachment_id => $data) {
        // Replace gallery image containers with media tag where possible
        $pattern = '/<div[^>]*class="[^"]*woocommerce-product-gallery__image[^"]*"[^>]*data-thumb-alt="[^"]*"[^>]*>.*?<\/div>/s';
        $buffer = preg_replace_callback($pattern, function($matches) use ($data) {
            if (strpos($matches[0], 'data-thumb-alt=""') !== false) {
                return '<div class="woocommerce-product-gallery__image" data-thumb-alt="">' . $data['media_tag'] . '</div>';
            }
            return $matches[0];
        }, $buffer);

        // Replace anchor-wrapped images for specific attachment file
        $pattern = '/<div[^>]*class="[^"]*woocommerce-product-gallery__image[^"]*"[^>]*>\s*<a[^>]*href="[^"]*' . preg_quote(basename($data['url']), '/') . '[^"]*"[^>]*>.*?<\/a>\s*<\/div>/s';
        $buffer  = preg_replace($pattern, '<div class="woocommerce-product-gallery__image">' . $data['media_tag'] . '</div>', $buffer);

        // Replace direct <img> placeholders in anchors
        $placeholder_url = custom_codes_get_placeholder_url($data['type']);
        $pattern = '/<a[^>]*href="[^"]*"[^>]*>\s*<img[^>]*src="[^"]*' . preg_quote(basename($placeholder_url), '/') . '[^"]*"[^>]*>\s*<\/a>/s';
        $buffer  = preg_replace($pattern, $data['media_tag'], $buffer);

        // Remove zoom trigger (not relevant for video/audio)
        $buffer  = preg_replace('/<a[^>]*class="[^"]*woocommerce-product-gallery__trigger[^"]*"[^>]*>.*?<\/a>/s', '', $buffer);
    }

    return $buffer;
}

function custom_codes_end_output_buffer() {
    if (function_exists('is_product') && is_product() && ob_get_level() > 0) {
        ob_end_flush();
    }
}
add_action('shutdown', 'custom_codes_end_output_buffer', 999);

/**
 * Frontend: pause other media when one plays
 */
function custom_codes_media_playback_control() {
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
add_action('wp_footer', 'custom_codes_media_playback_control', 999);
