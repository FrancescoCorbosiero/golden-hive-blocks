/**
 * Golden Hive Blocks — Scroll-snap rail controls (progressive enhancement).
 *
 * Drives both the product rail (.gh-rail) and the category slider (.gh-cs):
 * native CSS scroll-snap does the scrolling; this only wires the optional
 * prev/next arrows, disables them at the ends, and advances by a full batch
 * (the number of whole cards visible, so a click moves the whole row). No
 * slider library. On the product rail it also moves the progress line under
 * the row (when the store shows the arrows below it) and lets a mouse drag
 * the row; a drag never ends in a click.
 *
 * Arrow clicks and scrolls are handled once, for the whole document, so a
 * rail works however it got onto the page: rendered with it, fetched later
 * (the product page's "Visti di recente"), or copied in by another script or
 * plugin, which keeps the markup but not the listeners. Which rails are
 * already set up is kept in memory, not in an attribute a copy would carry.
 * window.ghbProductRail.init(root) still sets up rails added later at once.
 */
(function () {
    var RAILS = '.gh-rail, .gh-cs';
    var NAVS = '.gh-rail__nav, .gh-cs__nav';
    var TRACKS = '.gh-rail__track, .gh-cs__track';
    var ready = typeof WeakSet === 'function' ? new WeakSet() : null;

    function partsOf(rail) {
        var isCs = rail.classList.contains('gh-cs');
        return {
            track: rail.querySelector(isCs ? '.gh-cs__track' : '.gh-rail__track'),
            navs: rail.querySelectorAll(isCs ? '.gh-cs__nav' : '.gh-rail__nav'),
            progress: isCs ? null : rail.querySelector('.gh-rail__progress span')
        };
    }

    // Real flex gap from the computed style (columnGap covers `gap` on flex
    // tracks); "normal"/unset parses to NaN → fall back to the historic 16.
    function gap(track) {
        var g = parseFloat(getComputedStyle(track).columnGap);
        return isFinite(g) ? g : 16;
    }

    /** One batch: as many whole cards as are visible. */
    function pageStep(track) {
        var card = track.firstElementChild;
        var step = card ? card.getBoundingClientRect().width + gap(track) : 0;
        if (!step) return track.clientWidth * 0.8;
        var perView = Math.max(1, Math.floor((track.clientWidth + gap(track)) / step));
        return perView * step;
    }

    function update(rail) {
        var p = partsOf(rail);
        if (!p.track) return;
        var max = p.track.scrollWidth - p.track.clientWidth - 1;
        rail.classList.toggle('is-scrollable', max > 1);
        p.navs.forEach(function (n) {
            var dir = parseInt(n.getAttribute('data-dir'), 10);
            n.disabled = (dir < 0 && p.track.scrollLeft <= 2) || (dir > 0 && p.track.scrollLeft >= max);
        });
        if (p.progress && p.track.scrollWidth > 0 && p.track.clientWidth > 0) {
            p.progress.style.width = Math.min(1, p.track.clientWidth / p.track.scrollWidth) * 100 + '%';
            p.progress.style.transform = 'translateX(' + (p.track.scrollLeft / p.track.clientWidth) * 100 + '%)';
        }
    }

    function setUp(rail) {
        if (ready ? ready.has(rail) : rail.__ghbRail) return;
        if (ready) { ready.add(rail); } else { rail.__ghbRail = true; }
        var p = partsOf(rail);
        if (!p.track) return;
        update(rail);
        if (rail.classList.contains('gh-rail')) dragToScroll(p.track);
    }

    function boot(root) {
        var scope = root && root.querySelectorAll ? root : document;
        if (scope.matches && scope.matches(RAILS)) setUp(scope);
        scope.querySelectorAll(RAILS).forEach(setUp);
    }

    // Arrows: one listener for every rail on the page.
    document.addEventListener('click', function (e) {
        var nav = e.target.closest && e.target.closest(NAVS);
        if (!nav || nav.disabled) return;
        var rail = nav.closest(RAILS);
        var track = rail && partsOf(rail).track;
        if (!track) return;
        setUp(rail);
        var dir = parseInt(nav.getAttribute('data-dir'), 10) || 1;
        var step = dir * pageStep(track);
        var from = track.scrollLeft;
        track.scrollBy({ left: step, behavior: 'smooth' });
        // If something cancelled the smooth scroll (a script, a browser quirk
        // with snapping), the click still moves the row.
        window.setTimeout(function () {
            if (Math.abs(track.scrollLeft - from) < 2) {
                track.scrollLeft = from + step;
            }
            update(rail);
        }, 450);
    });

    // Scroll events don't bubble, but they can be caught on the way down.
    document.addEventListener('scroll', function (e) {
        var t = e.target;
        if (!t || !t.matches || !t.matches(TRACKS)) return;
        var rail = t.closest(RAILS);
        if (rail) window.requestAnimationFrame(function () { setUp(rail); update(rail); });
    }, { capture: true, passive: true });

    // A rail first met by the pointer (copied in after load) gets its state.
    document.addEventListener('pointerover', function (e) {
        var rail = e.target.closest && e.target.closest(RAILS);
        if (rail) setUp(rail);
    }, { passive: true });

    window.addEventListener('resize', function () { document.querySelectorAll(RAILS).forEach(update); });
    window.addEventListener('load', function () { boot(); document.querySelectorAll(RAILS).forEach(update); });

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

    window.ghbProductRail = { init: boot };

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', function () { boot(); });
    }
})();
