# Golden Hive Blocks

WordPress plugin for the ResellPiacenza WooCommerce store (sneakers,
streetwear): 29 Gutenberg blocks plus the store's WooCommerce interface and
the Store Hub REST bridge. **Read `README.md` first:** it maps every file,
feature, setting and endpoint. Read `THEME-COUPLING.md` before touching
anything theme-facing.

## Commands

```bash
npm install && npm run build      # after editing a root *.css or js/*.js
for t in tests/*-test.php; do php "$t" || exit 1; done && for t in tests/*-test.js; do node "$t" || exit 1; done
find . -name '*.php' -not -path './node_modules/*' -exec php -l {} \; | grep -v 'No syntax errors'
```

`tests/motion-test.js` runs headless Chromium through Playwright (it skips
itself without it). CI (`.github/workflows/tests.yml`) runs all of this on
every push, and fails when a `.min` file doesn't match a fresh build. There
is no WordPress install here: test WordPress-dependent code with stubs in a
scratch file.

## Every shipped change

1. Bump `Version:` **and** `GOLDEN_HIVE_BLOCKS_VERSION` in
   `golden-hive-blocks.php`, together (semver: fix → patch, feature → minor).
   It's the cache-buster for every asset.
2. Edited a root `.css` or `js/*.js`? Run `npm run build` and commit the
   `.min` file too. `gh_asset_url()` serves `.min` when it exists, so a stale
   build ships stale code.
3. Edited `blocks/<name>/editor.js`? Bump `version` in that block's
   `editor.asset.php`.
4. Run all tests.
5. Changed a file's role, a hook, a setting or an endpoint? Update
   `README.md` / `THEME-COUPLING.md` in the same change. Changed a block's
   attributes? Run `php tools/block-reference.php` (CI checks
   `docs/blocks.md` is current).

## Where code goes

- **New WooCommerce feature:** a new `includes/<feature>.php`, required from
  `golden-hive-blocks.php` with a one-line comment. Start the file with a
  docblock that says what it does, where it hooks and how to switch it off.
- **Decisions worth testing:** put them in `includes/<feature>-core.php` as
  plain PHP (no WordPress calls) with a `tests/<feature>-test.php`. Copy an
  existing test's shape (no framework, prints `N passed, M failed`, exits
  non-zero on failure). Browser logic: export pure helpers under Node, as
  `js/live-blocks.js` does.
- **Blocks:** `blocks/<name>/` with `block.json`, a server-side `render.php`,
  and a plain-JS `editor.js` (no JSX, no build) that uses
  `window.ghEditorUtils` and depends on `gh-editor-utils`. Blocks register
  automatically from their folder.
- **Shoptimizer / CommerceKit markup:** only behind `ghb_theme_module()`,
  following the rules at the end of `THEME-COUPLING.md`.
- **Motion:** use the hooks in README's Motion section (`data-gh-reveal`,
  `data-gh-split="lines"`, `data-gh-image-reveal`, `data-gh-drift`); a new
  move goes in `js/motion.js` and keeps its rules. Never hide anything with
  CSS waiting for a script, and never animate the first screen. One strong
  move per section. Change motion? Run `tests/motion-test.js`.

## Rules that aren't obvious

- **Pages are page-cached.** Never render anything visitor- or time-dependent
  into HTML. Ship a frame and fill it in the browser (`js/live-blocks.js`,
  localStorage) or over `admin-ajax.php`.
- **Public AJAX endpoints** (`wp_ajax_nopriv_*`) are nonce-less on purpose,
  so they stay cacheable. Each may serve only what the public shop already
  shows: use `ghb_product_is_public()` for product IDs, `post_status =
  publish` and catalogue visibility in queries, and cap every number a
  visitor can pass. The answer must not depend on who is asking.
- **Store Hub contract.** The Hub app mirrors `includes/hub-rails-core.php`
  (e.g. `ghb_hub_order_ids()`); changing those rules changes the app's
  previews. Keep REST responses backward-compatible within `wc-gh/v1` and
  advertise new abilities through `/capabilities`.
- **Archive order** is a `posts_clauses` chain (10 sort, 20 rail pins, 50
  sold-out last). Never `remove_filter()` a callback from inside its own run.
- **Quick View's and Quick Add's data** come from `includes/product-ajax.php`,
  not `quick-view.php` / `quick-add.php`. Don't redeclare the handlers.
- **Sizes are `pa_taglia`** with duplicate spellings in the data; compare
  sizes through the key helpers in `size-match-core.php`, never raw labels.
- **`[bfl_filters]`** is the filters' older shortcode name and is still
  placed in the shop sidebar: keep it registered next to `[gh_filters]`.
- **No site content in the repo.** Pages live in WordPress. Writing one?
  Follow `docs/pages.md`, look attributes up in `docs/blocks.md`, and run
  `php tools/check-page.php` on it: 0 errors before it goes anywhere. In
  shortcodes inside block JSON, quote attributes with single quotes.
- **`tests/fixtures/homepage.html`** is a real homepage's structure with the
  store's details replaced; the Store Hub tests depend on its exact shape
  (rail keys, block paths). Don't "tidy" it.

## Language and style

- Customer-facing strings: Italian, text domain `golden-hive-blocks`.
  Store Hub error messages are Italian too.
- Code comments: English. Older ones in `style.css` and a few other files
  are still Italian: translate them when you touch that part.
- Prefixes: PHP functions `ghb_` (older helpers `gh_`), CSS classes `gh-` /
  `ghb-` (Quick View `ghb-qv-*`, filters `ghb-filter-*`), design tokens
  `--gh-*`. Every radius is
  `calc(N * var(--gh-round))`; every brand colour is `var(--gh-accent)` (set
  per store in the Customizer), never a hex: the plugin runs on several stores.
- Escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`), sanitize
  on input, `$wpdb->prepare()` for anything not a cast integer.
