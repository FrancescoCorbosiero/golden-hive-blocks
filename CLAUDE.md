# Golden Hive Blocks

WordPress plugin behind the ResellPiacenza WooCommerce store (sneakers and
streetwear). It has 29 Gutenberg blocks under `blocks/`, with shared editor
code in `blocks/shared/`. It also draws the store's WooCommerce UI from
`includes/`: Quick View and Quick Add, the loop add-to-cart button, filters,
live search, product rails ("Nella tua taglia" included), the archive hero,
the product page additions (WhatsApp, share, delivery estimate, colorway and
recently viewed rails), drops (release date, lock, calendar), social proof
from real orders, size alerts on the newsletter, the Store Hub bridge and the
design switches. The time-dependent parts of the blocks run in the browser
(`js/live-blocks.js`, `js/calendar.js`), so cached pages stay true.

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
- **Tests.** Pure, without WordPress: `php tests/hub-rails-test.php` (Store
  Hub decisions), `php tests/size-match-test.php` (size matching, sorting,
  grouping), `php tests/size-alerts-test.php` (who hears about an arrival),
  `php tests/social-proof-test.php`, `php tests/drops-test.php` (.ics and
  Google Calendar), `node tests/delivery-estimate-test.js` and
  `node tests/live-blocks-test.js` (opening hours, drop countdown). Each
  `*-core.php` file holds the logic its test covers.
- **Editor scripts.** Changing a block's `editor.js` means bumping the
  `version` in its `editor.asset.php`: it is the editor script's cache-buster.
- **Store Hub contract.** The Hub app mirrors the rules in
  `includes/hub-rails-core.php` (for example `ghb_hub_order_ids()`), so
  changing them changes the app's previews.
- **Language.** Customer-facing strings are in Italian, code comments in
  English.
