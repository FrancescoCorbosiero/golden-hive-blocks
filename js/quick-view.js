/**
 * Golden Hive Blocks — WooCommerce Quick View modal behaviour.
 *
 * Fetches a product over AJAX and renders it in a modal. The ajax URL and
 * action come from the localized `ghbQuickView` object (see includes/
 * quick-view.php). Class names (.rp-qv-*) are preserved from the original
 * site snippet this was migrated from.
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
                html += '<a href="' + p.url + '" class="rp-qv-view-full">Vedi Prodotto Completo</a>';
                html += '</div></div>';

                $content.html(html);
            }
        });
    });

    // Thumbnail click
    $(document).on('click', '.rp-qv-thumb', function() {
        var src = $(this).data('src');
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        $(this).closest('.rp-qv-gallery').find('.rp-qv-main-img').attr('src', src);
    });
});
