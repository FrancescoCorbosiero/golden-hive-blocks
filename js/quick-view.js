/**
 * Golden Hive Blocks — WooCommerce Quick View modal behaviour.
 *
 * Vanilla JS, no build step, no jQuery dependency. Fetches a product over
 * AJAX and renders it in a modal built with createElement/textContent (no
 * HTML injection from product data). The ajax URL/action and the cart
 * endpoint come from the localized `ghbQuickView` object (see
 * includes/quick-view.php). Class names (.rp-qv-*) are preserved from the
 * original site snippet this was migrated from.
 *
 * Add-to-cart posts to WooCommerce's native wc-ajax=add_to_cart endpoint
 * (posting the variation ID for variable products), so cart fragments and
 * the mini-cart update natively in the same round-trip.
 */
(function () {
    'use strict';

    var cfg = window.ghbQuickView || {};
    var overlay = document.querySelector('.rp-qv-overlay');
    var modal = document.querySelector('.rp-qv-modal');
    var content = modal ? modal.querySelector('.rp-qv-content') : null;
    var closeBtn = modal ? modal.querySelector('.rp-qv-close') : null;

    if (!overlay || !modal || !content) {
        return;
    }

    var lastFocus = null;
    var fetchController = null;

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

    /* ── Small DOM helpers ── */
    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null && text !== '') node.textContent = text;
        return node;
    }

    function setLoading(message) {
        content.textContent = '';
        content.appendChild(el('div', 'rp-qv-loading', message));
    }

    /* ── Open / close ── */
    function isOpen() {
        return modal.classList.contains('active');
    }

    function openModal() {
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
            modal.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])')
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

    /* ── Open + fetch on quick-view button click (delegated) ── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.rp-quick-view-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var productId = btn.getAttribute('data-product-id');

        setLoading('Caricamento...');
        openModal();

        // Abort any in-flight request so a slow earlier response can never
        // overwrite the product the user clicked last.
        if (fetchController) fetchController.abort();
        fetchController = new AbortController();
        var signal = fetchController.signal;

        var url = (cfg.ajaxUrl || '')
            + '?action=' + encodeURIComponent(cfg.action || 'ghb_quick_view')
            + '&product_id=' + encodeURIComponent(productId);

        fetch(url, { signal: signal })
            .then(function (r) { return r.json(); })
            .then(function (response) {
                if (signal.aborted) return;
                if (!response || !response.success) {
                    setLoading('Prodotto non trovato');
                    return;
                }
                renderProduct(productId, response.data);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                setLoading('Errore di caricamento — riprova.');
            });
    });

    /* ── Render (createElement/textContent — no injection) ── */
    function renderProduct(productId, p) {
        content.textContent = '';

        var body = el('div', 'rp-qv-body');

        // Gallery
        var gallery = el('div', 'rp-qv-gallery');
        var images = Array.isArray(p.images) ? p.images : [];
        if (images.length) {
            var mainImg = el('img', 'rp-qv-main-img');
            mainImg.src = images[0];
            mainImg.alt = p.title || '';
            gallery.appendChild(mainImg);

            if (images.length > 1) {
                var thumbs = el('div', 'rp-qv-thumbs');
                images.forEach(function (src, i) {
                    var thumb = el('img', 'rp-qv-thumb' + (i === 0 ? ' active' : ''));
                    thumb.src = src;
                    thumb.alt = '';
                    thumb.setAttribute('data-src', src);
                    thumbs.appendChild(thumb);
                });
                gallery.appendChild(thumbs);
            }
        }
        body.appendChild(gallery);

        // Info
        var info = el('div', 'rp-qv-info');
        if (p.categories) {
            info.appendChild(el('div', 'rp-qv-cats', p.categories));
        }
        info.appendChild(el('div', 'rp-qv-title', p.title || ''));

        var price = el('div', 'rp-qv-price');
        if (p.price_html) {
            // WC-generated price markup (del/ins on sale) — safe server HTML.
            price.innerHTML = p.price_html;
        } else {
            price.textContent = p.price || '';
        }
        info.appendChild(price);

        info.appendChild(el(
            'div',
            'rp-qv-stock ' + (p.in_stock ? 'in-stock' : 'out-of-stock'),
            p.stock_text || ''
        ));

        if (p.short_desc) {
            var desc = el('div', 'rp-qv-desc');
            // wpautop'd short description from the server (trusted, own site).
            desc.innerHTML = p.short_desc;
            info.appendChild(desc);
        }

        if (Array.isArray(p.attributes) && p.attributes.length) {
            var attrs = el('div', 'rp-qv-attrs');
            p.attributes.forEach(function (a) {
                var attr = el('span', 'rp-qv-attr');
                attr.appendChild(el('strong', '', (a.label || '') + ':'));
                attr.appendChild(document.createTextNode(' ' + (a.value || '')));
                attrs.appendChild(attr);
            });
            info.appendChild(attrs);
        }

        if (p.sku) {
            info.appendChild(el('div', 'rp-qv-sku', 'SKU: ' + p.sku));
        }

        info.appendChild(el('div', 'rp-qv-atc'));

        var viewFull = el('a', 'rp-qv-view-full', 'Vedi Prodotto Completo');
        viewFull.href = p.url || '#';
        info.appendChild(viewFull);

        body.appendChild(info);
        content.appendChild(body);

        renderCartControl(productId, p);
    }

    // Build the add-to-cart control (simple button or size pills) straight
    // from the Quick View payload — no extra request.
    function renderCartControl(productId, p) {
        var slot = content.querySelector('.rp-qv-atc');
        if (!slot) return;

        if (p.type === 'variable') {
            if (!Array.isArray(p.sizes) || !p.sizes.length) {
                var optionsLink = el('a', 'rp-qv-add rp-qv-add--link', 'Seleziona opzioni');
                optionsLink.href = p.url || '#';
                slot.appendChild(optionsLink);
                return;
            }
            slot.appendChild(el('div', 'rp-qv-sizes-label', 'Seleziona taglia'));
            var sizes = el('div', 'rp-qv-sizes');
            p.sizes.forEach(function (s) {
                if (s.in_stock) {
                    var sizeBtn = el('button', 'rp-qv-size', s.label);
                    sizeBtn.type = 'button';
                    sizeBtn.setAttribute('data-variation-id', s.variation_id);
                    sizes.appendChild(sizeBtn);
                } else {
                    sizes.appendChild(el('span', 'rp-qv-size is-oos', s.label));
                }
            });
            slot.appendChild(sizes);
        } else if (p.purchasable) {
            var addBtn = el('button', 'rp-qv-add', 'Aggiungi al carrello');
            addBtn.type = 'button';
            addBtn.setAttribute('data-product-id', productId);
            slot.appendChild(addBtn);
        } else if (p.type !== 'simple') {
            var viewLink = el('a', 'rp-qv-add rp-qv-add--link', 'Vedi prodotto');
            viewLink.href = p.url || '#';
            slot.appendChild(viewLink);
        } else {
            var soldOut = el('button', 'rp-qv-add', 'Esaurito');
            soldOut.type = 'button';
            soldOut.disabled = true;
            slot.appendChild(soldOut);
        }
    }

    /* ── Feedback message inside the atc slot ── */
    var msgTimer = null;
    function feedback(slot, text, isError) {
        var msg = slot.querySelector('.rp-qv-cart-msg');
        if (!msg) {
            msg = el('div', 'rp-qv-cart-msg');
            slot.appendChild(msg);
        }
        msg.textContent = text;
        msg.classList.toggle('is-error', !!isError);
        msg.classList.add('show');
        clearTimeout(msgTimer);
        msgTimer = setTimeout(function () { msg.classList.remove('show'); }, 3000);
    }

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

    /* ── Add to cart via WooCommerce's native wc-ajax endpoint ── */
    function addToCart(slot, id) {
        if (!cfg.cartEndpoint || !id) return;
        slot.classList.add('is-busy');

        var formData = new FormData();
        formData.append('product_id', id);
        formData.append('quantity', 1);

        fetch(cfg.cartEndpoint, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                slot.classList.remove('is-busy');
                if (data && data.error) {
                    // WooCommerce refused the add (stock, purchasability…);
                    // the real notice is shown on the product page it points to.
                    if (data.product_url) {
                        window.location = data.product_url;
                        return;
                    }
                    feedback(slot, 'Impossibile aggiungere al carrello — riprova.', true);
                    return;
                }
                // Success: the returned fragments already carry the fresh
                // mini-cart — no extra wc_fragment_refresh round-trip needed.
                applyFragments(data ? data.fragments : null);
                if (window.jQuery) {
                    window.jQuery(document.body).trigger('added_to_cart', [
                        data ? data.fragments : null,
                        data ? data.cart_hash : null,
                        window.jQuery(slot)
                    ]);
                }
                feedback(slot, '✓ Aggiunto al carrello', false);
            })
            .catch(function () {
                slot.classList.remove('is-busy');
                feedback(slot, 'Errore di caricamento — riprova.', true);
            });
    }

    /* ── Delegated clicks inside the modal ── */
    document.addEventListener('click', function (e) {
        // Simple product → add directly.
        var addBtn = e.target.closest('.rp-qv-atc .rp-qv-add[data-product-id]');
        if (addBtn) {
            e.preventDefault();
            addToCart(addBtn.closest('.rp-qv-atc'), addBtn.getAttribute('data-product-id'));
            return;
        }

        // Variable product → add the picked size's variation (posted as
        // product_id: WooCommerce resolves parent + attributes natively).
        var sizeBtn = e.target.closest('.rp-qv-atc .rp-qv-size[data-variation-id]');
        if (sizeBtn) {
            e.preventDefault();
            addToCart(sizeBtn.closest('.rp-qv-atc'), sizeBtn.getAttribute('data-variation-id'));
            return;
        }

        // Thumbnail click → swap the main image.
        var thumb = e.target.closest('.rp-qv-thumb');
        if (thumb) {
            var src = thumb.getAttribute('data-src');
            var siblings = thumb.parentElement.querySelectorAll('.rp-qv-thumb');
            siblings.forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
            var gallery = thumb.closest('.rp-qv-gallery');
            var mainImg = gallery ? gallery.querySelector('.rp-qv-main-img') : null;
            if (mainImg && src) mainImg.src = src;
        }
    });
})();
