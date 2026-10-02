# Golden Hive Blocks

WordPress plugin behind the ResellPiacenza WooCommerce store (sneakers and
streetwear). It has 27 Gutenberg blocks under `blocks/`, with shared editor
code in `blocks/shared/`. It also draws the store's WooCommerce UI from
`includes/`: Quick View and Quick Add, the loop add-to-cart button, filters,
live search, product rails, the archive hero, the Store Hub bridge and the
design switches.

- **Theme coupling.** The store runs Shoptimizer + CommerceKit.
  `THEME-COUPLING.md` maps what depends on them and what doesn't; read it
  before touching theme-facing code. New theme-specific code goes behind
  `ghb_theme_module()` (`includes/theme-compat.php`).
- **Assets.** Edit the source `.css` / `js/*.js`, then run
  `npm install && npm run build`. esbuild writes the `.min` siblings, and
  `gh_asset_url()` serves those when present. Commit both.
- **Version.** On any shipped change, bump `Version:` and
  `GOLDEN_HIVE_BLOCKS_VERSION` in `golden-hive-blocks.php` together. The
  version is the asset cache-buster.
- **Tests.** `php tests/hub-rails-test.php` covers the Store Hub decisions in
  pure PHP, without WordPress.
- **Store Hub contract.** The Hub app mirrors the rules in
  `includes/hub-rails-core.php` (for example `ghb_hub_order_ids()`), so
  changing them changes the app's previews.
- **Language.** Customer-facing strings are in Italian, code comments in
  English.
