<?php
/**
 * Shared render helpers used by block render.php files and the includes/
 * features. Centralises the markup patterns that used to be copy-pasted
 * (CTA button + arrow icon, image output, map-iframe sanitisation) so
 * escaping and conventions live in exactly one place.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Asset URL preferring the minified build when it exists.
 *
 * gh_asset_url('js/animations.js') → …/js/animations.min.js if built
 * (run `npm run build`), otherwise the source file. Callers keep passing
 * the source path so a missing build never breaks the site.
 */
function gh_asset_url($relative)
{
    $min = preg_replace('/\.(js|css)$/', '.min.$1', $relative);

    if ($min !== $relative && file_exists(GOLDEN_HIVE_BLOCKS_PATH . $min)) {
        return GOLDEN_HIVE_BLOCKS_URL . $min;
    }

    return GOLDEN_HIVE_BLOCKS_URL . $relative;
}

/**
 * Inline SVG icon library. All markup is static and safe to echo.
 *
 * @param string $name Icon name.
 * @param int    $size Rendered square size in px.
 * @return string SVG markup, empty string for unknown names.
 */
function gh_icon($name, $size = 18)
{
    $icons = array(
        'arrow-right' => '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M5 12h14M12 5l7 7-7 7"/></svg>',
        'check'       => '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M20 6L9 17l-5-5"/></svg>',
        'close'       => '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M18 6L6 18M6 6l12 12"/></svg>',
        'star'        => '<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
    );

    if (!isset($icons[$name])) {
        return '';
    }

    return sprintf($icons[$name], (int) $size);
}

/**
 * CTA button anchor matching the global .gh-btn system.
 *
 * @param array $args {
 *     @type string $url      Required. Link target.
 *     @type string $text     Required. Visible label (plain text).
 *     @type string $classes  CSS classes. Default primary large.
 *     @type string $icon     gh_icon() name appended after the label,
 *                            '' for none. Default 'arrow-right'.
 *     @type string $magnetic data-gh-magnetic strength, '' to disable.
 *     @type array  $attrs    Extra attributes (name => value).
 * }
 * @return string Anchor markup, empty string when url/text missing.
 */
function gh_button($args)
{
    $args = wp_parse_args($args, array(
        'url'      => '',
        'text'     => '',
        'classes'  => 'gh-btn gh-btn--primary gh-btn--large',
        'icon'     => 'arrow-right',
        'magnetic' => '',
        'attrs'    => array(),
    ));

    if ($args['url'] === '' || $args['text'] === '') {
        return '';
    }

    if ($args['magnetic'] !== '') {
        $args['attrs']['data-gh-magnetic'] = $args['magnetic'];
    }

    $extra = '';
    foreach ($args['attrs'] as $attr_name => $attr_value) {
        $attr_name = preg_replace('/[^a-z0-9\-_]/i', '', (string) $attr_name);
        if ($attr_name === '') {
            continue;
        }
        $extra .= sprintf(' %s="%s"', $attr_name, esc_attr($attr_value));
    }

    return sprintf(
        '<a href="%s" class="%s"%s>%s%s</a>',
        esc_url($args['url']),
        esc_attr($args['classes']),
        $extra,
        esc_html($args['text']),
        $args['icon'] !== '' ? "\n    " . gh_icon($args['icon']) : ''
    );
}

/**
 * Attachment ID for an image URL from this site's media library, 0 when it
 * isn't one.
 *
 * Content saved before blocks tracked image IDs stores only the URL, which
 * used to render a bare <img src> with no srcset — so phones downloaded the
 * full-size original (the homepage hero included). Resolving the ID gives
 * that content srcset/sizes too. Lookups, misses included, are cached in the
 * object cache so each URL costs at most one query.
 */
function gh_attachment_id_from_url($url)
{
    $url = (string) $url;
    if ($url === '') {
        return 0;
    }

    // Root-relative ("/wp-content/uploads/…") → absolute.
    if ($url[0] === '/' && strpos($url, '//') !== 0) {
        $url = home_url($url);
    }

    // Only files under the uploads dir can be attachments: skip the query for
    // anything else (external images, theme assets). Scheme-agnostic.
    $uploads = wp_get_upload_dir();
    $base    = preg_replace('#^https?:#i', '', (string) $uploads['baseurl']) . '/';
    if ($base === '/' || strpos(preg_replace('#^https?:#i', '', $url), $base) !== 0) {
        return 0;
    }

    $key   = 'url2id_' . md5($url);
    $found = false;
    $id    = wp_cache_get($key, 'golden-hive', false, $found);
    if ($found) {
        return (int) $id;
    }

    $id = (int) attachment_url_to_postid($url);
    wp_cache_set($key, $id, 'golden-hive', DAY_IN_SECONDS);

    return $id;
}

