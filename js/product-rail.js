/**
 * Golden Hive Blocks — Scroll-snap rail arrows (progressive enhancement).
 *
 * Drives both the product rail (.gh-rail) and the category slider (.gh-cs):
 * native CSS scroll-snap does the scrolling; this only wires the optional
 * prev/next arrows, disables them at the ends, and advances by a full batch
 * (the number of whole cards visible). No slider library.
 */
(function () {
    function initScroller(rail, trackSel, navSel) {
        var track = rail.querySelector(trackSel);
        if (!track) return;
        var navs = rail.querySelectorAll(navSel);
        var GAP = 16;

        function cardStep() {
            var card = track.querySelector(':scope > *');
            return card ? card.getBoundingClientRect().width + GAP : track.clientWidth * 0.8;
        }
        function pageStep() {
            var step = cardStep();
            var perView = Math.max(1, Math.floor((track.clientWidth + GAP) / step));
            return perView * step;
        }
        function update() {
            var max = track.scrollWidth - track.clientWidth - 1;
            rail.classList.toggle('is-scrollable', max > 1);
            navs.forEach(function (n) {
                var dir = parseInt(n.getAttribute('data-dir'), 10);
                n.disabled = (dir < 0 && track.scrollLeft <= 1) || (dir > 0 && track.scrollLeft >= max);
            });
        }

        navs.forEach(function (n) {
            n.addEventListener('click', function () {
                track.scrollBy({ left: parseInt(n.getAttribute('data-dir'), 10) * pageStep(), behavior: 'smooth' });
            });
        });
        track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
        window.addEventListener('resize', update);
        window.addEventListener('load', update); // re-check once images settle
        update();
    }

    function boot() {
        document.querySelectorAll('.gh-rail').forEach(function (r) {
            initScroller(r, '.gh-rail__track', '.gh-rail__nav');
        });
        document.querySelectorAll('.gh-cs').forEach(function (r) {
            initScroller(r, '.gh-cs__track', '.gh-cs__nav');
        });
    }

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot);
    }
})();
