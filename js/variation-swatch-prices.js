/**
 * Golden Hive Blocks — Per-variation prices on size swatches.
 *
 * THEME-SPECIFIC (Shoptimizer + CommerceKit). See
 * includes/variation-swatch-prices.php for the full dependency notes. Reads
 * WooCommerce's own data-product_variations JSON off the variations form and
 * renders the matching price under each in-stock size swatch, greying out
 * out-of-stock sizes. Fails silently if the CommerceKit markup isn't present.
 */
(function () {
  function formatPrice(a) {
    return Math.round(a).toLocaleString('it-IT') + ' €';
  }

  function getMap(form) {
    var raw = form.getAttribute('data-product_variations');
    if (!raw || raw === 'false') return null;
    var vars;
    try { vars = JSON.parse(raw); } catch (e) { return null; }
    var map = {};
    vars.forEach(function (v) {
      var keys = Object.keys(v.attributes || {});
      if (!keys.length) return;
      var val = v.attributes[keys[0]];
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

    var swatches = document.querySelectorAll(
      '.cgkit-attribute-swatches[data-attribute="attribute_pa_taglia"] .cgkit-swatch'
    );
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
    if (e.target.closest('.cgkit-attribute-swatches[data-attribute="attribute_pa_taglia"]')) {
      setTimeout(injectPrices, 50);
    }
  });
})();