/**
 * srcset filter, attached only around gh_img()'s own render: drop resized
 * copies that weigh more than the full-size file.
 *
 * WordPress re-encodes every resized copy itself, so an image squeezed with
 * TinyPNG before upload can end up with resized copies HEAVIER than the
 * original — serving one of those to a phone would slow the page down. Uses
 * the per-file sizes WordPress (6.0+) stores in the attachment metadata;
 * older uploads without them pass through untouched. If fewer than two
 * candidates survive, WordPress drops srcset and the original is served.
 */
function gh_srcset_skip_heavier_copies($sources, $size_array, $image_src, $image_meta)
{
    if (!is_array($sources) || empty($image_meta['filesize']) || empty($image_meta['sizes'])) {
        return $sources;
    }

    $original = (int) $image_meta['filesize'];
    $weights  = array();
    foreach ($image_meta['sizes'] as $sub) {
        if (!empty($sub['file']) && !empty($sub['filesize'])) {
            $weights[$sub['file']] = (int) $sub['filesize'];
        }
    }

    foreach ($sources as $width => $source) {
        $file = wp_basename($source['url']);
        if (isset($weights[$file]) && $weights[$file] > $original) {
            unset($sources[$width]);
        }
    }

    return $sources;
}

/**
 * Render an <img>, preferring the attachment ID — which brings srcset,
 * sizes, width/height (CLS) and the media-library alt text — and falling
 * back to the stored URL for content saved before IDs were tracked. For that
 * legacy content the ID is looked up from the URL (gh_attachment_id_from_url),
 * so it gets srcset as well whenever the image lives in the media library.
 *
 * @param int    $id   Attachment ID (0/absent for legacy content).
 * @param string $url  Fallback image URL.
 * @param array  $args {
 *     @type string      $size          WP image size. Default 'full'.
 *     @type string      $class         CSS classes.
 *     @type string|null $alt           null (default) = let the media library
 *                                      alt win on the ID path; '' = force an
 *                                      empty alt (decorative image); any other
 *                                      string = explicit alt text.
 *     @type string      $loading       'lazy' (default) or 'eager'.
 *     @type string      $decoding      Default 'async'.
 *     @type string      $fetchpriority '' or 'high'.
 *     @type string      $sizes         Custom sizes attribute.
 *     @type string      $style         Inline style.
 * }
 * @return string <img> markup, empty string when no source available.
 */
function gh_img($id, $url, $args = array())
{
    $args = wp_parse_args($args, array(
        'size'          => 'full',
        'class'         => '',
        'alt'           => null,
        'loading'       => 'lazy',
        'decoding'      => 'async',
        'fetchpriority' => '',
        'sizes'         => '',
        'style'         => '',
    ));

    $id       = (int) $id;
    $from_url = false;
    if (!$id && $url !== '') {
        $id       = gh_attachment_id_from_url($url);
        $from_url = $id > 0;
    }

    if ($id && wp_attachment_is_image($id)) {
        $attrs = array(
            'loading'  => $args['loading'],
            'decoding' => $args['decoding'],
        );
        foreach (array('class', 'fetchpriority', 'sizes', 'style') as $key) {
            if ($args[$key] !== '') {
                $attrs[$key] = $args[$key];
            }
        }
        // Legacy content resolved from its URL keeps the class-less markup it
        // always had: WordPress's default attachment-/size-* classes would
        // expose it to theme image rules it never matched before.
        if ($from_url && !isset($attrs['class'])) {
            $attrs['class'] = '';
        }
        // alt: null = media-library alt; '' or a string = explicit override.
        if ($args['alt'] !== null) {
            $attrs['alt'] = $args['alt'];
        }

        add_filter('wp_calculate_image_srcset', 'gh_srcset_skip_heavier_copies', 10, 4);
        $html = wp_get_attachment_image($id, $args['size'], false, $attrs);
        remove_filter('wp_calculate_image_srcset', 'gh_srcset_skip_heavier_copies', 10);
        if ($html !== '') {
            return $html;
        }
    }

    if ($url === '') {
        return '';
    }

    $alt  = $args['alt'] === null ? '' : $args['alt'];
    $html = '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '"';
    foreach (array('class', 'loading', 'decoding', 'fetchpriority', 'sizes', 'style') as $key) {
        if ($args[$key] !== '') {
            $html .= ' ' . $key . '="' . esc_attr($args[$key]) . '"';
        }
    }

    return $html . '>';
}

/**
 * Sanitise a pasted map embed down to a single safe <iframe>.
 * Shared by the map-embed and contact-info blocks (wp_kses_post strips
 * iframes entirely, which silently deleted the map).
 */
function gh_kses_map_iframe($html)
{
    return wp_kses($html, array(
        'iframe' => array(
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'style'           => true,
            'allowfullscreen' => true,
            'loading'         => true,
            'referrerpolicy'  => true,
            'frameborder'     => true,
            'title'           => true,
        ),
    ));
}
