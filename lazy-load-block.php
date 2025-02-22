<?php
/**
 * Plugin Name: Lazy Load Block
 * Plugin URI: https://strivewp.com
 * Description: Loads inner blocks with Ajax when they are scrolled into view
 * Version: 1.0.0
 * Author: Strive WP
 * Author URI: https://strivewp.com
 * Text Domain: lazy-load-block
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize the lazy load block plugin
 */
function create_block_lazy_load_block_init() {
    register_block_type(__DIR__, array(
        'render_callback' => 'lazy_block_render_callback'
    ));

    add_action('wp_ajax_lazy_load_block_content', 'lazy_load_block_ajax_handler');
    add_action('wp_ajax_nopriv_lazy_load_block_content', 'lazy_load_block_ajax_handler');
    add_action('wp_enqueue_scripts', 'enqueue_lazy_block_scripts');
}
add_action('init', 'create_block_lazy_load_block_init');

/**
 * Enqueues frontend scripts
 */
function enqueue_lazy_block_scripts() {
    wp_enqueue_script(
        'lazy-load-block-frontend',
        plugins_url('build/frontend.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-components'),
        filemtime(plugin_dir_path(__FILE__) . 'build/frontend.js'),
        true
    );

    // Get block gap value
    $block_gap = wp_get_global_styles(['spacing', 'blockGap']) ?? '16px';

    // Known block CSS variables that need preserving
    $css_vars = array(
        '--wp--style--unstable-gallery-gap' => $block_gap,
        '--wp--style--block-gap' => $block_gap,
        // Add others as we discover them
    );

    // Build inline CSS
    $inline_css = ":root {\n";
    foreach ($css_vars as $var => $value) {
        $inline_css .= "    {$var}: {$value};\n";
    }
    $inline_css .= "}";

    // Add CSS variables to both script and inline style
    wp_localize_script('lazy-load-block-frontend', 'lazyBlockAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('lazy_block_nonce'),
        'wpUrl' => includes_url(),
        'cssVars' => $css_vars // Pass to JS in case we need them
    ));

    wp_add_inline_style('wp-block-library', $inline_css);
}

/**
 * Renders the initial empty container
 */
function lazy_block_render_callback($attributes, $content, $block) {
    $block_id = isset($attributes['dataBlockId']) ? esc_attr($attributes['dataBlockId']) : '';
    
    $wrapper_attributes = get_block_wrapper_attributes([
        'class' => 'wp-block-strive-lazy-load-block lazy-load-block',
        'data-block-id' => $block_id,
        'data-animation' => isset($attributes['animation']) ? esc_attr($attributes['animation']) : 'fade',
        'data-animation-duration' => isset($attributes['animationDuration']) ? intval($attributes['animationDuration']) : 300,
        'data-spinner-size' => isset($attributes['spinnerSize']) ? intval($attributes['spinnerSize']) : 40,
        'data-spinner-border' => isset($attributes['spinnerBorderWidth']) ? intval($attributes['spinnerBorderWidth']) : 4,
        'data-spinner-primary' => isset($attributes['spinnerPrimaryColor']) ? esc_attr($attributes['spinnerPrimaryColor']) : '#c214bf',
        'data-spinner-secondary' => isset($attributes['spinnerSecondaryColor']) ? esc_attr($attributes['spinnerSecondaryColor']) : '#290529',
        'data-show-spinner' => isset($attributes['showSpinner']) ? (($attributes['showSpinner']) ? 'true' : 'false') : 'true',
        'data-loading-offset' => isset($attributes['loadingOffset']) ? intval($attributes['loadingOffset']) : 100
    ]);

    return sprintf('<div %s></div>', $wrapper_attributes);
}

/**
 * AJAX handler for loading block content
 */
