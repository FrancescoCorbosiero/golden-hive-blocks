/**
 * Golden Hive Blocks — Quick Add to Cart modal (bottom sheet) behaviour.
 *
 * Vanilla JS, no build step. Extracted from the inline script that used to be
 * printed in wp_footer by includes/quick-add.php. Config comes from the
 * localized `ghbQuickAdd` object (ajaxUrl for the read-only variations
 * endpoint, cartEndpoint for WooCommerce's native wc-ajax=add_to_cart).
 *
 * Cart writes post the matched variation ID as product_id to the native WC
 * endpoint, so validation, notices and mini-cart fragments are all native —
 * one round-trip, no custom cart-write endpoint.
 */
(function () {
    'use strict';

    var cfg = window.ghbQuickAdd || {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var cartEndpoint = cfg.cartEndpoint || '';

    var overlay = document.querySelector('.ghb-qa-overlay');
    var modal = document.querySelector('.ghb-qa-modal');
    var content = modal ? modal.querySelector('.ghb-qa-content') : null;
    var closeBtn = modal ? modal.querySelector('.ghb-qa-close') : null;

    if (!overlay || !modal || !content) {
        return;
    }

    var currentVariations = [];
    var selectedAttrs = {};
    var matchedVariation = null;
    var lastFocus = null;
    var fetchController = null;
    var closeTimer = null;

    /* ── Scroll lock (shared GoldenHive helper, guarded fallback) ── */
    function lockScroll() {
        if (window.GoldenHive && window.GoldenHive.lockScroll) {
            window.GoldenHive.lockScroll();
        } else {
            document.documentElement.classList.add('gh-scroll-lock');
        }
    }
    function unlockScroll() {
        if (window.GoldenHive && window.GoldenHive.unlockScroll) {
            window.GoldenHive.unlockScroll();
        } else {
            document.documentElement.classList.remove('gh-scroll-lock');
        }
    }

    /* ── DOM helpers (nodes + textContent — no HTML injection) ── */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null && text !== '') node.textContent = text;
        return node;
    }

    function plainText(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return tmp.textContent || '';
    }

    function setLoading(message) {
        content.textContent = '';
        content.appendChild(el('div', 'ghb-qa-loading', message));
    }

    /* ── Open / close ── */
    function isOpen() {
        return modal.classList.contains('active');
    }

    function openModal() {
        // Una riapertura entro 1.5s annulla l'auto-chiusura post-acquisto.
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        if (!isOpen()) {
            lastFocus = document.activeElement;
            lockScroll();
        }
        overlay.classList.add('active');
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        if (closeBtn) closeBtn.focus();
    }

    function closeModal() {
        // Idempotente: il timer di auto-chiusura post-acquisto può scattare
        // dopo una chiusura manuale — senza guardia decrementerebbe il
        // contatore condiviso dello scroll-lock sotto un altro overlay
        // aperto e abortirebbe il fetch di una sheet riaperta.
        if (!modal.classList.contains('active')) {
            return;
        }
        if (fetchController) {
            fetchController.abort();
            fetchController = null;
        }
        overlay.classList.remove('active');
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        unlockScroll();
        if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
        lastFocus = null;
    }

    overlay.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) {
            closeModal();
        }
    });

    // Keep Tab within the dialog while it's open.
    modal.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !isOpen()) return;
        var focusables = Array.prototype.slice.call(
            modal.querySelectorAll('a[href], button:not([disabled]), input:not([readonly]), [tabindex]:not([tabindex="-1"])')
        ).filter(function (node) { return node.offsetParent !== null; });
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    /* ── Native WooCommerce fragment application ── */
    function applyFragments(fragments) {
        if (!fragments) return;
        Object.keys(fragments).forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (node) {
                var wrap = document.createElement('div');
                wrap.innerHTML = fragments[selector];
                var replacement = wrap.firstElementChild;
                if (replacement) {
                    node.replaceWith(replacement);
                } else {
                    node.remove();
                }
            });
        });
    }

    function notifyAdded(fragments, cartHash, target) {
        if (window.jQuery) {
            window.jQuery(document.body).trigger('added_to_cart', [
                fragments || null,
                cartHash || null,
                window.jQuery(target)
            ]);
        }
    }

    /* ── Add via WooCommerce's native endpoint (variation ID as product_id) ── */
    function nativeAddToCart(id, quantity) {
        var formData = new FormData();
        formData.append('product_id', id);
        formData.append('quantity', quantity || 1);
        return fetch(cartEndpoint, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); });
    }

    /* ── Simple product: direct add from the card button ── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ghb-simple-add-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        if (!cartEndpoint) return;

        var productId = btn.getAttribute('data-product-id');
        var originalText = btn.textContent;

        function fail() {
            btn.classList.remove('adding');
            btn.textContent = 'Errore — riprova';
            setTimeout(function () { btn.textContent = originalText; }, 2000);
        }

        btn.classList.add('adding');
        btn.textContent = 'Aggiunta...';

        nativeAddToCart(productId, 1)
            .then(function (data) {
                if (data && data.error) {
                    // WooCommerce refused the add — the real notice lives on
                    // the product page it points to.
                    if (data.product_url) {
                        window.location = data.product_url;
                        return;
                    }
                    fail();
                    return;
                }
                btn.classList.remove('adding');
                btn.classList.add('added');
                btn.textContent = 'Aggiunto al carrello!';
                // The returned fragments already refresh the mini-cart — no
                // extra wc_fragment_refresh round-trip.
                applyFragments(data ? data.fragments : null);
                notifyAdded(data ? data.fragments : null, data ? data.cart_hash : null, btn);
                setTimeout(function () {
                    btn.classList.remove('added');
                    btn.textContent = originalText;
                }, 2000);
            })
            .catch(fail);
    });

    /* ── Variable product: open modal + fetch variations ── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ghb-quick-add-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var productId = btn.getAttribute('data-product-id');

        // Reset the picker state BEFORE fetching, so a reopened modal can
        // never add the previously matched variation.
        currentVariations = [];
        selectedAttrs = {};
        matchedVariation = null;

        setLoading('Caricamento varianti...');
        openModal();

        // Abort any in-flight request so a slow earlier response can never
        // populate the modal with the wrong product.
        if (fetchController) fetchController.abort();
        fetchController = new AbortController();
        var signal = fetchController.signal;

        fetch(ajaxUrl + '?action=ghb_get_variations&product_id=' + encodeURIComponent(productId), { signal: signal })
            .then(function (r) { return r.json(); })
            .then(function (response) {
                if (signal.aborted) return;
                if (!response || !response.success) {
                    setLoading('Prodotto non trovato');
                    return;
                }
                renderModal(response.data, productId);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                setLoading('Errore di caricamento — riprova.');
            });
    });

    /* ── Modal content (createElement — no string HTML) ── */
    function renderModal(data, productId) {
        currentVariations = Array.isArray(data.variations) ? data.variations : [];
        selectedAttrs = {};
        matchedVariation = null;

        content.textContent = '';

        // Header
        var header = el('div', 'ghb-qa-header');
        var img = document.createElement('img');
        img.src = data.image || '';
        img.alt = data.title || '';
        header.appendChild(img);

        var headerInfo = el('div', 'ghb-qa-header-info');
        headerInfo.appendChild(el('div', 'ghb-qa-title', data.title || ''));
        var price = el('div', 'ghb-qa-price');
        if (data.price_html) {
            // WC-generated price markup (del/ins on sale) — safe server HTML.
            price.innerHTML = data.price_html;
        } else {
            price.textContent = data.price || '';
        }
        headerInfo.appendChild(price);
        header.appendChild(headerInfo);
        content.appendChild(header);

        // Attributes
        var attributes = el('div', 'ghb-qa-attributes');
        (data.attributes || []).forEach(function (attr) {
            var group = el('div', 'ghb-qa-attr-group');
            group.setAttribute('data-attr', attr.name);
            group.appendChild(el('div', 'ghb-qa-attr-label', attr.label));
            var options = el('div', 'ghb-qa-attr-options');
            (attr.options || []).forEach(function (opt) {
                var optBtn = el('button', 'ghb-qa-attr-option', opt);
                optBtn.type = 'button';
                optBtn.setAttribute('data-attr', attr.name);
                optBtn.setAttribute('data-value', opt);
                optBtn.setAttribute('aria-pressed', 'false');
                options.appendChild(optBtn);
            });
            group.appendChild(options);
            attributes.appendChild(group);
        });
        content.appendChild(attributes);

        // Quantity
        var qtyRow = el('div', 'ghb-qa-quantity-row');
        qtyRow.appendChild(el('span', 'ghb-qa-qty-label', 'Quantità'));
        var qtyWrap = el('div', 'ghb-qa-qty-wrap');
        var minus = el('button', 'ghb-qa-qty-btn ghb-qa-qty-minus', '−');
        minus.type = 'button';
        minus.setAttribute('aria-label', 'Riduci quantità');
        var qtyInput = document.createElement('input');
        qtyInput.type = 'text';
        qtyInput.className = 'ghb-qa-qty-value';
        qtyInput.value = '1';
        qtyInput.readOnly = true;
        qtyInput.setAttribute('aria-label', 'Quantità');
        var plus = el('button', 'ghb-qa-qty-btn ghb-qa-qty-plus', '+');
        plus.type = 'button';
        plus.setAttribute('aria-label', 'Aumenta quantità');
        qtyWrap.appendChild(minus);
        qtyWrap.appendChild(qtyInput);
        qtyWrap.appendChild(plus);
        qtyRow.appendChild(qtyWrap);
        content.appendChild(qtyRow);

        // Error + add-to-cart
        content.appendChild(el('div', 'ghb-qa-error', 'Seleziona tutte le opzioni'));
        var footer = el('div', 'ghb-qa-footer');
        var atcBtn = el('button', 'ghb-qa-add-to-cart', 'Seleziona le opzioni');
        atcBtn.type = 'button';
        atcBtn.setAttribute('data-product-id', productId);
        footer.appendChild(atcBtn);
        content.appendChild(footer);

        updateAvailability();
    }

    /* ── Quantity clamping to the matched variation's purchasable max ── */
    function maxQty() {
        if (matchedVariation && typeof matchedVariation.max_qty === 'number' && matchedVariation.max_qty > 0) {
            return matchedVariation.max_qty;
        }
        return Infinity; // -1 / missing → no cap
    }

    function clampQty() {
        var input = modal.querySelector('.ghb-qa-qty-value');
        if (!input) return;
        var val = parseInt(input.value, 10) || 1;
        var max = maxQty();
        if (val > max) val = max;
        if (val < 1) val = 1;
        input.value = val;
    }

    /* ── Attribute selection (delegated) ── */
    document.addEventListener('click', function (e) {
        var option = e.target.closest('.ghb-qa-attr-option:not(.unavailable)');
        if (!option || !modal.contains(option)) return;

        var attrName = option.getAttribute('data-attr');
        var val = option.getAttribute('data-value');

        // Toggle
        if (selectedAttrs[attrName] === val) {
            delete selectedAttrs[attrName];
            option.classList.remove('selected');
            option.setAttribute('aria-pressed', 'false');
        } else {
            selectedAttrs[attrName] = val;
            var siblings = option.parentElement.querySelectorAll('.ghb-qa-attr-option');
            siblings.forEach(function (s) {
                s.classList.remove('selected');
                s.setAttribute('aria-pressed', 'false');
            });
            option.classList.add('selected');
            option.setAttribute('aria-pressed', 'true');
        }

        updateAvailability();
        updateButton();
    });

    function updateAvailability() {
        var groups = modal.querySelectorAll('.ghb-qa-attr-group');
        groups.forEach(function (group) {
            var groupAttr = group.getAttribute('data-attr');
            var options = group.querySelectorAll('.ghb-qa-attr-option');

            options.forEach(function (opt) {
                var optValue = opt.getAttribute('data-value');
                var testAttrs = Object.assign({}, selectedAttrs);
                testAttrs[groupAttr] = optValue;

                var possible = currentVariations.some(function (v) {
                    return Object.keys(testAttrs).every(function (key) {
                        var vKey = 'attribute_' + key;
                        return !v.attributes[vKey] || v.attributes[vKey] === testAttrs[key];
                    }) && v.is_in_stock;
                });

                opt.classList.toggle('unavailable', !possible);
                if (!possible) {
                    opt.setAttribute('disabled', 'disabled');
                } else {
                    opt.removeAttribute('disabled');
                }
            });
        });
    }

    function updateButton() {
        var btn = modal.querySelector('.ghb-qa-add-to-cart');
        var errorEl = modal.querySelector('.ghb-qa-error');
        if (!btn) return;

        var totalAttrs = modal.querySelectorAll('.ghb-qa-attr-group').length;
        var selectedCount = Object.keys(selectedAttrs).length;

        if (selectedCount < totalAttrs) {
            btn.classList.remove('ready');
            btn.textContent = 'Seleziona le opzioni';
            if (errorEl) errorEl.classList.remove('visible');
            matchedVariation = null;
            return;
        }

        // Find matching variation
        matchedVariation = currentVariations.find(function (v) {
            return Object.keys(selectedAttrs).every(function (key) {
                var vKey = 'attribute_' + key;
                return !v.attributes[vKey] || v.attributes[vKey] === selectedAttrs[key];
            });
        }) || null;

        if (matchedVariation && matchedVariation.is_in_stock) {
            var priceText = matchedVariation.price_text || plainText(matchedVariation.price_html);
            btn.classList.add('ready');
            btn.textContent = 'Aggiungi al Carrello' + (priceText ? ' – ' + priceText : '');
            if (errorEl) errorEl.classList.remove('visible');

            // Reflect the picked variation in the header (image + sale-aware price).
            if (matchedVariation.image) {
                var img = modal.querySelector('.ghb-qa-header img');
                if (img) img.src = matchedVariation.image;
            }
            if (matchedVariation.price_html) {
                var priceEl = modal.querySelector('.ghb-qa-price');
                if (priceEl) priceEl.innerHTML = matchedVariation.price_html;
            }

            clampQty();
        } else if (matchedVariation && !matchedVariation.is_in_stock) {
            btn.classList.remove('ready');
            btn.textContent = 'Esaurito';
            if (errorEl) errorEl.classList.remove('visible');
        } else {
            btn.classList.remove('ready');
            btn.textContent = 'Combinazione non disponibile';
            if (errorEl) errorEl.classList.add('visible');
        }
    }

    /* ── Quantity controls (delegated) ── */
    document.addEventListener('click', function (e) {
        var minusBtn = e.target.closest('.ghb-qa-qty-minus');
        if (minusBtn && modal.contains(minusBtn)) {
            var input = minusBtn.closest('.ghb-qa-qty-wrap').querySelector('.ghb-qa-qty-value');
            var val = parseInt(input.value, 10) || 1;
            if (val > 1) input.value = val - 1;
            return;
        }
        var plusBtn = e.target.closest('.ghb-qa-qty-plus');
        if (plusBtn && modal.contains(plusBtn)) {
            var plusInput = plusBtn.closest('.ghb-qa-qty-wrap').querySelector('.ghb-qa-qty-value');
            var plusVal = parseInt(plusInput.value, 10) || 1;
            if (plusVal + 1 <= maxQty()) {
                plusInput.value = plusVal + 1;
            }
        }
    });

    /* ── Add to cart from the modal ── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ghb-qa-add-to-cart.ready');
        if (!btn || !modal.contains(btn)) return;

        if (!matchedVariation || !cartEndpoint) return;

        clampQty();
        var qtyInput = modal.querySelector('.ghb-qa-qty-value');
        var quantity = qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1;
        var errorEl = modal.querySelector('.ghb-qa-error');

        function fail(message) {
            btn.classList.remove('adding');
            btn.classList.add('ready');
            btn.textContent = 'Errore – Riprova';
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.classList.add('visible');
            }
        }

        btn.classList.remove('ready');
        btn.classList.add('adding');
        btn.textContent = 'Aggiunta in corso...';
        if (errorEl) errorEl.classList.remove('visible');

        // Post the variation ID as product_id: WooCommerce's native endpoint
        // resolves the parent product and attributes itself.
        nativeAddToCart(matchedVariation.variation_id, quantity)
            .then(function (data) {
                if (data && data.error) {
                    // WooCommerce refused the add (stock, cart limits…); the
                    // full notice is on the product page it points to.
                    fail(data.product_url
                        ? 'Impossibile aggiungere al carrello — apri la pagina prodotto per i dettagli.'
                        : 'Impossibile aggiungere al carrello — riprova.');
                    return;
                }
                btn.classList.remove('adding');
                btn.classList.add('added');
                btn.textContent = 'Aggiunto al carrello!';
                // Fragments returned with the add already refresh the
                // mini-cart — no extra wc_fragment_refresh round-trip.
                applyFragments(data ? data.fragments : null);
                notifyAdded(data ? data.fragments : null, data ? data.cart_hash : null, btn);
                closeTimer = setTimeout(closeModal, 1500);
            })
            .catch(function () {
                fail('Errore di rete — riprova.');
            });
    });
})();
