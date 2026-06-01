/**
 * Golden Hive Blocks — Product rail arrows (progressive enhancement).
 *
 * The rail scrolls natively (CSS scroll-snap); this only wires the optional
 * prev/next arrows and disables them at the ends. No slider library.
 * See includes/product-rail.php.
 */
(function () {
    function initRail(rail) {
        var track = rail.querySelector('.gh-rail__track');
        if (!track) return;
        var navs = rail.querySelectorAll('.gh-rail__nav');

        function cardStep() {
            var card = track.querySelector('li.product');
            var gap = 16;
            return card ? card.getBoundingClientRect().width + gap : track.clientWidth * 0.8;
        }

        function update() {
            var max = track.scrollWidth - track.clientWidth - 1;
            rail.classList.toggle('is-scrollable', max > 1);
            navs.forEach(function (n) {
                var dir = parseInt(n.getAttribute('data-dir'), 10);
                var atStart = track.scrollLeft <= 1;
                var atEnd = track.scrollLeft >= max;
                n.disabled = (dir < 0 && atStart) || (dir > 0 && atEnd);
            });
        }

        navs.forEach(function (n) {
            n.addEventListener('click', function () {
                var dir = parseInt(n.getAttribute('data-dir'), 10);
                track.scrollBy({ left: dir * cardStep() * 2, behavior: 'smooth' });
            });
        });

        track.addEventListener('scroll', function () {
            window.requestAnimationFrame(update);
        }, { passive: true });
        window.addEventListener('resize', update);

        // Re-check once images have laid out (card widths settle).
        window.addEventListener('load', update);
        update();
    }

    function boot() {
        document.querySelectorAll('.gh-rail').forEach(initRail);
    }

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot);
    }
})();
