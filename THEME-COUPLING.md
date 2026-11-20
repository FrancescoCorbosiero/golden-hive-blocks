# Theme coupling

What in this plugin depends on the **Shoptimizer** theme and **CommerceKit**
(its companion plugin), what depends only on WooCommerce, and what a theme
change would take.

**In short:** four modules are written for Shoptimizer / CommerceKit, each
switched by `ghb_theme_module()` in `includes/theme-compat.php`, and they
turn themselves off on any other theme. Everything else uses WooCommerce's own
hooks and markup and works on any classic WooCommerce theme. On a block (FSE)
theme, two things need porting.

*Checked against the code at 5.17.0. Last checked on a live store at 5.12.0
(WooCommerce 9.9.5, WordPress 6.8). Twenty Twenty-Five was used for the
block-theme notes.*

## 1. Shoptimizer / CommerceKit modules

`ghb_theme_module($key)` is true when the parent theme is Shoptimizer (a child
theme counts). On another theme these modules load no CSS, no JS and no
Customizer option.

| Key | Files | What it targets | On another theme |
|---|---|---|---|
| `theme-bridge` | `theme-bridge.css`, `includes/design.php`, `ghb_pp_theme_container()` in `includes/product-page.php` + `js/product-page.js` | Shoptimizer's product cards, sale badges, buttons, fields, pagination; its `#secondary` sidebar (turned into the desktop filters drawer) and `#primary` / `.content-area`; its `.col-full` container | Off. The corner-shape switch still reshapes everything the plugin draws, but not the theme's own surfaces. The "Filtri su desktop" option disappears, because the drawer *is* this stylesheet. Product page sections fall back to the plugin's own max-width. |
| `mobile-nav` | `includes/mobile-nav.php`, `mobile-nav.css`, `js/mobile-nav.js` | Shoptimizer's menu: `.main-navigation`, `.menu-item-has-children`, `.caret`, `.sub-menu-wrapper`, `.cg-open` | Off |
| `swatch-prices` | `includes/variation-swatch-prices.php`, `variation-swatch-prices.css`, `js/variation-swatch-prices.js` | CommerceKit swatches: `.cgkit-attribute-swatches[data-attribute="attribute_pa_taglia"]`, `.cgkit-swatch` | Off |
| `swatch-sale-badges` | `includes/swatch-sale-badges.php` | CommerceKit swatch buttons: `[data-attribute] button[data-attribute-value]` | Off |

**The product page container.** The product page's band and rails line up
with Shoptimizer's `.col-full`, but they can't simply use that class: the
theme resets every `.col-full` inside the product area to full width. So
`js/product-page.js` measures a probe placed outside that area and copies its
width and gutters. Until the script runs, `product-page.css` holds the store's
1400px.

**Sold-out swatches stay clickable.** `swatch-prices` greys out sold-out sizes
but doesn't block clicks, because CommerceKit's Waitlist shows its form when
one is selected. (Blocking them, as before 5.13.0, hid the waitlist.)

