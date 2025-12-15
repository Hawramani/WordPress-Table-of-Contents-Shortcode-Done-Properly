<?php
/**
 * Plugin Name: WordPress Table-of-Contents Shortcode Done Properly
 * Plugin URI:  https://github.com/Hawramani/WordPress-Table-of-Contents-Shortcode-Done-Properly
 * Description: A lightweight, server-side Table of Contents generator using DOMDocument (libxml). Adds IDs to headings automatically and renders a nested list via the [auto_toc] shortcode.
 * Version: 1.0.0
 * Author: Ikram Hawramani (hawramani.com)
 * License: MIT
 * Text Domain: wp-auto-toc
 * Description: A lightweight, server-side Table of Contents generator using DOMDocument (libxml). Adds IDs to headings automatically and renders a nested list via the [auto_toc] shortcode.
 * Version:     1.0.0
 * Author:      Ikram Hawramani
 * Author URI:  https://hawramani.com
 * License:     MIT
 * Text Domain: wp-auto-toc
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Global variable to pass TOC data from the content filter to the shortcode.
 */
global $wp_auto_toc_items;
$wp_auto_toc_items = [];

/**
 * 1. THE FILTER
 * Parses content, adds IDs to headings, and collects TOC data.
 * Runs with priority 99 to ensure it processes the final HTML (after other shortcodes/filters).
 */
function wp_auto_toc_prepare_content($content) {
    // Only run on single posts/pages and in the main loop
    if (!is_singular() || !in_the_loop() || is_admin()) {
        return $content;
    }

    // Optimization: If the shortcode isn't present, don't parse the DOM
    if (strpos($content, '[auto_toc') === false) {
        return $content;
    }

    global $wp_auto_toc_items;
    $wp_auto_toc_items = []; // Reset for the current loop

    // Suppress libxml errors for malformed HTML fragments
    libxml_use_internal_errors(true);
    
    $dom = new DOMDocument();
    
    // Load HTML with UTF-8 encoding hack
    // LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD prevents adding <html><body> wrappers automatically
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // Select all potential headings (h1-h6)
    $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');

    if ($headings->length > 0) {
        $used_slugs = [];

        foreach ($headings as $heading) {
            $text = $heading->textContent;
            $tag = strtolower($heading->nodeName);
            
            // Create a sanitized ID slug
            $slug = sanitize_title($text);
            if (!$slug) $slug = 'section';

            // Ensure unique IDs (handle duplicate headings)
            if (isset($used_slugs[$slug])) {
                $suffix = 2;
                while (isset($used_slugs[$slug . '-' . $suffix])) {
                    $suffix++;
                }
                $slug .= '-' . $suffix;
            }
            $used_slugs[$slug] = true;

            // Set the ID attribute on the HTML node
            $heading->setAttribute('id', $slug);

            // Add to our global data array
            $wp_auto_toc_items[] = [
                'tag'   => $tag,
                'text'  => $text,
                'id'    => $slug,
                'level' => (int)substr($tag, 1) // e.g., h2 = 2
            ];
        }

        // Save the modified HTML back to content variable
        $content = $dom->saveHTML();
        
        // Remove the UTF-8 hack declaration
        $content = str_replace('<?xml encoding="utf-8" ?>', '', $content);
    }

    return $content;
}
add_filter('the_content', 'wp_auto_toc_prepare_content', 99);

/**
 * 2. THE SHORTCODE
 * Generates the nested list HTML based on the data collected above.
 */
function wp_auto_toc_shortcode($atts) {
    global $wp_auto_toc_items;

    if (empty($wp_auto_toc_items)) {
        return '';
    }

    // Default settings
    $atts = shortcode_atts([
        'tags'  => 'h2,h3', // Default tags to show
        'title' => 'Table of Contents',
    ], $atts);

    // Parse allowed tags into array (e.g., ['h2', 'h3'])
    $allowed_tags = array_map('trim', explode(',', strtolower($atts['tags'])));

    // Filter items to only include requested tags
    $items = array_filter($wp_auto_toc_items, function($item) use ($allowed_tags) {
        return in_array($item['tag'], $allowed_tags);
    });

    if (empty($items)) {
        return '';
    }

    $output  = '<div class="auto-toc-container">';
    if ($atts['title']) {
        $output .= '<div class="auto-toc-title">' . esc_html($atts['title']) . '</div>';
    }
    $output .= '<ul class="auto-toc-list">';

    $current_level = 0;
    $first = true;

    foreach ($items as $item) {
        $level = $item['level'];

        if ($first) {
            $current_level = $level;
            $first = false;
        }

        if ($level > $current_level) {
            // Start nested list
            $output .= "\n<ul>";
        } elseif ($level < $current_level) {
            // Close lists
            $diff = $current_level - $level;
            $output .= str_repeat("</ul></li>", $diff);
        } else {
            // Same level, close previous list item
            $output .= "</li>\n";
        }

        $output .= '<li><a href="#' . esc_attr($item['id']) . '">' . esc_html($item['text']) . '</a>';
        
        $current_level = $level;
    }

    // Close any remaining open tags
    $output .= str_repeat("</li></ul>", 1); 
    
    $output .= '</div>';

    return $output;
}
add_shortcode('auto_toc', 'wp_auto_toc_shortcode');

/**
 * 3. INLINE STYLES
 * Injects basic CSS into the head so the plugin looks good out of the box.
 */
function wp_auto_toc_styles() {
    ?>
    <style>
        .auto-toc-container {
            background: #f9f9f9;
            border: 1px solid #e1e1e1;
            padding: 15px;
            margin: 20px 0;
            display: inline-block;
            min-width: 250px;
            border-radius: 4px;
        }
        .auto-toc-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .auto-toc-list, .auto-toc-list ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .auto-toc-list ul {
            padding-left: 20px; /* Indent nested items */
        }
        .auto-toc-list li {
            margin-bottom: 5px;
            line-height: 1.4;
        }
        .auto-toc-list a {
            text-decoration: none;
            color: inherit;
            border-bottom: 1px solid transparent;
        }
        .auto-toc-list a:hover {
            border-bottom-color: currentColor;
            opacity: 0.8;
        }
    </style>
    <?php
}
add_action('wp_head', 'wp_auto_toc_styles');
