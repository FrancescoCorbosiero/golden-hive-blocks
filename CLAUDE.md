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

There is no CI and no WordPress install here: run the tests yourself, and
test WordPress-dependent code with stubs in a scratch file.

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
   `README.md` / `THEME-COUPLING.md` in the same change.

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
- **Quick View's AJAX handler** lives in
  `includes/product-carousel-shortcode.php`, not `quick-view.php`. Don't
  redeclare it.
- **Sizes are `pa_taglia`** with duplicate spellings in the data; compare
  sizes through the key helpers in `size-match-core.php`, never raw labels.
- **Legacy class names** (`.rp-qv-*`, `.bfl-*`) came from migrated Code
  Snippets and may be targeted by site CSS: don't rename them.
- **`pages/homepage.html`** is copy-paste page markup *and* a fixture for
  `tests/hub-rails-test.php`. `tests/fixtures/homepage.html` is the live
  homepage; don't "tidy" either.

## Language and style

- Customer-facing strings: Italian, text domain `golden-hive-blocks`.
  Store Hub error messages are Italian too.
- Code comments: English (`js/animations.js` predates this and is in
  Italian; leave it unless you are rewriting that part).
- Prefixes: PHP functions `ghb_` (older helpers `gh_`), CSS classes `gh-` /
  `ghb-`, design tokens `--gh-*`. Every radius is
  `calc(N * var(--gh-round))`.
- Escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`), sanitize
  on input, `$wpdb->prepare()` for anything not a cast integer.
