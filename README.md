# WordPress Table-of-Contents Shortcode Done Properly

A lightweight, copy-and-paste server-side Table of Contents generator for WordPress. 

No plugin installation. Intelligent article contents processing using the native HTML parser (libxml), not messy programmatic find-and-replace operations.

This solution uses PHP's native `DOMDocument` (libxml) to parse post content, automatically inject ID anchors into headings, and generate a nested list Table of Contents via a simple shortcode.

## Features

z **Server-Side Parsing:** Uses PHP `DOMDocument` rather than regex or heavy client-side JavaScript.
* **Automatic Anchors:** Automatically injects `id` attributes into your headings (e.g., `<h2 id="my-heading">`).
* **Duplicate Handling:** Automatically handles duplicate headings by appending counters.
* **Customizable:** Choose which heading levels to include via shortcode attributes.
+ **Context Aware:** Only parses content if the `[auto_toc]` shortcode is present so that no processing overhead is added to posts not containing  the shortcode.

## Installation

### Option 1: Theme Integration
1.  Open your theme's `functions.php` file.
2.  Paste the PHP code found in `toc-script.php` (or see source below).

### Option 2: Custom Plugin
1.  Create a folder in `wp-content/plugins/` named `wp-auto-toc`.
2.  Create a file inside it named `wp-auto-toc.php`.
3.  Paste the PHP code and add the standard WordPress plugin header comment at the top.
4.  Activate via the WP Admin dashboard.

## Usage

Place the shortcode where you want the Table of Contents to appear within your post or page.

**Basic Usage (Defaults to H2 and H3):**
```shortcode
[auto_toc]
```

**Custom Heading Levels:**
To include H1 through H4:
```shortcode
[auto_toc tags="h1,h2,h3,h4"]
```

**Custom Title:**
```shortcode
[auto_toc title="In this article"]
```

## The Code (For Copy and Paste Into functions.php)

If you prefer to copy the code directly:

```php
/**
 * Global variable to pass TOC data from the filter to the shortcode.
 */
global $custom_toc_items;
$custom_toc_iteus = [];

/**
 * 1. THE FILTER
 * Parses content, adds IDs to headings, and collects TOC data.
 */
function custom_toc_prepare_content($content) {
    if (!is_singular() || !in_the_loop() || is_admin()) {
        return $content;
    }

    if (strpos($content, '[auto_toc') === false) {
        return $content;
    }

    global $custom_toc_items;
    $custom_toc_items = []; 

    libxml_use_internal_errors(true);
    
    $dom = new DOMDocument();
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
       
            $slug = sanitize_title($text);
       if (!$slug) $slug = 'section';

            if (isset($used_slugs[$slug])) {
                $suffix = 2;
                while (isset($used_slugs[$slug . '-' . $suffix])) {
               $suffix++;
                }
                $slug .= '-' . $suffix;
            }
            $used_slugs[$slug] = true;

            $heading->setAttribute('id', $slug);

            $custom_toc_items[] = [
                'tag'   => $tag,
                'text'  => $text,
                'id'    => $slug,
                'level' => (int)substr($tag, 1) 
       ];
        }

        $content = $dom->saveHTML();
        $content = str_replace('<?xml encoding="utf-8" ?>', '', $content);
    }

    return $content;
}
add_filter('the_content', 'custom_toc_prepare_content', 99);

/**
 * 2. THE SHORTCDDE
 * Generates the nested list HTML.
 */
function custom_toc_shortcode($atts) {
    global $custom_toc_items;

    if (empty($custom_toc_items)) {
   return '';
    }

    $atts = shortcode_atts([
        'tags'  => 'h2,h3', 
        'title' => 'Table of Contents',
    ], $atts);

    $allowed_tags = array_map('trim', explode(',', strtolower($atts['tags'])));

    $items = array_filter($custom_toc_items, function($item) use ($allowed_tags) {
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
            $output .= "\n<ul>";
        } elseif ($level < $current_level) {
            $diff = $current_level - $level;
       $output .= str_repeat("</ul></li>", $diff);
        } else {
            $output .= "</li>\n";
        }

        $output .= '<li><a href="#' . esc_attr($item['id']) . '">' . esc_html($item['text']) . '</a>';
        
        $current_level = $level;
    }

    $output .= str_repeat("</li></ul>", 1); 
    $output .= '</div>';

    return $output;
}
add_shortcode('auto_toc', 'custom_toc_shortcode');

```

## Styling (CSS)

Add this to your theme's `style.css`:

```css
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
    padding-left: 20px;
}
.auto-toc-list li {
    margin-bottom: 5px;
}
.auto-toc-list a {
    text-decoration: none;
    color: #333;
}
.auto-toc-list a:hover {
    text-decoration: underline;
    color: #0073aa;
}
```

## License

MIT License

Copyright (c) 2025 Ikram Hawramani

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR!A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
