/**
 * Golden Hive Blocks — scroll motion.
 *
 * How sections arrive as the page scrolls. The moves and their rules are the
 * Alpacode motion catalog's (github.com/FrancescoCorbosiero/alpacode-motion:
 * reveal, reveal-stagger, split-lines, clip), rebuilt on IntersectionObserver
 * and the Web Animations API instead of GSAP: the same curves and timings,
 * no library to download (GSAP, ScrollTrigger and SplitText are about 50 KB
 * gzipped; this file is about 2).
 *
 * Hooks
 *   [data-gh-reveal]            the block rises into place: "up" (default),
 *                               "down", "left", "right" or "scale"
 *   [data-gh-reveal="stagger"]  its items land in a cascade, with a little
 *                               bounce: the direct children, or the ones
 *                               data-gh-stagger="<selector>" names
 *   [data-gh-split="lines"]     a heading's lines rise out of their masks
 *   [data-gh-image-reveal]      a photo opens from a clip, its image settling
 *                               from a slight zoom
 *   [data-gh-drift]             a cover image drifts in its frame as the page
 *                               scrolls: CSS only (style.css), no script
 *
 * Rules
 *   - Nothing is hidden by CSS. An element is hidden here, at init, and only
 *     when it starts below the first screen, so a page whose script fails or
 *     never arrives is complete and static.
 *   - What is already painted never moves: the first screen stays as it is.
 *   - "Hidden" is a paused animation, never an inline style. Cancelling or
 *     finishing it hands the element straight back to the stylesheet, so no
 *     CSS transition fires and hover transforms keep working. The moves use
 *     the individual `translate` / `scale` properties, so they never fight an
 *     element's own transform.
 *   - Elements that enter together land as one batch, staggered in page
 *     order. Elements a jump scrolled past (an anchor, find-in-page) show at
 *     once instead of animating out of sight.
 *   - Keyboard focus landing inside a waiting element shows it at once.
 *   - prefers-reduced-motion: nothing is hidden and nothing moves.
 *
 * Loaded on every page before js/animations.js, whose refresh(root) calls
 * GoldenHiveMotion.init(root) for content added later (Quick View, AJAX
 * filters, the size rail).
 */
