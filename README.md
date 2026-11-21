# Golden Hive Blocks

The WordPress plugin behind several WooCommerce stores for sneakers and
streetwear. It does two jobs:

1. **29 Gutenberg blocks** for a store's pages (homepage, Chi siamo, guides).
2. **The store's WooCommerce interface**: product cards, Quick View, filters,
   live search, product rails, the product page additions, drops, size alerts,
   social proof, and the REST bridge the Store Hub app edits the homepage
   through.

**Nothing about any one store is written in the code.** Each store sets its
details, its look and its listings in the Customizer (Aspetto → Personalizza
→ Golden Hive, see [Settings per store](#settings-per-store)); every default
is neutral.

Requires WordPress 6.4+, PHP 8.0+ and WooCommerce. The stores run the
Shoptimizer theme with CommerceKit. Only four small modules depend on them
(see [THEME-COUPLING.md](THEME-COUPLING.md)); everything else runs on any
classic WooCommerce theme.

## Repository map

| Path | What it is |
|---|---|
| `golden-hive-blocks.php` | Plugin header, version, block registration, global assets. Requires every file in `includes/`. |
| `blocks/<name>/` | One block each: `block.json`, `render.php` (server-rendered), `editor.js` + `editor.asset.php` (editor UI). |
| `blocks/shared/editor-utils.js` | `window.ghEditorUtils`, the helpers every block's editor uses (list operations, media fields, fields that follow the store's details). |
| `includes/*.php` | The WooCommerce features, one file per feature. |
| `includes/*-core.php` | Plain PHP with no WordPress calls: the decisions a feature makes, unit-tested in `tests/`. |
| `includes/customizer.php` | The Customizer's Golden Hive panel and its four sections, the list and checklist controls (`customizer-controls.php`, `js/customizer-controls.js`), the cache purge after a save, the update notice. Each feature file adds its own settings. |
| `*.css` (root), `js/*.js` | Front-end sources. `npm run build` writes the `.min` siblings, which are served when present. |
| `js/motion.js` | Scroll motion: how sections arrive as the page scrolls (see [Motion](#motion)). |
| `js/animations.js` | The blocks' behaviour: modals, FAQ, hero carousel, countdowns, newsletter, the social-proof toast, the remembered size. |
| `templates/search-results.php` | Result card for the Relevanssi live-search modal. |
| `docs/pages.md` | How to build a store page as block markup: rules, product rails, motion, skeletons. The pages themselves live in WordPress, not here. |
| `docs/blocks.md` | Every block's attributes, generated from the code (`php tools/block-reference.php`). |
| `tools/check-page.php` | Checks a page's block markup before it goes to the site. |
| `tests/` | Framework-free tests (PHP, Node, and one browser test). `tests/wp/` holds copies of WordPress's block parser; `tests/fixtures/` a homepage's markup (store details replaced by placeholders), a smaller generic page, and the motion test page. |
| `.github/workflows/tests.yml` | CI: runs on every push (see [Development](#development)). |

## What the plugin does

### Blocks

All are in the **Golden Hive** inserter category and are server-rendered.

- **Heroes and sections:** Hero Carousel, Hero Split, Hero Video, About Hero,
  Parallax Section, CTA Banner, CTA Image Split.
- **Catalogue:** Product Spotlight, Category Slider, Brand Marquee.
- **Drops:** Drop Countdown (can follow a product's release date), Drop
  Calendar (lists coming drops).
- **Trust:** Authenticity Guarantee, Legit Check, Trust Badges, Social Proof
  (latest real orders), Trustpilot Widget, FAQ Schema (with FAQ structured
  data).
- **Store and contact:** Store Hours, Vieni in negozio (store visit), Contact
  Info, Map Embed, WhatsApp Button, Social Buttons, Instagram Feed.
- **Conversion:** Newsletter (with size alerts), Promo Modal, Size Chart Modal.
- **Utility:** Shortcode Wrapper (puts any shortcode in a block).

### Shortcodes

| Shortcode | What it shows | File |
|---|---|---|
| `[gh_product_rail]` | A product rail (scroll-snap carousel): by category, brand, tag, IDs or type, with `pin` / `exclude` / `fallback` ordering | `includes/product-rail.php` |
| `[carousel_section]`, `[product_carousel]`, `[bestsellers]`, `[new_arrivals]`, `[on_sale]`, `[featured_products]` | Older names, kept so existing pages still work; all render the product rail | `includes/product-query.php` |
| `[gh_size_rail]` | "Nella tua taglia": products in the size this browser remembers | `includes/size-rail.php` |
| `[gh_filters]` (older name `[bfl_filters]`, still in the shop sidebar) | The shop filters panel (`mode="inline"` for a sidebar) | `includes/filters.php` |
| `[gh_bank_details]` | The bank-transfer details drawer | `includes/bank-details.php` |

### Store interface

| Feature | File | Notes |
|---|---|---|
| The store's details: address, presentation, phone, WhatsApp, email, P.IVA, socials, hours, closures, map, trust badges | `store.php`, `store-core.php` | Blocks use them for any attribute a page leaves out (see [Settings per store](#settings-per-store)). |
| Card add-to-cart with inline size picker, "Esaurito" | `add-to-cart.php`, `quick-add.php` | Replaces WooCommerce's loop button. Cart writes go through WooCommerce's own `wc-ajax=add_to_cart`. |
| Quick View | `quick-view.php` | Its data comes from `product-ajax.php` (with Quick Add's). |
| The product query every rail uses | `product-query.php` | Published, catalogue-visible, sold-out hidden when the store hides them. |
| Card says "Taglia 42 · 180 €" when the shop is filtered by size | `size-match.php` | Quick View / Quick Add open on that size. |
| Shop filters (price, category, availability, attributes) | `filters.php`, `filters-core.php` | Which facets, in what order and under what title is the store's choice; every attribute it has until it chooses. Follows whatever collection is being viewed; every filtered URL is also a plain page load. |
| Sold-out products last | `sold-out-last.php` | Done in SQL, so they fill the last pages. |
| Shop grid alignment | `shop-grid.php` | |
| Archive hero (category image, parallax) | `archive-hero.php` | Pure CSS, no JavaScript. On by default, with a switch. |
| Live search modal | `live-search.php` | UI only; needs Relevanssi Live Ajax Search. |
| Product rails | `product-rail.php`, `product-rail-core.php`, `js/product-rail.js` | Cards use WooCommerce's own template, so Quick View and add-to-cart appear on them. Layout and number of products the shortcode leaves out are the store's. Arrow clicks are handled once for the whole page, so a rail copied in by another script still works (`tests/rail-test.js`). |
| Product page: WhatsApp, share, delivery estimate, "Non trovi la tua taglia?" band with "Acquista con fiducia", "Altre colorway", "Visti di recente" | `product-page.php`, `product-page-core.php` | "Acquista con fiducia" shows only the tiles the store writes; "Altre colorway" groups by the attribute it picks. |
| "Venduto N volte negli ultimi 30 giorni", recent-orders toast | `social-proof.php` | Shows the product and city, never a name, email or total. |
| Drops: release date, purchase lock, countdown, "Ricordamelo" (.ics / Google Calendar) | `drops.php` | At release: cache purge and size alerts. |
| Size alerts ("È arrivato un paio nella tua taglia") | `size-alerts.php` | Double opt-in, digests in batches, at most one email per subscriber every 12 h. |
| Newsletter signup | `newsletter.php` | Stores subscribers as a private post type (admin: Iscritti newsletter). Fires `ghb_newsletter_subscribed` for an email service. |
| Bank-transfer drawer at checkout | `bank-details.php` | Reads WooCommerce's BACS accounts. |
| Preload and speculation rules | `preload-speculation.php` | Admin: Strumenti → GH Preload. |
| Design tokens and switches (colours, fonts, title style, opening titles' size, space beside product sections, corner shape, desktop filters drawer) | `design.php`, `design-core.php` | Printed as `html:root` custom properties on the site and in the editor. Defaults are neutral; stores that ran the plugin before 5.18 got their old look saved as settings once. |
| Shoptimizer / CommerceKit modules | `theme-compat.php` and the four modules it gates | See THEME-COUPLING.md. |
| Page-cache purge | `cache.php` | SiteGround, WP Rocket, LiteSpeed, W3TC, WP Super Cache. |

### Store Hub bridge

The Store Hub app's **Vetrina** edits the homepage from a phone. The homepage
stays the single source of truth: each rail's content and order live in its
own shortcode (`pin`, `exclude`, `fallback`). The REST API is in namespace
`wc-gh/v1`; WooCommerce authenticates it with the Hub's existing API keys, and
every route needs `manage_woocommerce`. "Write" routes accept POST, PUT and PATCH,
and refuse rather than guess when the page changed since the app read it.

| Route | Method | Does |
|---|---|---|
| `/capabilities` | GET | Plugin and API version, rail types, editable fields, limits, the store's rail defaults (`rail_defaults`, 5.20.0) |
| `/homepage` | GET | The homepage's sections and rails |
| `/rail` | GET | One rail's products in store order, plus its automatic order so the app can preview pins |
| `/products` | GET | Product cards for a list of IDs |
| `/homepage/block` | write | Edit one section: a rail's pin / exclude / fallback / limit / source, or a block's fields |
| `/homepage/layout` | write | Add, remove, reorder or switch off sections |
| `/homepage/history` | GET | One rail's earlier states, from the page's revisions |

The decisions live in `includes/hub-rails-core.php` (tested). The WordPress
side is three files: `hub-rails.php` (what the storefront runs: the rail
query and category pages following their homepage rail), `hub-page.php`
(reading and saving the homepage, its fields, product cards) and
`hub-rest.php` (the routes). **The app mirrors the core's rules**, so
changing them changes what the app previews. Since 5.20.0 a rail's layout
attributes left out are the store's rail settings (`rail_defaults`, feature
`rail-defaults`), and a block field left out reads as the store's detail
(feature `store-defaults`: the WhatsApp button's message).

## Where the shop owner changes things

Everything of the plugin's is in one Customizer panel, **Aspetto →
Personalizza → Golden Hive**, four sections:

| Section | Settings |
|---|---|
| **Negozio** | The store's details: Indirizzo, Presentazione del negozio, Telefono, Numero WhatsApp, Messaggio WhatsApp, Email, Partita IVA, Ragione sociale, the five social profiles, Orari (Monday to Sunday), Chiuso nei giorni festivi, Giorni di chiusura (also skipped by the delivery estimate), Nota sugli orari, Mappa, Badge di fiducia |
| **Stile** | The design tokens: Colore principale (buttons, prices, eyebrows, selected sizes, filters), Colore scuro, Colore chiaro, Font dei titoli, Font del testo, Titoli delle sezioni (as typed / uppercase), Spessore dei titoli, Titoli in apertura (the hero titles' size), Spazio ai lati delle sezioni prodotto; corner shape |
| **Prodotti e filtri** | Product rails when the shortcode doesn't say: products per row on desktop, tablet and phone, products per section, photo format, whole or cropped photos, second photo on hover, arrows (below the row or on the sides). The shop filters: which, in what order, under what title; categories never offered; desktop filters (sidebar or drawer); the archive hero |
| **Pagina prodotto** | "Non trovi la tua taglia?" and "Acquista con fiducia" (each on or off) and the tiles of the latter; "Altre colorway" (the attribute naming the model); "Venduto N volte"; "Visti di recente"; delivery estimate (off by default), cut-off time, min/max days |

Elsewhere:

| Place | Settings |
|---|---|
| Aspetto → Personalizza → WooCommerce → Catalogo prodotti | "Prodotti esauriti in fondo" |
| Prodotto → Dati prodotto → Generale | Drop date and "Blocca l'acquisto fino al drop" |
| WooCommerce → Impostazioni → Pagamenti → Bonifico bancario | IBAN shown in the checkout drawer |
| WooCommerce → Impostazioni → Email | Template used by size-alert emails |
| Strumenti → GH Preload | Preload, prerender and prefetch URLs |
| Iscritti newsletter (admin menu) | Subscribers and their size alerts |

Saving any Golden Hive setting empties the page caches, so it shows at once.

## Settings per store

**A block attribute that follows the store has no default in its
`block.json`.** Left out of a page, it takes the store's detail
(`ghb_store_attr()`); written, it is that page's own value, and written
empty (`"vat":""`) it hides that detail on that page. These follow the
store (`GHB_STORE_BLOCK_FIELDS` in `includes/store-core.php`;
[docs/blocks.md](docs/blocks.md) shows their default as "Negozio → …"):

| Block | Attributes |
|---|---|
| Contact Info | subtitle (Presentazione), address, vat, phone, email, mapEmbed |
| Vieni in negozio | subtitle, address, vat, phone, hours, note, closedOnHolidays, closedDates, mapEmbed (WhatsApp is always the store's number) |
| Orari | hours, note, closedOnHolidays, closedDates |
| Map Embed | embedCode, address (the store's only with the store's map) |
| WhatsApp Button | phoneNumber, message |
| Social Buttons | instagram, facebook, tiktok, youtube, twitter |
| Instagram Feed | instagramUrl, buttonText ("@name" from the Instagram profile) |
| Trust Badges | badges |

The block editor shows the store's values as placeholders and offers "Usa
i dati del negozio". The rails' layout and number of products, the filters,
"Acquista con fiducia" and "Altre colorway" are store settings too (above).

**Updating from 5.19 or earlier** copies nothing from the code. Once, from
each store's own data (`ghb_store_migrate`, `ghb_filters_migrate`,
`ghb_pp_migrate`):

- a store that never saved a WhatsApp number gets the one its own WhatsApp
  buttons all use; with none, no WhatsApp link shows until it sets one;
- its filters stay as they were (the pre-5.20 list, limited to the
  attributes it has, labels included);
- "Altre colorway" keeps grouping by `pa_modello` where it did;
- the band's one switch becomes two with its value;
- an admin notice lists the blocks that left out a default the plugin no
  longer has (another store's address, hours or map; sample texts), with
  the fields to fill in, and says "Acquista con fiducia" needs the store's
  own tiles: until then it isn't shown.

## How it works on cached pages

The store's pages are page-cached, so anything that depends on the visitor or
on the current time is never rendered into the HTML. The page ships a frame,
and the browser fills it in:

- **In the browser:** countdowns, opening hours, delivery estimate, "2 ore
  fa" (`js/live-blocks.js`, `js/calendar.js`, `js/product-page.js`). Pure
  helpers are exported for Node tests.
- **Over `admin-ajax.php`** (which caches skip): the size rail, recently
  viewed, the order feed, Quick View data.
- **Remembered in the browser:** the shopper's size (`SizeMemory` in
  `js/animations.js`) and recently viewed products (localStorage).

### Public AJAX endpoints

Anyone can call these, so each serves only public catalogue data or checks a
token. Keep it that way when adding one.

| Action | Guard |
|---|---|
| `ghb_quick_view`, `ghb_get_variations` | Published, non-password products only (`ghb_product_is_public`) |
| `ghb_recent_rail`, `ghb_size_rail` | Rail query: published and catalogue-visible only |
| `ghb_social_proof` | Product, city and time only; days and count capped |
| `ghb_filter` | Catalogue query |
| `ghb_ls_warm` | Returns nothing; warms PHP before the first live search |
| `ghb_newsletter_subscribe` | Nonce, 5 attempts per IP per hour |
| `ghb_drop_ics` | A published product's drop, or a signed event |
| `ghb_alerts` | Subscriber token; only a POST acts |

## Motion

`js/motion.js` decides how sections arrive as the page scrolls. Its moves and
rules come from the Alpacode motion catalog
([alpacode-motion](https://github.com/FrancescoCorbosiero/alpacode-motion) at
`7b1a203`: `reveal`, `reveal-stagger`, `split-lines`, `clip`, and
`parallax-cover` as CSS). They're rebuilt on IntersectionObserver and the Web
Animations API instead of GSAP: the same curves and timings, about 2 KB
gzipped instead of about 50 KB of libraries, and no smooth-scroll library
taking over the shop's scrolling.

| Hook | Move | Used on |
|---|---|---|
| `data-gh-reveal` (`up`, `down`, `left`, `right`, `scale`) | The block rises into place; blocks entering together land as a staggered batch | Most block content |
| `data-gh-reveal="stagger"` (+ `data-gh-stagger="<selector>"`) | Its items land in a cascade with a little bounce | Product rails (their cards) |
| `data-gh-split="lines"` | A heading's lines rise out of their masks | Every block title except the hero carousel's |
| `data-gh-image-reveal` | A photo opens from a clip, its image settling from a slight zoom | CTA Image, About Hero, Product Spotlight |
| `data-gh-drift` | A cover photo drifts in its frame while it crosses the screen (a CSS scroll-driven animation, no script) | CTA Image, About Hero |

The rules, which keep it fast and safe on a cached store:

- **Nothing is hidden by CSS.** `motion.js` hides what it animates, at init,
  and only below the first screen. Without JavaScript, or if the script
  fails, the page is complete.
- **The first screen never moves**, so nothing waits on a script to be seen.
- **"Hidden" is a paused animation, not an inline style.** When a move ends
  the element is back under the stylesheet, so hover effects keep working.
- **A split heading keeps its exact height** (no layout shift), and is put
  back as plain text when its move ends.
- A jump (anchor, find-in-page) shows what it skipped at once; keyboard focus
  shows a waiting block at once; reduced motion turns all of it off.

`js/animations.js` keeps the hero titles' own character split
(`data-gh-split="chars"` / `"words"`) and the pointer effects
(`data-gh-magnetic`, `data-gh-mouse-parallax`).

## Development

```bash
npm install && npm run build   # after editing a root .css or js/*.js; commit the .min files too
```

Run every test (no WordPress needed):

```bash
for t in tests/*-test.php; do php "$t" || exit 1; done && for t in tests/*-test.js; do node "$t" || exit 1; done
```

`tests/motion-test.js` drives headless Chromium through Playwright, and skips
itself where Playwright isn't installed. **CI**
(`.github/workflows/tests.yml`) runs on every push: PHP syntax and tests on
PHP 8.0 and 8.4, a check that the committed `.min` files match a fresh
build, the Node tests, and the motion test in Chromium.

**Release checklist**

1. Bump `Version:` and `GOLDEN_HIVE_BLOCKS_VERSION` in
   `golden-hive-blocks.php` together. The version is the cache-buster for
   every asset.
2. Changed a block's `editor.js`? Bump `version` in its `editor.asset.php`.
3. Changed a root `.css` or `js/*.js`? `npm run build`, commit both files.
4. Run the tests.

## Building pages

Pages are written as block markup and checked before they go to the site:
[docs/pages.md](docs/pages.md) is the spec, [docs/blocks.md](docs/blocks.md)
the block reference, and

```bash
php tools/check-page.php page.html
```

reports what WordPress would drop or get wrong: attributes that aren't valid
JSON, unknown blocks or attributes, wrong types, rail attributes the rail
doesn't know, rails the Store Hub can't see, unknown motion values. It exits
1 on any error.

## Known debt

- **Italian code comments** are still in `style.css` (about 40 lines) and a
  few lines each in other stylesheets and scripts. New comments are English;
  translate the old ones when you touch them.
- **Big files:** `js/animations.js` (~1,450 lines, one module per block
  behaviour), `includes/filters.php` (~1,100) and `includes/hub-rest.php`
  (~680). Each is one feature, sectioned, and covered where it decides
  things, but they're the slowest to read.
