# Theme coupling

Golden Hive Blocks is built on **WordPress + WooCommerce** and runs on any
theme. The store runs **Shoptimizer** (CommerceGurus) with **CommerceKit**, the
companion plugin it ships with. Only the modules in section 1 are written
against them, and each one switches on through a single function,
`ghb_theme_module()` in `includes/theme-compat.php`. On any other theme they
stay off.

Last checked: plugin 5.12.0, WooCommerce 9.9.5, WordPress 6.8.

## 1. Shoptimizer / CommerceKit only

| Module key | Files | Written against | On another theme |
|---|---|---|---|
| `theme-bridge` | `theme-bridge.css`, `includes/design.php` | Shoptimizer's product cards, sale badges, buttons, fields and pagination; its `#secondary` sidebar and `#primary` / `.content-area` | Off. The shape switch still re-shapes everything the plugin draws, but the theme's own surfaces keep their shape. The "Filtri su desktop" option leaves the Customizer, because the drawer *is* this stylesheet. |
| `mobile-nav` | `includes/mobile-nav.php`, `mobile-nav.css`, `js/mobile-nav.js` | Shoptimizer's menu: `.main-navigation`, `.menu-item-has-children`, `.caret`, `.sub-menu-wrapper`, `.cg-open` | Off |
| `swatch-prices` | `includes/variation-swatch-prices.php`, `variation-swatch-prices.css`, `js/variation-swatch-prices.js` | CommerceKit's swatches: `.cgkit-attribute-swatches`, `.cgkit-swatch` | Off |
| `swatch-sale-badges` | `includes/swatch-sale-badges.php` | CommerceKit's swatch buttons: `[data-attribute] button[data-attribute-value]` | Off |

One harmless leftover: while the archive hero is on, `archive-hero.css` hides
`.shoptimizer-category-banner` (the theme's second h1). On other themes that
selector matches nothing.

To force a module either way, for example if CommerceKit is kept on another
theme:

```php
add_filter('ghb_theme_module', function ($on, $module) {
    return in_array($module, array('swatch-prices', 'swatch-sale-badges'), true) ? true : $on;
}, 10, 2);
```

`add_filter('ghb_design_theme_bridge', '__return_false')` still turns the
bridge off on Shoptimizer.

## 2. Classic WooCommerce markup

These features hook into WooCommerce's own templates, not the theme's, so they
work on any classic WooCommerce theme (Storefront, Kadence, GeneratePress,
Astra, Blocksy…):

| Feature | Relies on |
|---|---|
| Loop add-to-cart, size picker, "Esaurito" | `woocommerce_after_shop_loop_item` |
| Quick View button | `woocommerce_before_shop_loop_item_title` |
| Archive hero | `woocommerce_before_main_content`, `woocommerce_show_page_title` |
| "Filtri" button (desktop drawer) | `woocommerce_before_shop_loop`, plus `theme-bridge` (section 1) |
| Shop grid alignment | `ul.products li.product` (`shop-grid.css`, `js/shop-grid.js`) |
| Filters, AJAX refresh | `ghb_filters_config`: `grid_selector` `ul.products`, `count_selector` `.woocommerce-result-count`, `pagination_selector` `.woocommerce-pagination` |
| Product rails | `wc_get_template_part('content', 'product')` inside the rail's own markup |
| Sold-out last, rail pins on archives | WooCommerce's main product query (`woocommerce_product_query`, then `posts_clauses`) |

### On a block (FSE) theme

Checked with Twenty Twenty-Five and WooCommerce 9.9.5. The archive grid is the
Product Collection block (`ul.wc-block-product-template > li.wc-block-product`),
and WooCommerce's compatibility layer still fires the loop hooks.

- **Still works:** the loop add-to-cart button, the Quick View button, the
  archive hero, sold-out last and the rail pins. The block inherits the main
  query, so the ordering carries over.
- **Needs porting:** anything that targets `ul.products`, i.e. the shop grid
  alignment and the filters' AJAX refresh (`grid_selector`). The filters panel
  also needs a new place, because it lives in the sidebar and a block theme
  has none. The section 1 modules are off.

## 3. Store data and other plugins

These survive a theme change.

- **Attribute taxonomies.** Sizes are `pa_taglia`: the loop size picker
  (filter `ghb_atc_size_attribute`), Quick View, the filters and the swatch
  prices. The filters also use `pa_modello`, `pa_anno`, `pa_marca` and
  `pa_colorway` (`ghb_filters_config`).
- **Brand taxonomies.** `product_brand`, `pwb-brand` and `pa_brand`, whichever
  are registered (rails, Store Hub).
- **Relevanssi Live Ajax Search.** Powers the live-search modal, which stands
  down without it.
- **Cache plugins.** After a Store Hub write, the plugin purges SiteGround
  Optimizer, WP Rocket, LiteSpeed, W3 Total Cache or WP Super Cache,
  whichever is present.
- **"Advanced Filters" Code Snippet.** While it is active, the plugin's
  filters stand down and show an admin notice.

## Rules for new code

1. Write against WooCommerce (its hooks and `ul.products` markup) and the
   plugin's own `--gh-*` tokens. Write radii as `calc(N * var(--gh-round))`.
2. Code that needs a theme's or CommerceKit's markup gets:
   - its own module;
   - a `THEME-SPECIFIC` docblock;
   - an early `return` on `ghb_theme_module('<key>')`, asked from a hook and
     never when the file loads (the Customizer's theme preview switches theme
     after plugins load);
   - a row in section 1.
3. Product archive order is a chain of `posts_clauses` callbacks:
   - WooCommerce's sort (priority 10) replaces the ORDER BY;
   - the homepage-rail pins (20) and sold-out last (50) prepend to it.

   Never `remove_filter()` a callback from inside its own run. When it is the
   only callback at its priority and a lower priority also exists,
   `WP_Hook` then skips the next priority. The rail pins did this until
   5.12.0 and silently dropped whatever came after them.

## Changing theme: checklist

**Classic theme**

- [ ] Nothing to switch off: the section 1 modules turn off by themselves.
- [ ] Shape switch on the new theme's cards and buttons: write a bridge for it
      (new module key, own stylesheet), modelled on `theme-bridge.css`.
- [ ] Desktop filters drawer, if wanted: its CSS targets the sidebar, so redo
      it for the new theme's sidebar markup.
- [ ] Mobile menu: the new theme's own, or a new module.
- [ ] Swatches: keep CommerceKit and force `swatch-prices` /
      `swatch-sale-badges` on, or move to another swatch plugin (new module).
- [ ] Place `[bfl_filters]` in the new theme's shop sidebar.
- [ ] Check the filter selectors against the theme's shop markup (WooCommerce
      defaults are usually left alone).

**Block theme**

- [ ] Everything above.
- [ ] Port the shop grid alignment and the filters' `grid_selector` to
      `ul.wc-block-product-template`.
- [ ] Put the filters panel in the archive template.
