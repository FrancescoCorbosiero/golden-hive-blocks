<?php
/**
 * Golden Hive — Preload & Speculation Rules
 *
 * Admin UI under Tools → GH Preload where admins can configure:
 *   • Media URLs to preload (one per line, optional "|type" override)
 *   • URLs to prerender via the Speculation Rules API
 *   • URLs to prefetch  via the Speculation Rules API
 *
 * Output is injected into <head> on the front end, as early as possible.
 *
 * @package Golden_Hive_Blocks
 * @since   5.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

const GHB_PRELOAD_OPT = 'ghb_preload_settings';

/**
 * Default settings shape.
 */
function ghb_preload_defaults()
{
    return [
        'preload_urls'          => '',
        'preload_homepage_only' => 1,
        'prerender_urls'        => '',
        'prefetch_urls'         => '',
    ];
}

function ghb_preload_get()
{
    $saved = get_option(GHB_PRELOAD_OPT, []);
    return wp_parse_args(is_array($saved) ? $saved : [], ghb_preload_defaults());
}

/* ══════════════════════════════════════════════════════════════════
   ADMIN — Tools → GH Preload
   ══════════════════════════════════════════════════════════════════ */

add_action('admin_menu', 'ghb_preload_admin_menu');
function ghb_preload_admin_menu()
{
    add_management_page(
        __('Golden Hive Preload', 'golden-hive-blocks'),
        __('GH Preload', 'golden-hive-blocks'),
        'manage_options',
        'ghb-preload',
        'ghb_preload_render_page'
    );
}

add_action('admin_init', 'ghb_preload_register_settings');
function ghb_preload_register_settings()
{
    register_setting('ghb_preload_group', GHB_PRELOAD_OPT, [
        'type'              => 'array',
        'sanitize_callback' => 'ghb_preload_sanitize',
        'default'           => ghb_preload_defaults(),
    ]);
}

function ghb_preload_sanitize($input)
{
    $clean = ghb_preload_defaults();
    if (!is_array($input)) {
        return $clean;
    }

    foreach (['preload_urls', 'prerender_urls', 'prefetch_urls'] as $k) {
        if (!isset($input[$k])) {
            continue;
        }
        // Normalize line endings, trim, drop empty / commented lines.
        $lines = preg_split('/\r\n|\r|\n/', (string) $input[$k]);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($l) {
            return $l !== '' && strpos($l, '#') !== 0;
        });
        $clean[$k] = implode("\n", $lines);
    }

    $clean['preload_homepage_only'] = !empty($input['preload_homepage_only']) ? 1 : 0;

    return $clean;
}

function ghb_preload_render_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $opt = ghb_preload_get();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Golden Hive — Preload & Speculation Rules', 'golden-hive-blocks'); ?></h1>
        <p style="max-width:780px">
            <?php esc_html_e('Configure aggressive preloading of critical homepage media and hint the browser to prerender / prefetch the pages your customers are most likely to visit next. Leave any field empty to disable it.', 'golden-hive-blocks'); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields('ghb_preload_group'); ?>

            <h2 style="margin-top:2em"><?php esc_html_e('① Media Preload', 'golden-hive-blocks'); ?></h2>
            <p class="description" style="max-width:780px">
                <?php esc_html_e('One URL per line. Type is auto-detected from the file extension. You can force a type with "|type" (image, video, font, style, script, audio). Lines starting with # are treated as comments.', 'golden-hive-blocks'); ?>
            </p>
            <p class="description" style="max-width:780px">
                <strong><?php esc_html_e('Example:', 'golden-hive-blocks'); ?></strong><br>
                <code>https://example.com/wp-content/uploads/intro.mp4</code><br>
                <code>https://example.com/wp-content/uploads/hero-poster.jpeg</code><br>
                <code>https://example.com/fonts/Inter.woff2|font</code><br>
                <code># this line is ignored</code>
            </p>
            <textarea
                name="<?php echo esc_attr(GHB_PRELOAD_OPT); ?>[preload_urls]"
                rows="10" cols="80" class="large-text code"
                spellcheck="false"
            ><?php echo esc_textarea($opt['preload_urls']); ?></textarea>

            <p>
                <label>
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr(GHB_PRELOAD_OPT); ?>[preload_homepage_only]"
                        value="1"
                        <?php checked($opt['preload_homepage_only'], 1); ?>
                    >
                    <?php esc_html_e('Only output these preloads on the homepage (recommended — otherwise every page pays the cost of downloading homepage media).', 'golden-hive-blocks'); ?>
                </label>
            </p>

            <h2 style="margin-top:2em"><?php esc_html_e('② Speculation Rules — Prerender', 'golden-hive-blocks'); ?></h2>
            <p class="description" style="max-width:780px">
                <?php esc_html_e('Pages very likely to be visited next. Chromium fully renders them in the background so the next navigation is instant. Same-origin only. One URL per line. Use absolute paths (e.g. /shop/) or full URLs on the same domain.', 'golden-hive-blocks'); ?>
            </p>
            <textarea
                name="<?php echo esc_attr(GHB_PRELOAD_OPT); ?>[prerender_urls]"
                rows="6" cols="80" class="large-text code"
                spellcheck="false"
                placeholder="/shop/&#10;/chi-siamo/"
            ><?php echo esc_textarea($opt['prerender_urls']); ?></textarea>

            <h2 style="margin-top:2em"><?php esc_html_e('③ Speculation Rules — Prefetch', 'golden-hive-blocks'); ?></h2>
            <p class="description" style="max-width:780px">
                <?php esc_html_e('Lighter than prerender: the browser fetches the HTML document only, without running scripts or rendering. Cheap, safe, and works for pages the user might visit. One URL per line.', 'golden-hive-blocks'); ?>
            </p>
            <textarea
                name="<?php echo esc_attr(GHB_PRELOAD_OPT); ?>[prefetch_urls]"
                rows="6" cols="80" class="large-text code"
                spellcheck="false"
                placeholder="/contatti/&#10;/product-category/sneakers/"
            ><?php echo esc_textarea($opt['prefetch_urls']); ?></textarea>

            <?php submit_button(__('Save Preload Configuration', 'golden-hive-blocks')); ?>
        </form>

        <hr>
        <h2><?php esc_html_e('What will be output', 'golden-hive-blocks'); ?></h2>
        <p class="description"><?php esc_html_e('Live preview of the markup that will be injected into <head> on the front end based on current settings:', 'golden-hive-blocks'); ?></p>
        <pre style="background:#1e1e1e;color:#d4d4d4;padding:1em;border-radius:4px;overflow:auto;max-width:900px"><?php
            echo esc_html(ghb_preload_build_markup_preview());
        ?></pre>
    </div>
    <?php
}

