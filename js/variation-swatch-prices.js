/**
 * Golden Hive Blocks — Per-variation prices on size swatches.
 *
 * THEME-SPECIFIC (Shoptimizer + CommerceKit). See
 * includes/variation-swatch-prices.php for the full dependency notes. Reads
 * WooCommerce's own data-product_variations JSON off the variations form and
 * renders the matching price under each in-stock size swatch, greying out
 * out-of-stock sizes. Fails silently if the CommerceKit markup isn't present.
 *
 * Sold-out sizes stay clickable (greyed only): picking one is how
 * CommerceKit's Waitlist offers its form. They carry a short note in the
 * price slot instead (`ghbSwatchPrices.oosLabel`, filter
 * ghb_swatch_oos_label).
 */
(function () {
  var cfg = window.ghbSwatchPrices || {};

  // The size attribute (includes/size-match.php ghb_size_attribute()), read
  // explicitly: the size is not guaranteed to be the FIRST variation attribute.
  var SIZE_ATTR = 'attribute_' + (cfg.attribute || 'pa_taglia');
  var SWATCHES = '.cgkit-attribute-swatches[data-attribute="' + SIZE_ATTR + '"]';
  var OOS_LABEL = cfg.oosLabel || '';

  // The store's currency and language. Never round prices: 99,90 € must
  // display as 99,90 €, not 100 €; decimals are dropped only when ,00.
  var LOCALE = cfg.locale || 'it-IT';
  var CURRENCY = cfg.currency || 'EUR';
  var fmtFull, fmtInt;
  try {
    fmtFull = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: CURRENCY });
    fmtInt  = new Intl.NumberFormat(LOCALE, {
      style: 'currency', currency: CURRENCY,
      minimumFractionDigits: 0, maximumFractionDigits: 0
    });
  } catch (e) {
    fmtFull = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
    fmtInt  = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function formatPrice(a) {
    var n = Number(a);
    if (!isFinite(n)) return '';
    var isWhole = Math.round(n * 100) % 100 === 0;
    return (isWhole ? fmtInt : fmtFull).format(n);
  }

  function getMap(form) {
    var raw = form.getAttribute('data-product_variations');
    if (!raw || raw === 'false') return null;
    var vars;
    try { vars = JSON.parse(raw); } catch (e) { return null; }
    var map = {};
    vars.forEach(function (v) {
      var attrs = v.attributes || {};
      var val = Object.prototype.hasOwnProperty.call(attrs, SIZE_ATTR)
        ? attrs[SIZE_ATTR]
        : attrs[Object.keys(attrs)[0]];
      if (!val) return;
      map[val] = {
        price: v.display_price,
        inStock: v.is_in_stock && v.is_purchasable
      };
    });
    return map;
  }

  // Rebuild the button so the size label lives in its own span.
  // Only runs when the span is missing (first pass, or if CK re-renders).
  function normalize(btn) {
    if (btn.querySelector('.cgkit-swatch-size')) return;
    var cross = btn.querySelector('.cross');
    var label = btn.getAttribute('data-attribute-text') || btn.textContent.trim();
    btn.innerHTML = (cross ? cross.outerHTML : '') +
      '<span class="cgkit-swatch-size">' + label + '</span>';
  }

  function injectPrices() {
    var form = document.querySelector('.variations_form');
    if (!form) return false;
    var map = getMap(form);
    if (!map) return false;

    var swatches = document.querySelectorAll(SWATCHES + ' .cgkit-swatch');
    if (!swatches.length) return false;

    swatches.forEach(function (btn) {
      normalize(btn);
      var val = btn.getAttribute('data-attribute-value');
      var old = btn.querySelector('.cgkit-swatch-price');
      if (old) old.remove();

      var d = map[val];
      if (d && d.inStock) {
        btn.classList.remove('cgkit-swatch-oos');
        var s = document.createElement('span');
        s.className = 'cgkit-swatch-price';
        s.textContent = formatPrice(d.price);
        btn.appendChild(s);
      } else {
        btn.classList.add('cgkit-swatch-oos');
        if (OOS_LABEL) {
          var note = document.createElement('span');
          note.className = 'cgkit-swatch-price';
          note.textContent = OOS_LABEL;
          btn.appendChild(note);
        }
      }
    });
    return true;
  }

  function boot() {
    var n = 0;
    var t = setInterval(function () {
      if (injectPrices() || ++n > 20) clearInterval(t);
    }, 150);
  }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);

  document.addEventListener('click', function (e) {
    if (e.target.closest(SWATCHES)) {
      setTimeout(injectPrices, 50);
    }
  });
})();