**One harmless leftover.** While the archive hero is on, `archive-hero.css`
hides `.shoptimizer-category-banner` (the theme's second h1). On other themes
the selector matches nothing.

**CommerceKit features the plugin leaves alone:** wishlist, waitlist, size
guides, sticky add-to-cart and the free-shipping bar. The product page
additions are designed not to duplicate them.

### Switches

Force a module on or off, for example to keep CommerceKit on another theme:

```php
add_filter('ghb_theme_module', function ($on, $module) {
    return in_array($module, array('swatch-prices', 'swatch-sale-badges'), true) ? true : $on;
}, 10, 2);
```

Turn the bridge off on Shoptimizer: `add_filter('ghb_design_theme_bridge', '__return_false')`.

## 2. WooCommerce hooks (any classic theme)

These hook WooCommerce's templates, not the theme's. They work on Storefront,
Kadence, GeneratePress, Astra, Blocksy and the like. The number after a hook
is its priority.

**Shop and archive cards**

| Feature | Hook / markup |
|---|---|
| Card add-to-cart, size picker, "Esaurito" | `woocommerce_after_shop_loop_item` (10) |
| Quick View button | `woocommerce_before_shop_loop_item_title` (11) |
| Second photo on hover (rail cards only) | `woocommerce_before_shop_loop_item_title` (12) |
| "Taglia 42 · 180 €" line, size pre-picked in Quick View / Quick Add | `woocommerce_after_shop_loop_item_title` (15), the `filter_taglia` URL param |
| Shop grid alignment | `ul.products li.product` (`shop-grid.css`, `js/shop-grid.js`) |
| Product rails | `wc_get_template_part('content', 'product')` inside the rail's markup. The photo plate assumes the card opens with a full-width photo, as WooCommerce's template does. |

**Archive pages**

| Feature | Hook / markup |
|---|---|
| Archive hero | `woocommerce_before_main_content` (25), `woocommerce_show_page_title` (20) |
| "Filtri" button for the desktop drawer | `woocommerce_before_shop_loop` (25), plus `theme-bridge` |
| Filters' AJAX refresh | `ghb_filters_config`: `grid_selector` = `ul.products`, `count_selector` = `.woocommerce-result-count`, `pagination_selector` = `.woocommerce-pagination` |
| Filter params, rails' category pages, sold-out last | `woocommerce_product_query` (20, 20, 10) |

**Product page**

| Feature | Hook |
|---|---|
| "Venduto N volte negli ultimi 30 giorni" | `woocommerce_single_product_summary` (11) |
| Drop countdown + "Ricordamelo" | `woocommerce_single_product_summary` (25) |
| Delivery estimate | `woocommerce_single_product_summary` (31) |
| "Chiedi su WhatsApp", "Condividi" | `woocommerce_single_product_summary` (35). The WhatsApp message reads the variations form's `<select>`. |
| "Non trovi la tua taglia?" + "Acquista con fiducia" | `woocommerce_after_single_product_summary` (15) |
| "Altre colorway" | `woocommerce_after_single_product_summary` (19) |
| "Visti di recente" | `woocommerce_after_single_product_summary` (25) |

**Purchase, stock, admin, checkout**

| Feature | Hook |
|---|---|
| Drop lock (not for sale before release) | `woocommerce_is_purchasable`, `woocommerce_variation_is_purchasable` (20) |
| Drop fields on the product | `woocommerce_product_options_general_product_data`, `woocommerce_admin_process_product_object` |
| Size alerts, rail cache | `woocommerce_product_set_stock_status`, `woocommerce_variation_set_stock_status` |
| Social proof cache | `woocommerce_order_status_changed` |
| Bank-transfer drawer link | `woocommerce_review_order_after_payment` (classic checkout; `[gh_bank_details]` elsewhere) |

### Archive order: one `posts_clauses` chain

The product order on archives is built by three `posts_clauses` callbacks:

1. WooCommerce's sort (priority 10) replaces the ORDER BY.
2. The homepage-rail pins (20, `includes/hub-rails.php`) prepend to it.
3. Sold-out last (50, `includes/sold-out-last.php`) prepends to that.

**Never `remove_filter()` a callback from inside its own run.** If it's the
only callback at its priority and a lower priority also exists, `WP_Hook`
skips the next priority. The rail pins did this until 5.12.0 and silently
dropped sold-out last.

### On a block (FSE) theme

The archive grid becomes the Product Collection block
(`ul.wc-block-product-template > li.wc-block-product`). WooCommerce's
compatibility layer still fires the loop hooks.

- **Still works:** card add-to-cart, Quick View, archive hero, sold-out last,
  rail pins (the block inherits the main query, so the order carries over).
- **Needs porting:** everything that targets `ul.products`: the shop grid
  alignment and the filters' `grid_selector`. The filters panel also needs a
  new home, since a block theme has no sidebar.

## 3. Other dependencies

These survive a theme change.

| Dependency | Used by |
|---|---|
| **`pa_taglia`** (size attribute) | Card size picker, Quick View, filters, the "Taglia 42" line, swatch prices, size rail, size alerts. Filter: `ghb_atc_size_attribute`. Duplicate spellings ("36 2/3" / "36-2-3") are merged by `ghb_size_options()`. |
| **`pa_modello`, `pa_anno`, `pa_marca`, `pa_colorway`** | Filters (`ghb_filters_config`). "Altre colorway" groups by `pa_modello` (`ghb_colorways_taxonomy`). |
| **Brand taxonomies:** `product_brand`, `pwb-brand`, `pa_brand`, whichever exist | Rails, Store Hub. Size alerts add `pa_marca` (`ghb_size_alerts_brand_taxonomies`); the live-search card also checks `yith_product_brand`. |
| **Orders** | Social proof reads Processing / Completed orders with `wc_get_orders` (HPOS-safe). "Venduto N volte" counts them in WooCommerce's analytics tables (`wc_order_product_lookup`, `wc_order_stats`) and shows nothing if they're missing. |
| **Action Scheduler** (bundled with WooCommerce) | Drop release (cache purge, size alerts) and batched size-alert sends. Falls back to WP-Cron. |
| **WooCommerce mailer** | Size-alert emails use its template (Impostazioni → Email); plain `wp_mail` without it. |
| **Relevanssi Live Ajax Search** | The live-search modal. It stands down if the plugin is missing. |
| **Google Maps** | Map Embed, Contact Info and "Vieni in negozio" load the embed only when the visitor taps (`gh_map_facade`), so no Google request before then. |
| **Page-cache plugins** | After a Store Hub write and at a drop's release, the plugin purges SiteGround Optimizer, WP Rocket, LiteSpeed, W3 Total Cache or WP Super Cache (`ghb_purge_page_caches`, `includes/cache.php`). A CDN can hook `ghb_page_caches_purged`. |

## Rules for new code

1. Write against WooCommerce's hooks and `ul.products` markup, and the
   plugin's `--gh-*` design tokens. Write every radius as
   `calc(N * var(--gh-round))` so the shape switch reaches it.
2. Code that needs Shoptimizer's or CommerceKit's markup gets:
   - its own module key;
   - a `THEME-SPECIFIC` docblock;
   - an early `return` on `ghb_theme_module('<key>')`, asked **from a hook,
     never when the file loads** (the Customizer's theme preview switches
     theme after plugins load);
   - a row in section 1 of this file.
3. Adding to the archive order? Join the `posts_clauses` chain above at a
   priority that says where you belong, and never remove your own callback
   mid-run.

## Changing theme: checklist

**Classic theme**

- [ ] The section 1 modules turn off by themselves; nothing to switch off.
- [ ] Corner shape on the new theme's cards and buttons: write a bridge for
      it (new module key, its own stylesheet), modelled on `theme-bridge.css`.
- [ ] Desktop filters drawer, if wanted: redo its CSS for the new theme's
      sidebar markup.
- [ ] Mobile menu: use the new theme's, or write a new module.
- [ ] Swatches: keep CommerceKit and force `swatch-prices` /
      `swatch-sale-badges` on, or move to another swatch plugin (new module).
- [ ] Put `[gh_filters]` in the new theme's shop sidebar.
- [ ] Check the filter selectors against the theme's shop markup
      (themes usually keep WooCommerce's).

**Block theme:** everything above, plus

- [ ] Port the shop grid alignment and the filters' `grid_selector` to
      `ul.wc-block-product-template`.
- [ ] Put the filters panel in the archive template.