/* ══════════════════════════════════════════════════════════════════
   FRONTEND — inject <link rel="preload"> + speculation rules
   ══════════════════════════════════════════════════════════════════ */

function ghb_preload_parse_lines($raw)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $out[] = $line;
    }
    return $out;
}

function ghb_preload_detect_type($url)
{
    $path = wp_parse_url($url, PHP_URL_PATH);
    if (!$path) {
        return null;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg'   => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image',
        'webp'  => 'image', 'avif' => 'image', 'svg' => 'image',
        'mp4'   => 'video', 'webm' => 'video', 'mov' => 'video',
        'woff2' => 'font',  'woff' => 'font',
        'css'   => 'style',
        'js'    => 'script', 'mjs' => 'script',
        'mp3'   => 'audio', 'ogg'  => 'audio', 'wav' => 'audio',
    ];
    return $map[$ext] ?? null;
}

/**
 * Build one <link rel="preload"> tag for a given URL+type.
 */
function ghb_preload_build_link_tag($url, $type)
{
    $url = esc_url_raw($url);
    if (!$url || !$type) {
        return '';
    }

    $attrs = sprintf(
        'rel="preload" as="%s" href="%s"',
        esc_attr($type),
        esc_url($url)
    );

    $path = wp_parse_url($url, PHP_URL_PATH) ?: '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    switch ($type) {
        case 'font':
            $mime = $ext === 'woff' ? 'font/woff' : 'font/woff2';
            $attrs .= ' crossorigin type="' . esc_attr($mime) . '"';
            break;
        case 'video':
            $mime = $ext === 'webm' ? 'video/webm'
                  : ($ext === 'mov' ? 'video/quicktime' : 'video/mp4');
            $attrs .= ' type="' . esc_attr($mime) . '"';
            break;
        case 'audio':
            $mime = $ext === 'mp3' ? 'audio/mpeg'
                  : ($ext === 'wav' ? 'audio/wav'  : 'audio/ogg');
            $attrs .= ' type="' . esc_attr($mime) . '"';
            break;
        case 'image':
            // Images listed here are presumed LCP-critical — boost them.
            $attrs .= ' fetchpriority="high"';
            break;
    }

    return "<link $attrs>";
}

/**
 * Returns the full block of markup (preload links + speculation rules script)
 * for the current request (or homepage-only sim in admin preview).
 *
 * @param bool $force_homepage Pretend we're on the homepage (for admin preview).
 */
function ghb_preload_build_markup($force_homepage = false)
{
    $opt = ghb_preload_get();
    $out = [];

    $is_home = $force_homepage || is_front_page() || is_home();

    // Media preloads
    if (!empty($opt['preload_urls']) && (!$opt['preload_homepage_only'] || $is_home)) {
        foreach (ghb_preload_parse_lines($opt['preload_urls']) as $entry) {
            $type = null;
            $url  = $entry;
            if (strpos($entry, '|') !== false) {
                [$url, $type] = array_map('trim', explode('|', $entry, 2));
            }
            if (!$type) {
                $type = ghb_preload_detect_type($url);
            }
            if (!$type) {
                continue;
            }
            $tag = ghb_preload_build_link_tag($url, $type);
            if ($tag) {
                $out[] = $tag;
            }
        }
    }

    // Speculation rules (always output — navigation hints work on any page)
    $rules     = [];
    $prerender = ghb_preload_parse_lines($opt['prerender_urls']);
    $prefetch  = ghb_preload_parse_lines($opt['prefetch_urls']);

    if ($prerender) {
        $rules['prerender'] = [[
            'source' => 'list',
            'urls'   => array_values($prerender),
        ]];
    }
    if ($prefetch) {
        $rules['prefetch'] = [[
            'source' => 'list',
            'urls'   => array_values($prefetch),
        ]];
    }

    if (!empty($rules)) {
        $json = wp_json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $out[] = '<script type="speculationrules">' . $json . '</script>';
    }

    return $out ? implode("\n", $out) . "\n" : '';
}

function ghb_preload_build_markup_preview()
{
    $opt = ghb_preload_get();
    $preview = ghb_preload_build_markup(true);
    if ($preview === '') {
        return '(nothing will be output — all fields are empty)';
    }
    $note = $opt['preload_homepage_only']
        ? "// Preload links are scoped to the homepage only.\n"
        : "// Preload links will be output on every front-end page.\n";
    return $note . $preview;
}

/**
 * Inject into <head> as early as possible (priority 2 runs right after
 * core's own preload emission at priority 1, and before enqueued styles).
 */
add_action('wp_head', 'ghb_preload_output_head', 2);
function ghb_preload_output_head()
{
    $markup = ghb_preload_build_markup();
    if ($markup !== '') {
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput -- markup is built from sanitized + esc_url/esc_attr pieces above.
    }
}
