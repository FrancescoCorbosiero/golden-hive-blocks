# Building pages

How to write a store page as block markup: by hand, or by asking an LLM to
write it from this spec. Pages are the site's content and live in WordPress,
not in this repository. What lives here is how to build them:

- this file: the rules, the product rails, motion, and page skeletons;
- [blocks.md](blocks.md): every Golden Hive block and its attributes,
  generated from the code;
- `php tools/check-page.php page.html`: checks a page against both before it
  goes to the site.

## Workflow

1. **Get the page as it is.** In WordPress: open the page, ⋮ menu → *Code
   editor*, copy everything. (Or read it from the REST API.)
2. **Write the new version** following this file and [blocks.md](blocks.md).
3. **Check it:** `php tools/check-page.php page.html` must report 0 errors.
   Warnings are worth reading (another plugin's shortcode is fine).
4. **Publish it:** paste it into the code editor and update, or send it
   through the REST API (see [Publishing](#publishing)). WordPress keeps a
   revision of every save, so the previous version is one click away.
5. **Look at it** on a phone and a desktop, scrolling through once.

## Markup rules

A page is a list of blocks. Each block is an HTML comment with the block's
name and its attributes as JSON:

```html
<!-- wp:golden-hive/cta-banner {"title":"Saldi di fine stagione","buttonText":"Scopri","buttonUrl":"/saldi/"} /-->
```

- **Golden Hive blocks are drawn by the server.** Write each as one
  self-closing comment (`/-->`), with nothing inside it.
- **Attributes are JSON on one line.** Keys and text in double quotes;
  numbers and `true` / `false` bare, as [blocks.md](blocks.md) types them.
  Leave an attribute out to get its default.
- **A double quote inside a value is written `\u0022`.** A bare `"` ends the
  value, the JSON breaks, and WordPress silently drops *every* attribute of
  that block. The checker catches it. In shortcodes, use single quotes
  instead: `[gh_product_rail category='saldi']`.
- **Never write `--` inside a value**: it would end the comment.
- **HTML inside a value** (an FAQ answer) is fine: `"answer":"<p>Sì, entro 14
  giorni.</p>"`.
- **Images:** give the URL, and the media ID too when you know it
  (`imageUrl` + `imageUrlId`, `image` + `imageId`): with the ID the site
  serves resized copies (srcset) instead of the full file.
- **WordPress's own blocks** (paragraph, heading, table, columns, image…)
  save their HTML in the page, and the editor expects it to match exactly
  what the editor itself would write. If it doesn't, the page still shows
  fine, but opening it in the editor reports "unexpected content". Keep them
  simple, or use one `core/html` block for custom markup.
- **Nothing outside blocks.** Loose HTML becomes a "Classic" block.

## Anatomy of a page

- **The first screen** is one hero: `hero-carousel` (several campaigns),
  `hero-split` (one image + text) or `hero-video`. Scroll motion never
  touches the first screen, so it's painted at once. In a carousel, slide
  1's image is what the page waits for: keep it light.
- **Then sections, each with one job.** One strong move per section;
  neighbours shouldn't repeat (two `cta-banner` in a row read as one
  section broken in two).
- **Section titles animate by themselves**: every Golden Hive block title
  rises line by line, photos open from a clip, cover photos drift. Nothing
  to add.
- **Towards the end:** reassurance and contact (`faq-schema`,
  `store-visit`, `newsletter`).
- **Floating widgets last**, at most one of each per page:
  `social-buttons`, `social-proof`, `whatsapp-button`.

## House conventions

How the stores' pages are actually built. Follow these unless asked
otherwise:

- **Keep the store's own copy.** Rearrange sections freely, fix typos and
  accents, resolve contradictions (and say which version you kept), but
  don't rewrite claims or invent new ones.
- **The page's `<h1>` is hidden**: a heading block with the `gh-sr-only`
  class, first on the page, for search engines and screen readers. The
  visible titles are the blocks' own `<h2>`s.
- **Homepage order:** `hero-carousel` (the store's own campaigns) →
  `trust-badges` (`"variant":"carousel"`) → a 1600px group of category rails
  → `brand-marquee` (no title: `"title":""`) → a 1600px group of brand rails
  → one or two `cta-image` (alternate `"reverse":true`) → the authenticity
  banner → `faq-schema` → `social-buttons` + `whatsapp-button`.
- **Rails:** `columns='4' columns_tablet='3' columns_mobile='2'`,
  `limit='12'`, inside `{"layout":{"type":"constrained","contentSize":"1600px"}}`
  groups. Category rails carry an eyebrow; brand rails don't, and their
  button reads "Esplora <Brand>".
- **Design comes from the store, not the page.** Colours, fonts and title
  style are the store's design tokens (Customizer → Golden Hive — Stile).
  Never put a colour, a font or a `<style>` in a page.
- **Blocks whose defaults belong to another store.** Contact Info (address,
  P.IVA), About Hero (title, text) and Trustpilot Widget (a fixed rating)
  have defaults written for the first store. Set every field you use and
  blank the rest (`"address":""`), and use the store's official TrustBox
  embed in an `html` block rather than the Trustpilot block.

The authenticity banner has no block yet; it's this `html` block (the
styles are the plugin's):

```html
<!-- wp:html -->
<section class="gh-block gh-auth-banner" data-gh-reveal="up">
    <div class="gh-auth-banner__bg">
        <img src="https://example.com/sfondo.jpg" alt="" loading="lazy" decoding="async">
    </div>
    <div class="gh-auth-banner__overlay"></div>
    <div class="gh-auth-banner__container">
        <div class="gh-auth-banner__icon-ring">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        </div>
        <h2 class="gh-auth-banner__title">100% Autentico, 100% Garantito</h2>
        <p class="gh-auth-banner__text">Una o due frasi su come il negozio verifica i prodotti.</p>
        <div class="gh-auth-banner__badges">
            <span class="gh-auth-banner__badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg> GARANZIA</span>
        </div>
    </div>
</section>
<!-- /wp:html -->
```

## Product rails

A rail is a `shortcode-wrapper` block holding `[gh_product_rail]`:

```html
<!-- wp:golden-hive/shortcode-wrapper {"eyebrow":"Appena arrivate","title":"NUOVI ARRIVI","shortcode":"[gh_product_rail category='nuovi-arrivi' limit='15' columns='5' columns_tablet='3']","buttonText":"Vedi tutto","buttonUrl":"/categoria/nuovi-arrivi/"} /-->
```

| Attribute | Values |
|---|---|
| `category`, `brand`, `tag` | Slugs, not names. Use **one** of the three. |
| `type` | `recent` (default), `featured`, `best_selling`, `sale`, `top_rated` |
| `ids` | Product IDs, comma-separated, in their own order |
| `pin`, `exclude`, `fallback` | Written by the Store Hub: products first, products never shown, order of the rest (`menu_order`, `date`, `popularity`, `price`, `price-desc`, `rating`) |
| `limit` | How many products (default 12) |
| `columns`, `columns_tablet`, `columns_mobile` | Cards per view, 1–8 |
| `ratio` | `square`, `portrait`, `tall`, `landscape`, `wide`, or `N/N` |
| `fit` | `contain` (default, whole photo) or `cover` (cropped) |

**For the Store Hub** (its *Vetrina* edits the homepage's rails from a phone):

- It only sees rails in a `shortcode-wrapper`. A rail in a plain shortcode
  or HTML block works on the site but can't be edited from the app.
- A rail showing exactly one category, brand or tag also orders that
  category's own page (its pins come first there). Two rails on the same
  term: the first one wins.
- Don't put the very same rail on the page twice: the app can't tell them
  apart and refuses to move them.
- The app edits the wrapper's `eyebrow`, `title`, `buttonText`,
  `buttonUrl`, `backgroundColor` (`white`, `gray`, `black`) and `hidden`.

Rails sit well inside a wide group:

```html
<!-- wp:group {"layout":{"type":"constrained","contentSize":"1600px"}} -->
<div class="wp-block-group"><!-- wp:golden-hive/shortcode-wrapper {"title":"IN TENDENZA","shortcode":"[gh_product_rail category='in-tendenza' limit='12']"} /-->

<!-- wp:golden-hive/shortcode-wrapper {"title":"SALDI","shortcode":"[gh_product_rail category='saldi' limit='18' columns='4']"} /--></div>
<!-- /wp:group -->
```

**"Nella tua taglia"** is `[gh_size_rail]` (attributes `title`, `text`,
`limit`, `columns`) in a `shortcode-wrapper`. It isn't a Store Hub rail: it
shows each visitor their own size.

## Motion in raw HTML

Golden Hive blocks bring their own motion. In a `core/html` block, add it
with these attributes (the README's Motion section has the details):

| Attribute | Move |
|---|---|
| `data-gh-reveal` (`up`, `down`, `left`, `right`, `scale`) | Rises into place |
| `data-gh-reveal="stagger"` + `data-gh-stagger="<selector>"` | Its items land in a cascade |
| `data-gh-split="lines"` | A plain-text heading rises line by line |
| `data-gh-image-reveal` | A photo (the element around an `<img>`) opens from a clip |
| `data-gh-drift` | A cover photo drifts in its frame |

Never hide anything with your own CSS waiting for a script, and don't put
motion on the first screen.

## Page skeletons

Placeholders in capitals. Each skeleton passes the checker
(`tests/page-check-test.php` checks every `html` block in this file).

### Homepage

```html
<!-- wp:heading {"level":1,"className":"gh-sr-only"} -->
<h1 class="wp-block-heading gh-sr-only">NOME DEL NEGOZIO — COSA VENDE</h1>
<!-- /wp:heading -->

<!-- wp:golden-hive/hero-carousel {"slides":[{"image":"https://example.com/slide-1.jpg","objectPosition":"center center","eyebrow":"Il tuo store di fiducia","title":"NOME DEL NEGOZIO","subtitle":"Una riga che dice perché.","buttonText":"Esplora","buttonUrl":"/categoria/selezione/"},{"image":"https://example.com/slide-2.jpg","eyebrow":"CAMPAGNA","title":"BRAND O MODELLO","buttonText":"Scopri","buttonUrl":"/marchio/brand/"}]} /-->

<!-- wp:golden-hive/trust-badges {"variant":"carousel"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1600px"}} -->
<div class="wp-block-group"><!-- wp:golden-hive/shortcode-wrapper {"eyebrow":"Selezionati da noi","title":"SELEZIONE","shortcode":"[gh_product_rail category='selezione' limit='12' columns='4' columns_tablet='3' columns_mobile='2']","buttonText":"Vedi tutti","buttonUrl":"/categoria/selezione/"} /-->

<!-- wp:golden-hive/shortcode-wrapper {"eyebrow":"Release aggiornate","title":"NUOVI ARRIVI","shortcode":"[gh_product_rail type='recent' limit='12' columns='4' columns_tablet='3' columns_mobile='2']","buttonText":"Vedi tutti","buttonUrl":"/negozio/"} /--></div>
<!-- /wp:group -->

<!-- wp:golden-hive/brand-marquee {"title":"","brands":[{"name":"BRAND","logo":"https://example.com/logo.png","url":"/marchio/brand/"}],"speed":40} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1600px"}} -->
<div class="wp-block-group"><!-- wp:golden-hive/shortcode-wrapper {"title":"BRAND","shortcode":"[gh_product_rail brand='brand' type='best_selling' limit='12' columns='4' columns_tablet='3' columns_mobile='2']","buttonText":"Esplora Brand","buttonUrl":"/marchio/brand/"} /--></div>
<!-- /wp:group -->

<!-- wp:golden-hive/cta-image {"imageUrl":"https://example.com/cta-1.jpg","eyebrow":"Selezionati da noi","title":"TITOLO","text":"Una frase.","buttonText":"Esplora","buttonUrl":"/categoria/slug/"} /-->

<!-- wp:golden-hive/cta-image {"imageUrl":"https://example.com/cta-2.jpg","eyebrow":"ETICHETTA","title":"TITOLO","text":"Una frase.","buttonText":"Acquista ora","buttonUrl":"/categoria/slug/","reverse":true} /-->

<!-- wp:golden-hive/faq-schema {"subtitle":"Trova le risposte alle domande più comuni","items":[{"question":"DOMANDA?","answer":"<p>RISPOSTA.</p>"}]} /-->

<!-- wp:golden-hive/social-buttons {"instagram":"https://www.instagram.com/example"} /-->

<!-- wp:golden-hive/whatsapp-button {"phoneNumber":"390000000000","iconOnly":true} /-->
```

### About

```html
<!-- wp:golden-hive/hero-video {"mediaType":"image","imageUrl":"https://example.com/negozio.jpg","title":"CHI SIAMO","subtitle":"Una riga sulla storia del negozio."} /-->

<!-- wp:golden-hive/about-hero {"eyebrow":"La storia","title":"TITOLO","text":"Il racconto, in due o tre frasi.","imageUrl":"https://example.com/storia.jpg","values":[{"title":"VALORE","text":"Cosa significa per chi compra."}]} /-->

<!-- wp:golden-hive/authenticity-guarantee /-->

<!-- wp:golden-hive/legit-check /-->

<!-- wp:golden-hive/store-visit /-->

<!-- wp:golden-hive/cta-banner {"title":"INVITO ALL'AZIONE","buttonText":"Vai al negozio","buttonUrl":"/negozio/"} /-->
```

### Contacts

```html
<!-- wp:golden-hive/hero-video {"mediaType":"image","imageUrl":"https://example.com/contatti.jpg","title":"CONTATTI"} /-->

<!-- wp:golden-hive/contact-info {"title":"Scrivici","subtitle":"","address":"VIA, CITTÀ","vat":"P.IVA 00000000000","phone":"+39 000 000 0000","email":"info@example.com","mapEmbed":""} /-->

<!-- wp:golden-hive/store-hours /-->

<!-- wp:golden-hive/social-buttons {"instagram":"https://www.instagram.com/example"} /-->
```

### A guide page (WordPress blocks only)

```html
<!-- wp:heading -->
<h2 class="wp-block-heading">TITOLO DELLA GUIDA</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Una frase che spiega come leggere la tabella.</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>EU</th><th>US</th><th>CM</th></tr></thead><tbody><tr><td>42</td><td>8.5</td><td>26.5</td></tr></tbody></table></figure>
<!-- /wp:table -->
```

## Publishing

Pasting into the code editor works for any page. To skip the editor
entirely, WordPress's REST API takes the same markup:

```
POST https://<site>/wp-json/wp/v2/pages/<page id>
Authorization: Basic <user>:<application password>
{"content": "<the whole page markup>"}
```

An Application Password is made in WordPress under *Utenti → Profilo →
Password applicazione*; it can be revoked at any time and never is the
account's own password. Every update keeps a revision. Check the page
first: the API stores whatever it's sent.
