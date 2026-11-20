/**
 * Golden Hive Blocks — WooCommerce Quick View modal behaviour.
 *
 * Vanilla JS, no build step, no jQuery dependency. Fetches a product over
 * AJAX and renders it in a modal built with createElement/textContent (no
 * HTML injection from product data). The ajax URL/action and the cart
 * endpoint come from the localized `ghbQuickView` object (see
 * includes/quick-view.php); the product data from `ghb_quick_view`
 * (includes/product-ajax.php).
 *
 * Add-to-cart posts to WooCommerce's native wc-ajax=add_to_cart endpoint
 * (posting the variation ID for variable products), so cart fragments and
 * the mini-cart update natively in the same round-trip.
 */
(function () {
    'use strict';

    var cfg = window.ghbQuickView || {};
    var overlay = document.querySelector('.ghb-qv-overlay');
    var modal = document.querySelector('.ghb-qv-modal');
    var content = modal ? modal.querySelector('.ghb-qv-content') : null;
    var closeBtn = modal ? modal.querySelector('.ghb-qv-close') : null;

    if (!overlay || !modal || !content) {
        return;
    }

    var lastFocus = null;
    var fetchController = null;

    // Size-picker state for the product on screen. Picking a size only
    // selects it (price + button follow the pick); the cart write waits for
    // an explicit click on "Aggiungi al carrello".
    var ADD_LABEL = 'Aggiungi al carrello';
    var sizeData = {};      // variation id → size row from the payload
    var selectedSize = null;
    var basePrice = { html: '', text: '' };

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
        content.appendChild(el('div', 'ghb-qv-loading', message));
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
    // Also any [data-ghb-quick-view][data-product-id] — the Instagram
    // block's "Shop the look" tags.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ghb-qv-btn, [data-ghb-quick-view][data-product-id]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        var productId = btn.getAttribute('data-product-id');
        // The size the shop's size filter asked for (includes/size-match.php).
        var preselect = btn.getAttribute('data-size-variation') || '';

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
                renderProduct(productId, response.data, preselect);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                setLoading('Errore di caricamento — riprova.');
            });
    });

    /* ── Render (createElement/textContent — no injection) ── */
    function renderProduct(productId, p, preselect) {
        content.textContent = '';
        sizeData = {};
        selectedSize = null;
        basePrice = { html: p.price_html || '', text: p.price || '' };

        var body = el('div', 'ghb-qv-body');

        // Gallery
        var gallery = el('div', 'ghb-qv-gallery');
        var images = Array.isArray(p.images) ? p.images : [];
        if (images.length) {
            var mainImg = el('img', 'ghb-qv-main-img');
            mainImg.src = images[0];
            mainImg.alt = p.title || '';
            gallery.appendChild(mainImg);

            if (images.length > 1) {
                var thumbs = el('div', 'ghb-qv-thumbs');
                images.forEach(function (src, i) {
                    var thumb = el('img', 'ghb-qv-thumb' + (i === 0 ? ' active' : ''));
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
        var info = el('div', 'ghb-qv-info');
        if (p.categories) {
            info.appendChild(el('div', 'ghb-qv-cats', p.categories));
        }
        info.appendChild(el('div', 'ghb-qv-title', p.title || ''));

        var price = el('div', 'ghb-qv-price');
        if (p.price_html) {
            // WC-generated price markup (del/ins on sale) — safe server HTML.
            price.innerHTML = p.price_html;
        } else {
            price.textContent = p.price || '';
        }
        info.appendChild(price);

        info.appendChild(el(
            'div',
            'ghb-qv-stock ' + (p.in_stock ? 'in-stock' : 'out-of-stock'),
            p.stock_text || ''
        ));

        if (p.short_desc) {
            var desc = el('div', 'ghb-qv-desc');
            // wpautop'd short description from the server (trusted, own site).
            desc.innerHTML = p.short_desc;
            info.appendChild(desc);
        }

        if (Array.isArray(p.attributes) && p.attributes.length) {
            var attrs = el('div', 'ghb-qv-attrs');
            p.attributes.forEach(function (a) {
                var attr = el('span', 'ghb-qv-attr');
                attr.appendChild(el('strong', '', (a.label || '') + ':'));
                attr.appendChild(document.createTextNode(' ' + (a.value || '')));
                attrs.appendChild(attr);
            });
            info.appendChild(attrs);
        }

        if (p.sku) {
            info.appendChild(el('div', 'ghb-qv-sku', 'SKU: ' + p.sku));
        }

        info.appendChild(el('div', 'ghb-qv-atc'));

        // Secondary action: the add-to-cart button above is the primary one.
        var viewFull = el('a', 'ghb-qv-view-full', 'Vedi prodotto completo');
        viewFull.href = p.url || '#';
        var arrow = el('span', 'ghb-qv-view-full__arrow', '→');
        arrow.setAttribute('aria-hidden', 'true');
        viewFull.appendChild(arrow);
        info.appendChild(viewFull);

        var actions = renderActions(p);
        if (actions) info.appendChild(actions);

        body.appendChild(info);
        content.appendChild(body);

        renderCartControl(productId, p);

        if (preselect) {
            var picked = content.querySelector('.ghb-qv-size[data-variation-id="' + parseInt(preselect, 10) + '"]');
            if (picked) pickSize(picked);
        }
    }

    // "Chiedi su WhatsApp" + "Condividi", as on the product page
    // (includes/product-page.php). The WhatsApp message is written on click,
    // so it names the size picked by then.
    function renderActions(p) {
        var canShare = !!(navigator.share || (navigator.clipboard && window.isSecureContext));
        if (!cfg.whatsapp && !canShare) return null;

        var row = el('div', 'ghb-qv-actions');
        row.setAttribute('data-name', p.title || '');
        row.setAttribute('data-url', p.url || '');
        if (cfg.whatsapp) {
            var wa = el('a', 'ghb-qv-action ghb-qv-action--whatsapp', 'Chiedi su WhatsApp');
            wa.href = whatsappUrl(p.title, '', p.url);
            wa.target = '_blank';
            wa.rel = 'noopener noreferrer';
            row.appendChild(wa);
        }
        if (canShare) {
            var shareBtn = el('button', 'ghb-qv-action ghb-qv-action--share', 'Condividi');
            shareBtn.type = 'button';
            row.appendChild(shareBtn);
        }
        return row;
    }

    function whatsappUrl(name, size, url) {
        var text = 'Ciao! Vorrei informazioni su ' + (name || '') + (size ? ', taglia ' + size : '') + ': ' + (url || '');
        return 'https://wa.me/' + cfg.whatsapp + '?text=' + encodeURIComponent(text);
    }

    function share(btn, name, url) {
        if (navigator.share) {
            navigator.share({ title: name, url: url }).catch(function () {});
            return;
        }
        navigator.clipboard.writeText(url).then(function () {
            btn.textContent = 'Link copiato';
            setTimeout(function () { btn.textContent = 'Condividi'; }, 2000);
        }).catch(function () {});
    }

    // Build the add-to-cart control (simple button or size pills) straight
    // from the Quick View payload — no extra request.
    function renderCartControl(productId, p) {
        var slot = content.querySelector('.ghb-qv-atc');
        if (!slot) return;

        // A drop not out yet (includes/drops.php): say when, buy nothing.
        if (p.drop && p.drop.ts * 1000 > Date.now()) {
            var soon = el('button', 'ghb-qv-add', p.drop.label || 'In arrivo');
            soon.type = 'button';
            soon.disabled = true;
            slot.appendChild(soon);
            return;
        }

        if (p.type === 'variable') {
            if (!Array.isArray(p.sizes) || !p.sizes.length) {
                var optionsLink = el('a', 'ghb-qv-add ghb-qv-add--link', 'Seleziona opzioni');
                optionsLink.href = p.url || '#';
                slot.appendChild(optionsLink);
                return;
            }
            slot.appendChild(el('div', 'ghb-qv-sizes-label', 'Seleziona taglia'));
            var sizes = el('div', 'ghb-qv-sizes');
            sizes.setAttribute('role', 'group');
            sizes.setAttribute('aria-label', 'Taglia');
            var anyInStock = false;
            p.sizes.forEach(function (s) {
                if (s.in_stock) {
                    anyInStock = true;
                    sizeData[s.variation_id] = s;
                    var sizeBtn = el('button', 'ghb-qv-size', s.label);
                    sizeBtn.type = 'button';
                    sizeBtn.setAttribute('aria-pressed', 'false');
                    sizeBtn.setAttribute('data-variation-id', s.variation_id);
                    sizes.appendChild(sizeBtn);
                } else {
                    sizes.appendChild(el('span', 'ghb-qv-size is-oos', s.label));
                }
            });
            slot.appendChild(sizes);

            if (!anyInStock) {
                var allGone = el('button', 'ghb-qv-add', 'Esaurito');
                allGone.type = 'button';
                allGone.disabled = true;
                slot.appendChild(allGone);
                return;
            }

            // Filled in only when the shopper tries to add without a size.
            var hint = el('div', 'ghb-qv-size-hint');
            hint.setAttribute('aria-live', 'assertive');
            slot.appendChild(hint);

            var confirmBtn = el('button', 'ghb-qv-add', ADD_LABEL);
            confirmBtn.type = 'button';
            confirmBtn.setAttribute('data-needs-size', '1');
            slot.appendChild(confirmBtn);
            slot.appendChild(statusLine());
        } else if (p.purchasable) {
            var addBtn = el('button', 'ghb-qv-add', ADD_LABEL);
            addBtn.type = 'button';
            addBtn.setAttribute('data-product-id', productId);
            slot.appendChild(addBtn);
            slot.appendChild(statusLine());
        } else if (p.type !== 'simple') {
            var viewLink = el('a', 'ghb-qv-add ghb-qv-add--link', 'Vedi prodotto');
            viewLink.href = p.url || '#';
            slot.appendChild(viewLink);
        } else {
            var soldOut = el('button', 'ghb-qv-add', 'Esaurito');
            soldOut.type = 'button';
            soldOut.disabled = true;
            slot.appendChild(soldOut);
        }
    }

    /* ── Size selection ── */
    function addLabel(btn) {
        if (btn && btn.hasAttribute('data-needs-size') && selectedSize && selectedSize.price_text) {
            return ADD_LABEL + ' – ' + selectedSize.price_text;
        }
        return ADD_LABEL;
    }

    function showPrice(html, text) {
        var priceEl = content.querySelector('.ghb-qv-price');
        if (!priceEl) return;
        if (html) {
            // WC-generated price markup (del/ins on sale) — safe server HTML.
            priceEl.innerHTML = html;
        } else {
            priceEl.textContent = text || '';
        }
    }

    // Toggle a size: select it (price line + button show that size's price)
    // or, clicking it again, deselect and restore the product price.
    function pickSize(btn) {
        var slot = btn.closest('.ghb-qv-atc');
        var id = btn.getAttribute('data-variation-id');
        var deselect = !!selectedSize && String(selectedSize.variation_id) === id;

        slot.querySelectorAll('.ghb-qv-size[data-variation-id]').forEach(function (b) {
            var on = !deselect && b === btn;
            b.classList.toggle('is-selected', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        selectedSize = deselect ? null : (sizeData[id] || null);

        if (selectedSize && selectedSize.price_html) {
            showPrice(selectedSize.price_html);
        } else {
            showPrice(basePrice.html, basePrice.text);
        }

        var confirmBtn = slot.querySelector('.ghb-qv-add[data-needs-size]');
        if (confirmBtn) confirmBtn.textContent = addLabel(confirmBtn);
        setHint(slot, false);
        clearStatus(slot);
    }

    // "Aggiungi al carrello" without a size: point at the size grid instead
    // of failing silently.
    function setHint(slot, show) {
        var hint = slot.querySelector('.ghb-qv-size-hint');
        var grid = slot.querySelector('.ghb-qv-sizes');
        if (hint) {
            hint.textContent = show ? 'Seleziona una taglia' : '';
            hint.classList.toggle('is-visible', show);
        }
        if (!grid) return;
        grid.classList.remove('is-invalid');
        if (show) {
            void grid.offsetWidth; // restart the nudge animation on repeat clicks
            grid.classList.add('is-invalid');
            var first = grid.querySelector('.ghb-qv-size[data-variation-id]');
            if (first) first.focus();
        }
    }

    /* ── Status line under the button (polite live region) ── */
    function statusLine() {
        var msg = el('div', 'ghb-qv-cart-msg');
        msg.setAttribute('role', 'status');
        return msg;
    }

    function feedback(slot, text, isError, link) {
        var msg = slot.querySelector('.ghb-qv-cart-msg');
        if (!msg) {
            msg = statusLine();
            slot.appendChild(msg);
        }
        msg.textContent = text;
        if (link && link.href) {
            var a = el('a', 'ghb-qv-cart-link', link.text);
            a.href = link.href;
            msg.appendChild(document.createTextNode(' '));
            msg.appendChild(a);
        }
        msg.classList.toggle('is-error', !!isError);
        msg.classList.add('show');
    }

    function clearStatus(slot) {
        var msg = slot.querySelector('.ghb-qv-cart-msg');
        if (!msg) return;
        msg.classList.remove('show', 'is-error');
        msg.textContent = '';
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
    function addToCart(slot, id, btn) {
        if (!cfg.cartEndpoint || !id) return;
        slot.classList.add('is-busy');
        clearStatus(slot);
        if (btn) btn.textContent = 'Aggiunta in corso…';

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
                    if (btn) btn.textContent = addLabel(btn);
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
                // Confirm on the button for a beat, then leave a way to the
                // cart; the modal stays open so shopping can continue.
                if (btn) {
                    btn.classList.add('is-added');
                    btn.textContent = '✓ Aggiunto';
                    setTimeout(function () {
                        btn.classList.remove('is-added');
                        btn.textContent = addLabel(btn);
                    }, 1800);
                }
                feedback(slot, 'Aggiunto al carrello.', false,
                    cfg.cartUrl ? { href: cfg.cartUrl, text: 'Vai al carrello' } : null);
            })
            .catch(function () {
                slot.classList.remove('is-busy');
                if (btn) btn.textContent = addLabel(btn);
                feedback(slot, 'Errore di caricamento — riprova.', true);
            });
    }

    /* ── Delegated clicks inside the modal ── */
    document.addEventListener('click', function (e) {
        var action = e.target.closest('.ghb-qv-action');
        if (action && modal.contains(action)) {
            var row = action.closest('.ghb-qv-actions');
            var name = row.getAttribute('data-name');
            var url = row.getAttribute('data-url');
            if (action.classList.contains('ghb-qv-action--whatsapp')) {
                // Rewritten before the browser follows it.
                action.href = whatsappUrl(name, selectedSize ? selectedSize.label : '', url);
            } else {
                share(action, name, url);
            }
            return;
        }

        // Simple product → add directly.
        var addBtn = e.target.closest('.ghb-qv-atc .ghb-qv-add[data-product-id]');
        if (addBtn) {
            e.preventDefault();
            addToCart(addBtn.closest('.ghb-qv-atc'), addBtn.getAttribute('data-product-id'), addBtn);
            return;
        }

        // Variable product: a size click only selects it…
        var sizeBtn = e.target.closest('.ghb-qv-atc .ghb-qv-size[data-variation-id]');
        if (sizeBtn) {
            e.preventDefault();
            pickSize(sizeBtn);
            return;
        }

        // …and the button adds the picked size's variation (posted as
        // product_id: WooCommerce resolves parent + attributes natively).
        var confirmBtn = e.target.closest('.ghb-qv-atc .ghb-qv-add[data-needs-size]');
        if (confirmBtn) {
            e.preventDefault();
            var slot = confirmBtn.closest('.ghb-qv-atc');
            if (!selectedSize) {
                setHint(slot, true);
                return;
            }
            addToCart(slot, selectedSize.variation_id, confirmBtn);
            return;
        }

        // Thumbnail click → swap the main image.
        var thumb = e.target.closest('.ghb-qv-thumb');
        if (thumb) {
            var src = thumb.getAttribute('data-src');
            var siblings = thumb.parentElement.querySelectorAll('.ghb-qv-thumb');
            siblings.forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
            var gallery = thumb.closest('.ghb-qv-gallery');
            var mainImg = gallery ? gallery.querySelector('.ghb-qv-main-img') : null;
            if (mainImg && src) mainImg.src = src;
        }
    });
})();