function lazy_load_block_ajax_handler() {
    $start = microtime(true);
    
    // Initialize timing array with all points to avoid undefined warnings
    $GLOBALS['lazy_block_timings'] = [
        'start' => $start,
        'points' => [
            'init' => $start,
            'template_type' => $start,
            'functions_defined' => $start,
            'page_check' => $start,
            'template_check' => $start,
            'block_found' => $start,
            'blocks_rendered' => $start,
            'embeds_processed' => $start
        ]
    ];
    
    // Create timing function
    function mark_time($point) {
        $GLOBALS['lazy_block_timings']['points'][$point] = microtime(true);
    }

    // Verify nonce first
    if (!isset($_POST['block_id']) || !isset($_POST['nonce']) || 
        !wp_verify_nonce($_POST['nonce'], 'lazy_block_nonce')) {
        wp_send_json_error(['message' => 'Invalid request']);
        return;
    }

    // Validate referrer
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($referrer)) {
        wp_send_json_error(['message' => 'Invalid referrer']);
        return;
    }

    $block_id = sanitize_text_field($_POST['block_id']);
    
    // Comment out debug logging
    /*if (WP_DEBUG) {
        error_log(sprintf(
            "Processing lazy block: %s (URL: %s)",
            $block_id,
            $referrer
        ));
    }*/

    // Get template type
    $current_template_type = get_template_type();
    mark_time('template_type');

    // Helper function to find block in array of blocks
    function find_block_by_id($blocks, $target_id, $depth = 0, $context = '') {
        global $block_cache, $ref_cache;
        
        // Early returns for performance
        if (empty($blocks) || $depth > 20) {
            return null;
        }
        
        foreach ($blocks as $block) {
            // Skip empty or invalid blocks
            if (empty($block['blockName'])) {
                continue;
            }

            // Direct match check
            if ($block['blockName'] === 'strive/lazy-load-block' && 
                isset($block['attrs']['dataBlockId']) && 
                $block['attrs']['dataBlockId'] === $target_id) {
                return [
                    'block' => $block,
                    'location' => $context
                ];
            }

            // Check inner blocks
            if (!empty($block['innerBlocks'])) {
                $inner_found = find_block_by_id($block['innerBlocks'], $target_id, $depth + 1, $context);
                if ($inner_found) return $inner_found;
            }

            // Reusable block check
            if ($block['blockName'] === 'core/block' && !empty($block['attrs']['ref'])) {
                $ref = $block['attrs']['ref'];
                if (!isset($ref_cache[$ref])) {
                    $ref_post = get_post($ref);
                    $ref_cache[$ref] = $ref_post ? parse_blocks($ref_post->post_content) : [];
                }
                $reusable_found = find_block_by_id(
                    $ref_cache[$ref], 
                    $target_id, 
                    $depth + 1, 
                    "Reusable Block: " . get_the_title($ref)
                );
                if ($reusable_found) return $reusable_found;
            }
        }

        return null;
    }

    mark_time('functions_defined');

    $target_block = null;
    
    // For pages, check post content first
    if ($current_template_type === 'page') {
        $post_id = url_to_postid($referrer);
        
        // Validate post ID
        if (!$post_id) {
            if (WP_DEBUG) {
                error_log('Failed to get post ID from referrer: ' . $referrer);
            }
            wp_send_json_error(['message' => 'Post not found']);
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            if (WP_DEBUG) {
                error_log('Failed to get post object for ID: ' . $post_id);
            }
            wp_send_json_error(['message' => 'Post content not found']);
            return;
        }

        if (WP_DEBUG) {
            error_log('Checking post content first for page: ' . $post_id);
        }
        setup_postdata($post);
        $blocks = parse_blocks($post->post_content);
        $found = find_block_by_id($blocks, $block_id, 0, "Page: " . get_the_title($post_id));
        if ($found) {
            $target_block = $found['block'];
            $block_location = $found['location'];
        }
        mark_time('page_check');
    }

    // Cache template content and parsed blocks
    static $template_cache = [];
    static $parsed_blocks_cache = [];
    static $template_parts_cache = [];
    
    if (!$target_block) {
        $template_key = get_stylesheet() . '//' . $current_template_type;
        
        // Get template from cache or load it
        if (!isset($template_cache[$template_key])) {
            $template_cache[$template_key] = get_block_template($template_key);
        }
        $current_template = $template_cache[$template_key];
        
        if ($current_template && $current_template->content) {
            // Get parsed blocks from cache or parse them
            if (!isset($parsed_blocks_cache[$template_key])) {
                $parsed_blocks_cache[$template_key] = parse_blocks($current_template->content);
            }
            $blocks = $parsed_blocks_cache[$template_key];

            if (WP_DEBUG) {
                error_log(sprintf(
                    "Using %d blocks from %s template",
                    count($blocks),
                    $current_template_type
                ));
            }

            // Check main template
            $found = find_block_by_id(
                $blocks, 
                $block_id, 
                0, 
                ucfirst($current_template_type) . " Template"
            );

            // Check template parts
            if (!$target_block) {
                // Get template parts from cache or find them
                if (!isset($template_parts_cache[$template_key])) {
                    $template_parts_cache[$template_key] = array_filter($blocks, function($block) {
                        return $block['blockName'] === 'core/template-part';
                    });
                }
                $template_parts = $template_parts_cache[$template_key];

                foreach ($template_parts as $part_block) {
                    if (!isset($part_block['attrs']['slug'])) continue;
                    
                    $part_key = get_stylesheet() . '//' . $part_block['attrs']['slug'];
                    
                    // Get template part from cache or load it
                    if (!isset($template_cache[$part_key])) {
                        $template_cache[$part_key] = get_block_template(
                            $part_key,
                            'wp_template_part'
                        );
                    }
                    $template_part = $template_cache[$part_key];

                    if ($template_part && $template_part->content) {
                        $blocks = parse_blocks($template_part->content);
                        $found = find_block_by_id(
                            $blocks, 
                            $block_id, 
                            0, 
                            "Template Part: " . ucfirst($part_block['attrs']['slug'])
                        );
                        if ($found) {
                            $target_block = $found['block'];
                            $block_location = $found['location'];
                            break;
                        }
                    }
                }
            }
        }
        mark_time('template_check');
    } else {
        // If we found the block in post content, still mark template check
        mark_time('template_check');
    }

    if (!$target_block || empty($target_block['innerBlocks'])) {
        if (WP_DEBUG) {
            error_log('Block not found in any location');
        }
        wp_send_json_error(['message' => 'Block content not found']);
        return;
    }

    mark_time('block_found');

    // Set up embed handling
    if (!class_exists('WP_Embed')) {
        require_once ABSPATH . WPINC . '/class-wp-embed.php';
    }
    global $wp_embed;

    // Add filter to handle embeds
    add_filter('render_block', function($block_content, $block) {
        if ($block['blockName'] === 'core/embed' || strpos($block['blockName'], 'embed') !== false) {
            wp_enqueue_script('wp-embed');
            wp_enqueue_script('wp-oembed');
            if (wp_style_is('wp-embed', 'registered')) {
                wp_enqueue_style('wp-embed');
            }
            
            if (isset($block['attrs']['url']) && strpos($block['attrs']['url'], 'vimeo.com') !== false) {
                wp_enqueue_script('vimeo-player', 'https://player.vimeo.com/api/player.js', array(), null, true);
            }
        }
        return $block_content;
    }, 10, 2);

    // Render inner blocks
    $content = '';
    foreach ($target_block['innerBlocks'] as $inner_block) {
        $content .= render_block($inner_block);
    }
    mark_time('blocks_rendered');

    // Only reset post data if we were working with a post
    if (isset($post) && $post) {
        wp_reset_postdata();
    }

    $content = $wp_embed->autoembed($wp_embed->run_shortcode($content));
    mark_time('embeds_processed');
    
    // End timing and log
    $timings = $GLOBALS['lazy_block_timings'];
    $points = $timings['points'];
    $start = $timings['start'];

    $measurements = [];
    $previous = $start;

    foreach ($points as $point => $time) {
        $measurements[$point] = ($time - $previous) * 1000;
        $previous = $time;
    }

    $debug_log = sprintf(
        '<div class="llb-debug-wrapper"><span class="llb-debug-trigger">Demo Debug Log ℹ️</span><div class="llb-debug-content">Found in: <strong>%s</strong> | Template type detection: %.2f ms | Page content check: %.2f ms | Template check: %.2f ms | Block rendering: %.2f ms | Embed processing: %.2f ms | <strong>Total time: %.2f ms</strong></div></div>',
        $block_location ?? 'Unknown Location',
        $measurements['template_type'],
        $measurements['page_check'],
        $measurements['template_check'],
        isset($measurements['blocks_rendered']) ? $measurements['blocks_rendered'] : 0,
        isset($measurements['embeds_processed']) ? $measurements['embeds_processed'] : 0,
        (end($points) - $start) * 1000
    );

    // Sanitize and encode the response
    $response = array(
        'content' => $content,
        'template_type' => $current_template_type,
        'success' => true,
        'debug' => $debug_log  // Remove WP_DEBUG check
    );

    // Use wp_send_json to safely encode and send the response
    wp_send_json($response);
}

