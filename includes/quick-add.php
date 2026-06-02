<?php
/**
 * Golden Hive — Quick Add to Cart modal (bottom sheet).
 *
 * Restored modal-based quick-add: a single button on each product card opens a
 * bottom-sheet modal to pick the variation (size) and add to cart — simple
 * products add directly. Triggered by:
 *   • .ghb-quick-add-btn[data-product-id]  → variable products (opens modal)
 *   • .ghb-simple-add-btn[data-product-id]  → simple products (direct add)
 * Both buttons are rendered by includes/add-to-cart.php on the loop cards.
 *
 * Uses the ghb_get_variations / ghb_add_to_cart AJAX handlers in
 * product-carousel-shortcode.php, and refreshes the mini-cart via
 * wc_fragment_refresh.
 *
 * @package Golden_Hive_Blocks
 * @since   5.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', 'ghb_quick_add_to_cart_frontend');
function ghb_quick_add_to_cart_frontend()
{
    // Load wherever WooCommerce is active (the rails/loops can appear on any
    // front-end page, e.g. the home page).
    if (!class_exists('WooCommerce')) {
        return;
    }
    ?>
    <style>
        /* ═══════════════════════════════════════════════════════════
           GHB Quick Add Modal Styles
           ═══════════════════════════════════════════════════════════ */

        /* Overlay */
        .ghb-qa-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 99998;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .ghb-qa-overlay.active { display: block; }

        /* Modal */
        .ghb-qa-modal {
            display: none;
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            max-height: 85vh;
            overflow-y: auto;
            background: var(--ghb-white, #fff);
            border-radius: 20px 20px 0 0;
            z-index: 99999;
            box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.15);
            animation: ghb-qa-slide-up 0.3s ease;
        }
        .ghb-qa-modal.active { display: block; }

        @keyframes ghb-qa-slide-up {
            from { transform: translateX(-50%) translateY(100%); }
            to { transform: translateX(-50%) translateY(0); }
        }

        /* Handle bar */
        .ghb-qa-handle {
            width: 40px;
            height: 4px;
            background: #ddd;
            border-radius: 4px;
            margin: 10px auto 0;
        }

        .ghb-qa-close {
            position: absolute;
            top: 10px;
            right: 14px;
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #999;
            line-height: 1;
            transition: color 0.2s;
        }
        .ghb-qa-close:hover { color: var(--ghb-accent, #721124); }

        /* Product header */
        .ghb-qa-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 20px 20px 12px;
        }
        .ghb-qa-header img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 12px;
            background: var(--ghb-light, #f5f5f5);
            flex-shrink: 0;
        }
        .ghb-qa-header-info { min-width: 0; }
        .ghb-qa-title {
            font-size: 15px;
            font-weight: 700;
            color: #222;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ghb-qa-price {
            font-size: 17px;
            font-weight: 700;
            color: var(--ghb-accent, #721124);
            margin-top: 2px;
        }

        /* Attribute selectors */
        .ghb-qa-attributes {
            padding: 0 20px;
        }
        .ghb-qa-attr-group {
            margin-bottom: 16px;
        }
        .ghb-qa-attr-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .ghb-qa-attr-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ghb-qa-attr-option {
            padding: 8px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fff;
            color: #333;
            user-select: none;
            text-align: center;
            min-width: 44px;
        }
        .ghb-qa-attr-option:hover {
            border-color: var(--ghb-accent, #721124);
            color: var(--ghb-accent, #721124);
            background: rgba(114, 17, 36, 0.04);
        }
        .ghb-qa-attr-option.selected {
            border-color: var(--ghb-accent, #721124);
            background: var(--ghb-accent, #721124);
            color: #fff;
            font-weight: 600;
        }
        .ghb-qa-attr-option.unavailable {
            opacity: 0.25;
            cursor: not-allowed;
            text-decoration: line-through;
            pointer-events: none;
        }

        /* Quantity */
        .ghb-qa-quantity-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px;
            margin-bottom: 16px;
        }
        .ghb-qa-qty-label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ghb-qa-qty-wrap {
            display: flex;
            align-items: center;
            border: 1.5px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
        }
        .ghb-qa-qty-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: #f9f9f9;
            cursor: pointer;
            font-size: 18px;
            color: #555;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ghb-qa-qty-btn:hover {
            background: var(--ghb-accent, #721124);
            color: #fff;
        }
        .ghb-qa-qty-value {
            width: 40px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            border: none;
            outline: none;
            color: #333;
        }

        /* Add to cart button */
        .ghb-qa-footer {
            padding: 16px 20px 24px;
        }
        .ghb-qa-add-to-cart {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: #d4d4d4;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            cursor: not-allowed;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
        }
        .ghb-qa-add-to-cart.ready {
            background: var(--ghb-accent, #721124);
            cursor: pointer;
            pointer-events: auto;
        }
        .ghb-qa-add-to-cart.ready:hover {
            background: var(--ghb-accent-dark, #520c1a);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(114, 17, 36, 0.25);
        }
        .ghb-qa-add-to-cart.ready:active {
            transform: translateY(0);
        }
        .ghb-qa-add-to-cart.adding {
            background: #999;
            pointer-events: none;
        }
        .ghb-qa-add-to-cart.added {
            background: var(--ghb-success, #38a169);
            pointer-events: none;
        }

        /* Error */
        .ghb-qa-error {
            font-size: 12px;
            color: #c62828;
            text-align: center;
            padding: 0 20px 8px;
            display: none;
        }
        .ghb-qa-error.visible { display: block; }

        /* Loading */
        .ghb-qa-loading {
            padding: 40px;
            text-align: center;
            color: #999;
            font-size: 14px;
        }

        /* Simple product adding state on the card button */
        .ghb-simple-add-btn.adding {
            opacity: 0.6;
            pointer-events: none;
        }
        .ghb-simple-add-btn.added {
            background: var(--ghb-success, #38a169) !important;
            color: #fff !important;
            pointer-events: none;
        }

        /* Desktop: center modal */
        @media (min-width: 641px) {
            .ghb-qa-modal {
                bottom: auto;
                top: 50%;
                transform: translate(-50%, -50%);
                border-radius: 20px;
                animation: ghb-qa-fade-in 0.25s ease;
            }
            @keyframes ghb-qa-fade-in {
                from { opacity: 0; transform: translate(-50%, -48%); }
                to { opacity: 1; transform: translate(-50%, -50%); }
            }
        }
    </style>

    <!-- GHB Quick Add Modal -->
    <div class="ghb-qa-overlay"></div>
    <div class="ghb-qa-modal">
        <div class="ghb-qa-handle"></div>
        <button class="ghb-qa-close" aria-label="Chiudi">&times;</button>
        <div class="ghb-qa-content"></div>
    </div>

    <script>
    (function() {
        var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
        var overlay = document.querySelector('.ghb-qa-overlay');
        var modal = document.querySelector('.ghb-qa-modal');
        var content = modal ? modal.querySelector('.ghb-qa-content') : null;
        var currentVariations = [];
        var selectedAttrs = {};
        var matchedVariation = null;

        if (!overlay || !modal || !content) return;

        function closeModal() {
            overlay.classList.remove('active');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        overlay.addEventListener('click', closeModal);
        modal.querySelector('.ghb-qa-close').addEventListener('click', closeModal);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // ─── Simple product: direct AJAX add to cart ───
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-simple-add-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var productId = btn.getAttribute('data-product-id');
            var originalText = btn.textContent;
            btn.classList.add('adding');
            btn.textContent = 'Aggiunta...';

            var formData = new FormData();
            formData.append('action', 'ghb_add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (response.success) {
                        btn.classList.remove('adding');
                        btn.classList.add('added');
                        btn.textContent = 'Aggiunto! (' + response.data.cart_count + ')';
                        // Trigger WooCommerce cart fragment refresh
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                        setTimeout(function() {
                            btn.classList.remove('added');
                            btn.textContent = originalText;
                        }, 2000);
                    } else {
                        btn.classList.remove('adding');
                        btn.textContent = 'Errore - Riprova';
                        setTimeout(function() { btn.textContent = originalText; }, 2000);
                    }
                })
                .catch(function() {
                    btn.classList.remove('adding');
                    btn.textContent = 'Errore - Riprova';
                    setTimeout(function() { btn.textContent = originalText; }, 2000);
                });
        });

        // ─── Variable product: open modal ───
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-quick-add-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var productId = btn.getAttribute('data-product-id');
            selectedAttrs = {};
            matchedVariation = null;
            content.innerHTML = '<div class="ghb-qa-loading">Caricamento varianti...</div>';
            overlay.classList.add('active');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            fetch(ajaxUrl + '?action=ghb_get_variations&product_id=' + encodeURIComponent(productId))
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (!response.success) {
                        content.innerHTML = '<div class="ghb-qa-loading">Prodotto non trovato</div>';
                        return;
                    }
                    renderModal(response.data, productId);
                })
                .catch(function() {
                    content.innerHTML = '<div class="ghb-qa-loading">Errore di caricamento</div>';
                });
        });

        function renderModal(data, productId) {
            currentVariations = data.variations;
            selectedAttrs = {};

            var html = '';

            // Header
            html += '<div class="ghb-qa-header">';
            html += '<img src="' + escHtml(data.image) + '" alt="' + escHtml(data.title) + '">';
            html += '<div class="ghb-qa-header-info">';
            html += '<div class="ghb-qa-title">' + escHtml(data.title) + '</div>';
            html += '<div class="ghb-qa-price">' + data.price + '</div>';
            html += '</div></div>';

            // Attributes
            html += '<div class="ghb-qa-attributes">';
            data.attributes.forEach(function(attr) {
                html += '<div class="ghb-qa-attr-group" data-attr="' + escHtml(attr.name) + '">';
                html += '<div class="ghb-qa-attr-label">' + escHtml(attr.label) + '</div>';
                html += '<div class="ghb-qa-attr-options">';
                attr.options.forEach(function(opt) {
                    html += '<div class="ghb-qa-attr-option" data-attr="' + escHtml(attr.name) + '" data-value="' + escHtml(opt) + '">' + escHtml(opt) + '</div>';
                });
                html += '</div></div>';
            });
            html += '</div>';

            // Quantity
            html += '<div class="ghb-qa-quantity-row">';
            html += '<span class="ghb-qa-qty-label">Quantit&agrave;</span>';
            html += '<div class="ghb-qa-qty-wrap">';
            html += '<button type="button" class="ghb-qa-qty-btn ghb-qa-qty-minus">&minus;</button>';
            html += '<input type="text" class="ghb-qa-qty-value" value="1" readonly>';
            html += '<button type="button" class="ghb-qa-qty-btn ghb-qa-qty-plus">+</button>';
            html += '</div></div>';

            // Error + ATC
            html += '<div class="ghb-qa-error">Seleziona tutte le opzioni</div>';
            html += '<div class="ghb-qa-footer">';
            html += '<button type="button" class="ghb-qa-add-to-cart" data-product-id="' + escHtml(productId) + '">Seleziona le opzioni</button>';
            html += '</div>';

            content.innerHTML = html;
            updateAvailability();
        }

        // Attribute selection
        document.addEventListener('click', function(e) {
            var option = e.target.closest('.ghb-qa-attr-option:not(.unavailable)');
            if (!option || !modal.contains(option)) return;

            var attrName = option.getAttribute('data-attr');
            var val = option.getAttribute('data-value');

            // Toggle
            if (selectedAttrs[attrName] === val) {
                delete selectedAttrs[attrName];
                option.classList.remove('selected');
            } else {
                selectedAttrs[attrName] = val;
                var siblings = option.parentElement.querySelectorAll('.ghb-qa-attr-option');
                siblings.forEach(function(s) { s.classList.remove('selected'); });
                option.classList.add('selected');
            }

            updateAvailability();
            updateButton();
        });

        function updateAvailability() {
            var groups = modal.querySelectorAll('.ghb-qa-attr-group');
            groups.forEach(function(group) {
                var groupAttr = group.getAttribute('data-attr');
                var options = group.querySelectorAll('.ghb-qa-attr-option');

                options.forEach(function(opt) {
                    var optValue = opt.getAttribute('data-value');
                    var testAttrs = Object.assign({}, selectedAttrs);
                    testAttrs[groupAttr] = optValue;

                    var possible = currentVariations.some(function(v) {
                        return Object.keys(testAttrs).every(function(key) {
                            var vKey = 'attribute_' + key;
                            return !v.attributes[vKey] || v.attributes[vKey] === testAttrs[key];
                        }) && v.is_in_stock;
                    });

                    if (possible) {
                        opt.classList.remove('unavailable');
                    } else {
                        opt.classList.add('unavailable');
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
            matchedVariation = currentVariations.find(function(v) {
                return Object.keys(selectedAttrs).every(function(key) {
                    var vKey = 'attribute_' + key;
                    return !v.attributes[vKey] || v.attributes[vKey] === selectedAttrs[key];
                });
            }) || null;

            if (matchedVariation && matchedVariation.is_in_stock) {
                var priceText = matchedVariation.price_html ? ' \u2013 ' + matchedVariation.price_html : '';
                btn.classList.add('ready');
                btn.textContent = 'Aggiungi al Carrello' + priceText;
                if (errorEl) errorEl.classList.remove('visible');

                if (matchedVariation.image) {
                    var img = modal.querySelector('.ghb-qa-header img');
                    if (img) img.src = matchedVariation.image;
                }
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

        // Quantity controls
        document.addEventListener('click', function(e) {
            if (e.target.closest('.ghb-qa-qty-minus')) {
                var input = e.target.closest('.ghb-qa-qty-wrap').querySelector('.ghb-qa-qty-value');
                var val = parseInt(input.value);
                if (val > 1) input.value = val - 1;
            }
            if (e.target.closest('.ghb-qa-qty-plus')) {
                var input = e.target.closest('.ghb-qa-qty-wrap').querySelector('.ghb-qa-qty-value');
                var val = parseInt(input.value);
                input.value = val + 1;
            }
        });

        // Add to cart from modal
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.ghb-qa-add-to-cart.ready');
            if (!btn || !modal.contains(btn)) return;

            var productId = btn.getAttribute('data-product-id');
            var qtyInput = modal.querySelector('.ghb-qa-qty-value');
            var quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

            if (!matchedVariation) return;

            btn.classList.remove('ready');
            btn.classList.add('adding');
            btn.textContent = 'Aggiunta in corso...';

            var formData = new FormData();
            formData.append('action', 'ghb_add_to_cart');
            formData.append('product_id', productId);
            formData.append('variation_id', matchedVariation.variation_id);
            formData.append('quantity', quantity);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (response.success) {
                        btn.classList.remove('adding');
                        btn.classList.add('added');
                        btn.textContent = 'Aggiunto! (' + response.data.cart_count + ' nel carrello)';
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                        setTimeout(closeModal, 1500);
                    } else {
                        btn.classList.remove('adding');
                        btn.classList.add('ready');
                        btn.textContent = 'Errore \u2013 Riprova';
                    }
                })
                .catch(function() {
                    btn.classList.remove('adding');
                    btn.classList.add('ready');
                    btn.textContent = 'Errore \u2013 Riprova';
                });
        });

        function escHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    })();
    </script>

    <?php
}