(function () {
    'use strict';

    var api = { init: function () {}, show: function () {} };
    window.GoldenHiveMotion = api;

    var reduced = !window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || !('IntersectionObserver' in window) || typeof Element.prototype.animate !== 'function') {
        return;
    }

    /* ---------- Settings: the store's voice ---------- */

    // GSAP's power3.out (a quartic ease-out), power4.out (quintic), and a soft
    // overshoot for cascades.
    var EASE = 'cubic-bezier(0.25, 1, 0.5, 1)';
    var EASE_LINES = 'cubic-bezier(0.22, 1, 0.36, 1)';
    var EASE_LAND = 'cubic-bezier(0.34, 1.36, 0.64, 1)';

    var REVEAL = { distance: 44, duration: 1100, stagger: 90, spread: 450 };
    var LINES = { rise: '115%', duration: 1150, stagger: 85 };
    var CASCADE = { distance: 28, duration: 900, stagger: 60, cap: 6 };
    var CLIP = { inset: '14% 12% 14% 12%', zoom: 1.16, duration: 1300 };

    /** Where an element counts as entering: its top passes 90% of the screen. */
    var ENTER_MARGIN = '-10%';
    /** The first screen: an element starting above this line is left alone. */
    var FIRST_VIEW = 0.92;

    /* ---------- Helpers ---------- */

    var individual = !!(window.CSS && CSS.supports && CSS.supports('translate', '1px 1px'));

    /** A keyframe that moves an element by x, y without touching its transform. */
    function shift(x, y) {
        return individual ? { translate: x + ' ' + y } : { transform: 'translate(' + x + ', ' + y + ')' };
    }

    function grow(factor) {
        return individual ? { scale: String(factor) } : { transform: 'scale(' + factor + ')' };
    }

    function merge(a, b) {
        for (var k in b) {
            if (Object.prototype.hasOwnProperty.call(b, k)) a[k] = b[k];
        }
        return a;
    }

    function inFirstView(el) {
        return el.getBoundingClientRect().top < window.innerHeight * FIRST_VIEW;
    }

    function scan(root, selector) {
        var out = root.nodeType === 1 && root.matches(selector) ? [root] : [];
        return out.concat(Array.prototype.slice.call(root.querySelectorAll(selector)));
    }

    function byPage(a, b) {
        return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
    }

    /**
     * Start a move and hold it on its first frame. `fill: backwards` holds
     * that frame through the delay; once the move ends nothing is filled, so
     * the stylesheet is in charge again.
     */
    function held(el, from, timing) {
        var anim = el.animate([merge({ offset: 0 }, from)], merge({ fill: 'backwards' }, timing));
        anim.pause();
        return anim;
    }

    function run(anim, delay) {
        anim.effect.updateTiming({ delay: delay || 0 });
        anim.currentTime = 0;
        anim.play();
    }

    /* ---------- The waiting list ---------- */

    // Element → { play(delay), show() }. Every waiting element also carries
    // data-gh-motion-pending, so focus can find it.
    var waiting = new Map();

    var io = new IntersectionObserver(onEnter, {
        // A huge top margin makes everything above the screen count as
        // "intersecting" too: a jump past an element still reports it, and
        // it is shown at once rather than left waiting.
        rootMargin: '100000px 0px ' + ENTER_MARGIN + ' 0px'
    });

    function wait(el, entry) {
        el.setAttribute('data-gh-motion-pending', '');
        waiting.set(el, entry);
        io.observe(el);
    }

    function release(el) {
        io.unobserve(el);
        waiting.delete(el);
        el.removeAttribute('data-gh-motion-pending');
    }

    function onEnter(entries) {
        var passed = [];
        var entering = [];
        entries.forEach(function (entry) {
            if (!entry.isIntersecting || !waiting.has(entry.target)) return;
            (entry.boundingClientRect.bottom < 0 ? passed : entering).push(entry.target);
        });

        passed.forEach(show);

        // One batch: page order, a short stagger; a long batch is spread
        // over a fixed time so the last block never waits long.
        entering.sort(byPage);
        var n = entering.length;
        entering.forEach(function (el, i) {
            var delay = n > 5 ? i * REVEAL.spread / (n - 1) : i * REVEAL.stagger;
            var entry = waiting.get(el);
            release(el);
            entry.play(delay);
        });
    }

    function show(el) {
        var entry = waiting.get(el);
        if (!entry) return;
        release(el);
        entry.show();
    }

    // Keyboard first: focus inside a waiting element shows it now.
    document.addEventListener('focusin', function (e) {
        var host = e.target.closest && e.target.closest('[data-gh-motion-pending]');
        while (host) {
            show(host);
            host = host.parentElement && host.parentElement.closest('[data-gh-motion-pending]');
        }
    });

    /* ---------- reveal ---------- */

    var DIRECTIONS = {
        up: function (d) { return shift('0px', d + 'px'); },
        down: function (d) { return shift('0px', -d + 'px'); },
        left: function (d) { return shift(-d + 'px', '0px'); },
        right: function (d) { return shift(d + 'px', '0px'); },
        scale: function () { return grow(0.94); }
    };

    function reveal(el) {
        var direction = DIRECTIONS[el.getAttribute('data-gh-reveal')] || DIRECTIONS.up;
        var anim = held(el, merge({ opacity: 0 }, direction(REVEAL.distance)), {
            duration: REVEAL.duration,
            easing: EASE
        });
        wait(el, {
            play: function (delay) {
                el.classList.add('gh-revealed');
                run(anim, delay);
            },
            show: function () {
                el.classList.add('gh-revealed');
                anim.cancel();
            }
        });
    }

    /* ---------- reveal-stagger ---------- */

    function cascade(el) {
        var selector = el.getAttribute('data-gh-stagger');
        var items = selector
            ? Array.prototype.slice.call(el.querySelectorAll(selector))
            : Array.prototype.slice.call(el.children);
        if (!items.length) return;

        var anims = items.map(function (item) {
            return held(item, merge({ opacity: 0 }, shift('0px', CASCADE.distance + 'px')), {
                duration: CASCADE.duration,
                easing: EASE_LAND
            });
        });
        wait(el, {
            play: function (delay) {
                el.classList.add('gh-revealed');
                anims.forEach(function (anim, i) {
                    run(anim, delay + Math.min(i, CASCADE.cap) * CASCADE.stagger);
                });
            },
            show: function () {
                el.classList.add('gh-revealed');
                anims.forEach(function (anim) { anim.cancel(); });
            }
        });
    }

    /* ---------- split-lines ---------- */

    /**
     * Split a plain-text heading into its lines as they wrap right now, each
     * in a mask. Measured at the moment it plays, so fonts and widths are
     * final; put back as plain text when the move ends, so nothing has to
     * be split again on resize.
     */
    function splitIntoLines(el, text) {
        var words = text.split(/\s+/);
        el.textContent = '';
        var spans = words.map(function (word) {
            var span = document.createElement('span');
            span.textContent = word;
            el.appendChild(span);
            el.appendChild(document.createTextNode(' '));
            return span;
        });

        var lines = [];
        var top = null;
        spans.forEach(function (span) {
            var t = span.offsetTop;
            if (top === null || Math.abs(t - top) > 2) {
                lines.push([]);
                top = t;
            }
            lines[lines.length - 1].push(span.textContent);
        });

        el.textContent = '';
        var wrap = document.createElement('span');
        wrap.className = 'gh-lines';
        wrap.setAttribute('aria-hidden', 'true');
        var inners = lines.map(function (line) {
            var mask = document.createElement('span');
            mask.className = 'gh-line';
            var inner = document.createElement('span');
            inner.className = 'gh-line__inner';
            inner.textContent = line.join(' ');
            mask.appendChild(inner);
            wrap.appendChild(mask);
            return inner;
        });
        el.appendChild(wrap);
        return inners;
    }

    function lines(el) {
        // Plain text only: a heading holding markup keeps it.
        if (el.children.length) return;
        var text = el.textContent.replace(/\s+/g, ' ').trim();
        if (!text) return;

        var hold = held(el, { opacity: 0 }, { duration: 1 });
        wait(el, {
            play: function (delay) {
                var label = el.getAttribute('aria-label');
                el.setAttribute('aria-label', text);
                var inners = splitIntoLines(el, text);
                var last = null;
                inners.forEach(function (inner, i) {
                    last = inner.animate([merge({ offset: 0 }, shift('0px', LINES.rise))], {
                        duration: LINES.duration,
                        delay: delay + i * LINES.stagger,
                        easing: EASE_LINES,
                        fill: 'backwards'
                    });
                });
                hold.cancel();
                var restore = function () {
                    el.textContent = text;
                    if (label === null) {
                        el.removeAttribute('aria-label');
                    } else {
                        el.setAttribute('aria-label', label);
                    }
                };
                if (last) {
                    last.onfinish = restore;
                    last.oncancel = restore;
                }
            },
            show: function () {
                hold.cancel();
            }
        });
    }

    /* ---------- clip ---------- */

    function clip(el) {
        var img = el.querySelector('img');
        var radius = getComputedStyle(el).borderTopLeftRadius;
        var round = radius && radius !== '0px' ? ' round ' + radius : '';
        var timing = { duration: CLIP.duration, easing: EASE };

        // The frame opens from a clip and fades in over its first third; the
        // image inside settles from a slight zoom.
        var frame = el.animate([
            { offset: 0, clipPath: 'inset(' + CLIP.inset + round + ')', opacity: 0 },
            { offset: 0.3, opacity: 1 },
            { offset: 1, clipPath: 'inset(0px 0px 0px 0px' + round + ')' }
        ], merge({ fill: 'backwards' }, timing));
        frame.pause();
        var photo = img ? held(img, grow(CLIP.zoom), timing) : null;

        wait(el, {
            play: function (delay) {
                run(frame, delay);
                if (photo) run(photo, delay);
            },
            show: function () {
                frame.cancel();
                if (photo) photo.cancel();
            }
        });
    }

    /* ---------- init ---------- */

    api.init = function (root) {
        root = root && root.nodeType ? root : document;

        var todo = [];
        var add = function (el, fn) {
            if (!el.hasAttribute('data-gh-motion')) todo.push([el, fn]);
        };
        // A photo with a clip opens from it; that is its move, so it skips
        // the plain reveal it may also carry.
        scan(root, '[data-gh-image-reveal]').forEach(function (el) { add(el, clip); });
        scan(root, '[data-gh-split="lines"]').forEach(function (el) { add(el, lines); });
        scan(root, '[data-gh-reveal]').forEach(function (el) {
            if (el.hasAttribute('data-gh-image-reveal')) return;
            add(el, el.getAttribute('data-gh-reveal') === 'stagger' ? cascade : reveal);
        });

        // Read every position first, then write: one layout, not one per element.
        var below = todo.map(function (job) { return !inFirstView(job[0]); });
        todo.forEach(function (job, i) {
            job[0].setAttribute('data-gh-motion', '');
            // The first screen is already painted: leave it as it is.
            if (below[i]) job[1](job[0]);
        });
    };

    api.show = show;

    // Pure helpers, for tests.
    api._splitIntoLines = splitIntoLines;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { api.init(document); });
    } else {
        api.init(document);
    }
})();
