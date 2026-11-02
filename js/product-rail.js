/**
 * Golden Hive Blocks — Scroll-snap rail controls (progressive enhancement).
 *
 * Drives both the product rail (.gh-rail) and the category slider (.gh-cs):
 * native CSS scroll-snap does the scrolling; this only wires the optional
 * prev/next arrows, disables them at the ends, and advances by a full batch
 * (the number of whole cards visible, so a click moves the whole row). No
 * slider library. On the product rail it also moves the progress line under
 * the row and lets a mouse drag the row; a drag never ends in a click.
 *
 * Rails added after load (the product page's "Visti di recente", fetched by
 * js/product-page.js) are wired with window.ghbProductRail.init(root).
 */
(function () {
    function initScroller(rail, trackSel, navSel, progressSel) {
        var track = rail.querySelector(trackSel);
        if (!track) return;
        var navs = rail.querySelectorAll(navSel);
        var progress = progressSel ? rail.querySelector(progressSel) : null;
        // Real flex gap from the computed style (columnGap covers `gap` on flex
        // tracks); "normal"/unset parses to NaN → fall back to the historic 16.
        function gap() {
            var g = parseFloat(getComputedStyle(track).columnGap);
            return isFinite(g) ? g : 16;
        }

        function cardStep() {
            var card = track.querySelector(':scope > *');
            return card ? card.getBoundingClientRect().width + gap() : track.clientWidth * 0.8;
        }
        function pageStep() {
            var step = cardStep();
            var perView = Math.max(1, Math.floor((track.clientWidth + gap()) / step));
            return perView * step;
        }
        function update() {
            var max = track.scrollWidth - track.clientWidth - 1;
            rail.classList.toggle('is-scrollable', max > 1);
            navs.forEach(function (n) {
                var dir = parseInt(n.getAttribute('data-dir'), 10);
                n.disabled = (dir < 0 && track.scrollLeft <= 1) || (dir > 0 && track.scrollLeft >= max);
            });
            if (progress && track.scrollWidth > 0 && track.clientWidth > 0) {
                progress.style.width = Math.min(1, track.clientWidth / track.scrollWidth) * 100 + '%';
                progress.style.transform = 'translateX(' + (track.scrollLeft / track.clientWidth) * 100 + '%)';
            }
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
        if (progress) dragToScroll(track);
    }

    // Mouse only: touch already swipes natively.
    function dragToScroll(track) {
        var drag = null;
        var blockClick = false;
        track.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            drag = { x: e.clientX, left: track.scrollLeft, moved: false };
        });
        window.addEventListener('pointermove', function (e) {
            if (!drag) return;
            var dx = e.clientX - drag.x;
            if (!drag.moved && Math.abs(dx) > 6) {
                drag.moved = true;
                track.classList.add('is-dragging');
            }
            if (drag.moved) track.scrollLeft = drag.left - dx;
        });
        window.addEventListener('pointerup', function () {
            if (!drag) return;
            if (drag.moved) {
                blockClick = true;
                window.setTimeout(function () { blockClick = false; }, 0);
            }
            drag = null;
            track.classList.remove('is-dragging'); // snapping comes back and settles the row
        });
        track.addEventListener('click', function (e) {
            if (blockClick) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
        track.addEventListener('dragstart', function (e) { e.preventDefault(); });
    }

    function boot(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.gh-rail').forEach(function (r) {
            if (r.hasAttribute('data-gh-rail-ready')) return;
            r.setAttribute('data-gh-rail-ready', '');
            initScroller(r, '.gh-rail__track', '.gh-rail__nav', '.gh-rail__progress span');
        });
        scope.querySelectorAll('.gh-cs').forEach(function (r) {
            if (r.hasAttribute('data-gh-rail-ready')) return;
            r.setAttribute('data-gh-rail-ready', '');
            initScroller(r, '.gh-cs__track', '.gh-cs__nav', null);
        });
    }

    window.ghbProductRail = { init: boot };

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', function () { boot(); });
    }
})();
