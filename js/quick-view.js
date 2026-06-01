/**
 * Golden Hive Blocks — WooCommerce Quick View modal behaviour.
 *
 * Fetches a product over AJAX and renders it in a modal. The ajax URL/action
 * and the cart endpoint come from the localized `ghbQuickView` object (see
 * includes/quick-view.php). Class names (.rp-qv-*) are preserved from the
 * original site snippet this was migrated from.
 *
 * The modal's add-to-cart reuses WooCommerce's native wc-ajax=add_to_cart
 * endpoint (posting the variation ID for variable products), exactly like the
 * loop size picker — so cart fragments and the mini-cart update natively.
 */
jQuery(function($) {
    var cfg = window.ghbQuickView || {};
    var $overlay = $('.rp-qv-overlay');
    var $modal = $('.rp-qv-modal');
    var $content = $('.rp-qv-content');

    function closeModal() {
        $overlay.removeClass('active');
        $modal.removeClass('active');
        $('body').css('overflow', '');
    }

    $overlay.on('click', closeModal);
    $('.rp-qv-close').on('click', closeModal);
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    $(document).on('click', '.rp-quick-view-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var productId = $(this).data('product-id');

        $content.html('<div class="rp-qv-loading">Caricamento...</div>');
        $overlay.addClass('active');
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        $.ajax({
            url: cfg.ajaxUrl,
            data: { action: cfg.action, product_id: productId },
            success: function(response) {
                if (!response.success) {
                    $content.html('<div class="rp-qv-loading">Prodotto non trovato</div>');
                    return;
                }

                var p = response.data;
                var html = '<div class="rp-qv-body">';

                // Gallery
                html += '<div class="rp-qv-gallery">';
                if (p.images.length) {
                    html += '<img src="' + p.images[0] + '" class="rp-qv-main-img" alt="' + p.title + '">';
                    if (p.images.length > 1) {
                        html += '<div class="rp-qv-thumbs">';
                        p.images.forEach(function(img, i) {
                            html += '<img src="' + img + '" class="rp-qv-thumb' + (i === 0 ? ' active' : '') + '" data-src="' + img + '">';
                        });
                        html += '</div>';
                    }
                }
                html += '</div>';

                // Info
                html += '<div class="rp-qv-info">';
                if (p.categories) html += '<div class="rp-qv-cats">' + p.categories + '</div>';
                html += '<div class="rp-qv-title">' + p.title + '</div>';
                html += '<div class="rp-qv-price">' + p.price + '</div>';
                html += '<div class="rp-qv-stock ' + (p.in_stock ? 'in-stock' : 'out-of-stock') + '">' + p.stock_text + '</div>';
                if (p.short_desc) html += '<div class="rp-qv-desc">' + p.short_desc + '</div>';

                if (p.attributes.length) {
                    html += '<div class="rp-qv-attrs">';
                    p.attributes.forEach(function(a) {
                        html += '<span class="rp-qv-attr"><strong>' + a.label + ':</strong> ' + a.value + '</span>';
                    });
                    html += '</div>';
                }

                if (p.sku) html += '<div class="rp-qv-sku">SKU: ' + p.sku + '</div>';
                html += '<div class="rp-qv-atc"></div>';
                html += '<a href="' + p.url + '" class="rp-qv-view-full">Vedi Prodotto Completo</a>';
                html += '</div></div>';

                $content.html(html);
                renderCartControl(productId, p);
            }
        });
    });

    // Build the add-to-cart control (simple button or size pills) straight from
    // the Quick View payload — no extra request.
    function renderCartControl(productId, p) {
        var $slot = $content.find('.rp-qv-atc');
        if (!$slot.length) return;

        if (p.type === 'variable') {
            if (!p.sizes || !p.sizes.length) {
                $slot.html('<a class="rp-qv-add rp-qv-add--link" href="' + p.url + '">Seleziona opzioni</a>');
                return;
            }
            var h = '<div class="rp-qv-sizes-label">Seleziona taglia</div><div class="rp-qv-sizes">';
            p.sizes.forEach(function(s) {
                if (s.in_stock) {
                    h += '<button type="button" class="rp-qv-size" data-variation-id="' + s.variation_id + '">' + s.label + '</button>';
                } else {
                    h += '<span class="rp-qv-size is-oos">' + s.label + '</span>';
                }
            });
            $slot.html(h + '</div>');
        } else if (p.purchasable) {
            $slot.html('<button type="button" class="rp-qv-add" data-product-id="' + productId + '">Aggiungi al carrello</button>');
        } else if (p.type !== 'simple') {
            $slot.html('<a class="rp-qv-add rp-qv-add--link" href="' + p.url + '">Vedi prodotto</a>');
        } else {
            $slot.html('<button type="button" class="rp-qv-add" disabled>Esaurito</button>');
        }
    }

    function feedback($slot) {
        var $msg = $slot.find('.rp-qv-cart-msg');
        if (!$msg.length) {
            $msg = $('<div class="rp-qv-cart-msg"></div>').appendTo($slot);
        }
        $msg.text('✓ Aggiunto al carrello').addClass('show');
        clearTimeout($slot.data('msgTimer'));
        $slot.data('msgTimer', setTimeout(function() { $msg.removeClass('show'); }, 2000));
    }

    function addToCart($slot, id) {
        if (!cfg.cartEndpoint || !id) return;
        $slot.addClass('is-busy');
        $.post(cfg.cartEndpoint, { product_id: id, quantity: 1 }, function(data) {
            $slot.removeClass('is-busy');
            if (data && data.error && data.product_url) {
                window.location = data.product_url;
                return;
            }
            if (data && data.fragments) {
                $.each(data.fragments, function(key, value) { $(key).replaceWith(value); });
            }
            $(document.body).trigger('added_to_cart', [
                data ? data.fragments : null,
                data ? data.cart_hash : null,
                $slot
            ]);
            feedback($slot);
        });
    }

    // Simple product → add directly.
    $(document).on('click', '.rp-qv-atc .rp-qv-add[data-product-id]', function(e) {
        e.preventDefault();
        addToCart($(this).closest('.rp-qv-atc'), $(this).data('product-id'));
    });

    // Variable product → add the picked size's variation.
    $(document).on('click', '.rp-qv-atc .rp-qv-size[data-variation-id]', function(e) {
        e.preventDefault();
        addToCart($(this).closest('.rp-qv-atc'), $(this).data('variation-id'));
    });

    // Thumbnail click
    $(document).on('click', '.rp-qv-thumb', function() {
        var src = $(this).data('src');
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        $(this).closest('.rp-qv-gallery').find('.rp-qv-main-img').attr('src', src);
    });
});