/**
 * Determines the current template type with error handling
 */
function get_template_type() {
    static $template_type = null;
    static $template_cache = [];

    if ($template_type !== null) {
        return $template_type;
    }

    if (wp_doing_ajax()) {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        if (empty($referrer)) {
            if (WP_DEBUG) {
                error_log('No referrer URL provided');
            }
            return 'index';
        }

        $post_id = url_to_postid($referrer);
        $path = parse_url($referrer, PHP_URL_PATH);

        if (WP_DEBUG) {
            error_log(sprintf(
                "Template Debug:\n- Path: %s\n- Post ID: %s\n- Post Type: %s",
                $path ?: 'none',
                $post_id ?: 'none',
                $post_id ? get_post_type($post_id) : 'none'
            ));
        }

        // Check for blog page first (most specific)
        if ($post_id && $post_id === (int)get_option('page_for_posts')) {
            $template_type = 'home';
            return $template_type;
        }

        // Then check post type with validation
        if ($post_id) {
            $post_type = get_post_type($post_id);
            if (!$post_type) {
                if (WP_DEBUG) {
                    error_log('Invalid post type for ID: ' . $post_id);
                }
                return 'index';
            }
            $template_type = ($post_type === 'page') ? 'page' : 'single';
            return $template_type;
        }
    }

    // Cache stylesheet prefix
    $stylesheet_prefix = get_stylesheet() . '//';

    // Check template existence with caching
    foreach (['page', 'single', 'archive', 'home', 'index'] as $type) {
        $template_key = $stylesheet_prefix . $type;
        
        if (!isset($template_cache[$template_key])) {
            $template_cache[$template_key] = get_block_template($template_key) !== null;
        }

        if ($template_cache[$template_key]) {
            $template_type = $type;
            return $template_type;
        }
    }

    // Default fallback
    $template_type = 'index';
    return $template_type;
}

function get_block_location($block_id) {
    global $block_cache;
    
    // Return the stored location if we found it
    if (isset($block_cache[$block_id . '_location'])) {
        return $block_cache[$block_id . '_location'];
    }

    // Fallback to template type if no specific location was found
    return 'Unknown Location';
} 