/**
 * Golden Hive Blocks — the blocks' behaviour.
 *
 * Text splitting, pointer play, countdowns, the social-proof toast, marquees,
 * modals (and the promo modal), the FAQ accordion, the hero carousel, the
 * newsletter form, the WhatsApp button, background video and the shopper's
 * remembered size. Scroll motion (reveals, line splits, image clips) is
 * js/motion.js, loaded before this file.
 *
 * - Every module exposes init(root) and is idempotent: each element gets a
 *   data-gh-init-* guard, so running it again never duplicates listeners,
 *   clones, intervals or observers.
 * - GoldenHive.refresh(root) runs every scan inside `root` again; a batched
 *   MutationObserver on document.body routes [data-gh-*] nodes added later
 *   (Quick View, AJAX filters, infinite scroll) through it.
 * - GoldenHive.lockScroll() / unlockScroll(): a shared, counted scroll lock
 *   that sets html.gh-scroll-lock (styled in style.css).
 */

(function () {
    'use strict';

    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* =====================================================================
     * Utilities
     * =================================================================== */

    /**
     * Query `selector` within `root`, including `root` itself when it matches.
     * Always returns a plain array.
     */
    function scan(root, selector) {
        root = root && root.nodeType ? root : document;
        var out = [];
        if (root.nodeType === 1 && root.matches && root.matches(selector)) {
            out.push(root);
        }
        var found = root.querySelectorAll(selector);
        for (var i = 0; i < found.length; i++) {
            out.push(found[i]);
        }
        return out;
    }

    function cssEscape(value) {
        return (window.CSS && CSS.escape) ? CSS.escape(value) : String(value);
    }

    /* ---------------------------------------------------------------------
     * Shared scroll lock — counted, so two stacked overlays (the promo modal
     * over Quick View) never unlock each other.
     * ------------------------------------------------------------------- */
    var scrollLockCount = 0;

    function lockScroll() {
        scrollLockCount++;
        document.documentElement.classList.add('gh-scroll-lock');
    }

    function unlockScroll() {
        scrollLockCount = Math.max(0, scrollLockCount - 1);
        if (scrollLockCount === 0) {
            document.documentElement.classList.remove('gh-scroll-lock');
        }
    }

    /**
     * Split text — character by character, or word by word.
     * Skips elements that already contain inline markup (links, <br>, …) so
     * author-provided HTML isn't destroyed. With data-gh-split-inline, plain
     * spans that only colour a few words (the hero-video titleAccent) are
     * split through instead, each piece staying inside its span.
     *
     * Characters are graphemes through Intl.Segmenter('it') where available
     * (emoji, combining accents and ligatures stay whole); the Array.from
     * fallback iterates by code point, never by code unit.
     *
     * data-gh-split="lines" is js/motion.js's, not this module's.
     */
    var SplitText = {
        segmenter: null,

        segment: function (text) {
            if (window.Intl && typeof Intl.Segmenter === 'function') {
                if (!this.segmenter) {
                    this.segmenter = new Intl.Segmenter('it', { granularity: 'grapheme' });
                }
                var out = [];
                var iterator = this.segmenter.segment(text);
                // Intl.Segmenter results are iterable.
                for (var it = iterator[Symbol.iterator](), step = it.next(); !step.done; step = it.next()) {
                    out.push(step.value.segment);
                }
                return out;
            }
            return Array.from(text);
        },

        /**
         * The element's text as runs, { text, wrap }: wrap is a bare copy of
         * the inline span the text sits in, null for plain text. Null when
         * the element holds markup that must stay as it is.
         */
        runs: function (el) {
            if (el.children.length === 0) {
                return [{ text: el.textContent.trim(), wrap: null }];
            }
            if (!el.hasAttribute('data-gh-split-inline')) return null;

            var runs = [];
            for (var i = 0; i < el.childNodes.length; i++) {
                var node = el.childNodes[i];
                if (node.nodeType === 3) {
                    runs.push({ text: node.nodeValue, wrap: null });
                } else if (node.nodeType === 1) {
                    // Only text-only spans: anything richer stays untouched.
                    if (node.tagName !== 'SPAN' || node.children.length > 0) return null;
                    runs.push({ text: node.textContent, wrap: node.cloneNode(false) });
                }
            }
            if (!runs.length) return null;
            runs[0].text = runs[0].text.replace(/^\s+/, '');
            runs[runs.length - 1].text = runs[runs.length - 1].text.replace(/\s+$/, '');
            return runs;
        },

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-split]:not([data-gh-split="lines"])').forEach(function (el) {
                if (el.dataset.ghSplitProcessed) return;

                var runs = self.runs(el);
                if (!runs) {
                    el.dataset.ghSplitProcessed = 'true';
                    return;
                }

                var type = el.dataset.ghSplit || 'chars';
                var text = el.textContent.trim();
                if (!text || (type !== 'chars' && type !== 'words')) return;

                // Accessibility: the whole text lives in the container's
                // aria-label; the split spans sit in an aria-hidden wrapper.
                el.setAttribute('aria-label', text);
                var wrapper = document.createElement('span');
                wrapper.setAttribute('aria-hidden', 'true');

                // Pieces of every run: graphemes ("chars") or words and the
                // spaces between them ("words"). Indexes run across the whole
                // text, so the entrance flows on through an accent span.
                var total = 0;
                runs.forEach(function (run) {
                    run.parts = type === 'chars'
                        ? self.segment(run.text)
                        : run.text.split(/(\s+)/).filter(function (part) { return part !== ''; });
                    run.parts.forEach(function (part) {
                        if (!/^\s+$/.test(part)) total++;
                    });
                });

                // Spaces stay plain text, so lines break between words only:
                // each word's .gh-char boxes sit in a .gh-char-word that
                // never breaks ("PIAC / ENZA" otherwise).
                var index = 0;
                runs.forEach(function (run) {
                    var target = run.wrap || wrapper;
                    var word = null;
                    run.parts.forEach(function (part) {
                        if (/^\s+$/.test(part)) {
                            target.appendChild(document.createTextNode(' '));
                            word = null;
                            if (type === 'chars') index++;
                            return;
                        }
                        var span = document.createElement('span');
                        if (type === 'chars') {
                            if (!word) {
                                word = document.createElement('span');
                                word.className = 'gh-char-word';
                                target.appendChild(word);
                            }
                            span.className = 'gh-char';
                            span.style.setProperty('--char-index', String(index));
                            span.style.setProperty('--char-total', String(total));
                            span.textContent = part;
                            word.appendChild(span);
                        } else {
                            span.className = 'gh-word';
                            span.style.setProperty('--word-index', String(index));
                            span.style.setProperty('--word-total', String(total));
                            span.textContent = part;
                            target.appendChild(span);
                        }
                        index++;
                    });
                    if (run.wrap) wrapper.appendChild(run.wrap);
                });

                el.textContent = '';
                el.appendChild(wrapper);
                el.dataset.ghSplitProcessed = 'true';
            });
        }
    };

    /**
     * Magnetic — an element drawn toward the mouse.
     * rAF-throttled so mousemove never writes more than once per frame. The
     * cached rect is refreshed on scroll (a passive listener, only while
     * hovered); on mouseleave the inline transform is removed entirely, so
     * :hover transforms in CSS work again.
     */
    var MagneticCursor = {
        init: function (root) {
            if (prefersReducedMotion || 'ontouchstart' in window) return;

            scan(root, '[data-gh-magnetic]').forEach(function (el) {
                if (el.dataset.ghInitMagnetic) return;
                el.dataset.ghInitMagnetic = 'true';

                var strength = parseFloat(el.dataset.ghMagnetic) || 0.3;
                var bounds = null;
                var targetX = 0, targetY = 0;
                var rafId = 0;

                var apply = function () {
                    rafId = 0;
                    el.style.transform = 'translate3d(' + targetX + 'px, ' + targetY + 'px, 0)';
                };

                var refreshBounds = function () {
                    bounds = el.getBoundingClientRect();
                };

                el.addEventListener('mouseenter', function () {
                    refreshBounds();
                    el.style.transition = 'transform 0.1s ease-out';
                    window.addEventListener('scroll', refreshBounds, { passive: true });
                }, { passive: true });

                el.addEventListener('mousemove', function (e) {
                    if (!bounds) return;
                    targetX = (e.clientX - bounds.left - bounds.width / 2) * strength;
                    targetY = (e.clientY - bounds.top - bounds.height / 2) * strength;
                    if (!rafId) rafId = requestAnimationFrame(apply);
                }, { passive: true });

                el.addEventListener('mouseleave', function () {
                    window.removeEventListener('scroll', refreshBounds);
                    bounds = null;
                    if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
                    el.style.transition = 'transform 0.4s cubic-bezier(0.33, 1, 0.68, 1)';
                    el.style.transform = '';
                    setTimeout(function () { el.style.transition = ''; }, 400);
                }, { passive: true });
            });
        }
    };

    /**
     * Mouse parallax — layers that follow the mouse inside a container
     * (Parallax Section). rAF-throttled.
     */
    var MouseParallax = {
        init: function (root) {
            if (prefersReducedMotion) return;

            scan(root, '[data-gh-mouse-parallax]').forEach(function (container) {
                if (container.dataset.ghInitMouseParallax) return;

                var layers = container.querySelectorAll('[data-gh-mouse-layer]');
                if (!layers.length) return;
                container.dataset.ghInitMouseParallax = 'true';

                var mx = 0, my = 0, rect = null, rafId = 0;

                var apply = function () {
                    rafId = 0;
                    layers.forEach(function (layer) {
                        var depth = parseFloat(layer.dataset.ghMouseLayer) || 30;
                        layer.style.transform = 'translate3d(' + (mx * depth) + 'px, ' + (my * depth) + 'px, 0)';
                    });
                };

                container.addEventListener('mouseenter', function () {
                    rect = container.getBoundingClientRect();
                }, { passive: true });

                container.addEventListener('mousemove', function (e) {
                    if (!rect) rect = container.getBoundingClientRect();
                    mx = (e.clientX - rect.left) / rect.width - 0.5;
                    my = (e.clientY - rect.top) / rect.height - 0.5;
                    if (!rafId) rafId = requestAnimationFrame(apply);
                }, { passive: true });

                container.addEventListener('mouseleave', function () {
                    if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
                    layers.forEach(function (layer) {
                        layer.style.transition = 'transform 0.6s cubic-bezier(0.33, 1, 0.68, 1)';
                        layer.style.transform = 'translate3d(0, 0, 0)';
                        setTimeout(function () { layer.style.transition = ''; }, 600);
                    });
                }, { passive: true });
            });
        }
    };

    /**
     * Countdown — digits flip as they change.
     */
    var CountdownTimer = {
        init: function (root) {
            var self = this;
            scan(root, '[data-gh-countdown]').forEach(function (el) {
                if (el.dataset.ghInitCountdown) return;

                var targetDate = new Date(el.dataset.ghCountdown).getTime();
                if (isNaN(targetDate)) return;
                el.dataset.ghInitCountdown = 'true';

                self.update(el, targetDate);
                var interval = setInterval(function () {
                    if (self.update(el, targetDate) <= 0) {
                        clearInterval(interval);
                        self.handleExpired(el);
                    }
                }, 1000);
            });
        },

        update: function (el, targetDate) {
            var remaining = targetDate - Date.now();
            if (remaining <= 0) return remaining;

            var units = {
                giorni: Math.floor(remaining / 86400000),
                ore: Math.floor((remaining % 86400000) / 3600000),
                minuti: Math.floor((remaining % 3600000) / 60000),
                secondi: Math.floor((remaining % 60000) / 1000)
            };

            Object.keys(units).forEach(function (unit) {
                var digitEl = el.querySelector('[data-gh-countdown-' + unit + ']');
                if (!digitEl) return;

                var newVal = String(units[unit]).padStart(2, '0');
                if (digitEl.textContent !== newVal) {
                    digitEl.classList.add('gh-digit-flip');
                    setTimeout(function () {
                        digitEl.textContent = newVal;
                        digitEl.classList.remove('gh-digit-flip');
                    }, 200);
                }
            });

            return remaining;
        },

        handleExpired: function (el) {
            var expired = document.createElement('div');
            expired.className = 'gh-countdown__expired';
            expired.textContent = el.dataset.ghCountdownExpired || 'DISPONIBILE ORA';
            el.textContent = '';
            el.appendChild(expired);
            el.classList.add('gh-countdown--expired');
            // The drop blocks switch to "Acquista ora" (js/live-blocks.js).
            el.dispatchEvent(new CustomEvent('gh:countdown-end', { bubbles: true }));
        }
    };

    /**
     * Social Proof Notification - Shopify style, from real orders.
     * The container carries data-gh-social-proof-src: the ghb_social_proof
     * endpoint (includes/social-proof.php) returns the latest paid orders
     * {product, image, url, city, ts}. "2 ore fa" is worked out here from the
     * payment time, so it stays true on a cached page. Each purchase shows
     * once per session (sessionStorage) and at most data-gh-social-proof-max
     * per page: real data repeated in a loop would look fake. Older markup
     * with hand-written notifications (data-gh-social-proof-items) shows
     * nothing. The product name goes in through textContent, never innerHTML.
     */
    var SocialProof = {
        SEEN_KEY: 'ghb_sp_seen',
        queue: [],
        shown: 0,
        maxPerVisit: 3,
        container: null,
        isHidden: false,
        displayDuration: 6000,
        hideTimer: null,
        cycleTimer: null,

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-social-proof]').forEach(function (container) {
                if (container.dataset.ghInitSocialProof) return;
                container.dataset.ghInitSocialProof = 'true';
                if (self.container) return; // one toast per page
                self.setup(container);
            });
        },

        setup: function (container) {
            var src = container.dataset.ghSocialProofSrc;
            if (!src || !window.fetch) return;

            this.container = container;
            this.displayDuration = parseInt(container.dataset.ghSocialProofDuration, 10) || 6000;
            this.maxPerVisit = parseInt(container.dataset.ghSocialProofMax, 10) || 3;

            var interval = parseInt(container.dataset.ghSocialProofInterval, 10) || 25000;
            var delay = parseInt(container.dataset.ghSocialProofDelay, 10) || 8000;
            var days = parseInt(container.dataset.ghSocialProofDays, 10) || 14;

            // Reduced motion: the toast still shows and hides, without the
            // slide (its transition is switched off inline).
            if (prefersReducedMotion) {
                container.style.transition = 'none';
            }

            var self = this;

            var closeBtn = container.querySelector('[data-gh-social-proof-close]');
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    self.hide();
                    self.isHidden = true; // User dismissed, stop showing
                    if (self.cycleTimer) clearInterval(self.cycleTimer);
                });
            }

            setTimeout(function () {
                fetch(src, { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        var items = json && json.success && json.data && Array.isArray(json.data.items) ? json.data.items : [];
                        self.queue = self.fresh(items, days);
                        if (!self.queue.length || self.isHidden) return;
                        self.show();
                        self.cycleTimer = setInterval(function () {
                            if (self.isHidden || !self.show()) clearInterval(self.cycleTimer);
                        }, interval);
                    })
                    .catch(function () {});
            }, delay);
        },

        // Purchases inside the window, not yet seen in this session.
        fresh: function (items, days) {
            var seen = this.seen();
            var since = Date.now() / 1000 - days * 86400;
            return items.filter(function (n) {
                return n && n.product && n.ts >= since && seen.indexOf(SocialProof.keyOf(n)) === -1;
            });
        },

        keyOf: function (n) {
            return String(n.ts) + '|' + n.product;
        },

        seen: function () {
            try {
                var list = JSON.parse(sessionStorage.getItem(this.SEEN_KEY) || '[]');
                return Array.isArray(list) ? list : [];
            } catch (e) {
                return [];
            }
        },

        markSeen: function (n) {
            try {
                var list = this.seen();
                list.push(this.keyOf(n));
                sessionStorage.setItem(this.SEEN_KEY, JSON.stringify(list.slice(-50)));
            } catch (e) { /* storage off: worst case a purchase shows again */ }
        },

        // "adesso", "12 minuti fa", "3 ore fa", "ieri", "5 giorni fa".
        ago: function (ts) {
            var s = Math.max(0, Math.floor(Date.now() / 1000 - ts));
            if (s < 60) return 'adesso';
            var m = Math.floor(s / 60);
            if (m < 60) return m === 1 ? '1 minuto fa' : m + ' minuti fa';
            var h = Math.floor(m / 60);
            if (h < 24) return h === 1 ? '1 ora fa' : h + ' ore fa';
            var d = Math.floor(h / 24);
            return d === 1 ? 'ieri' : d + ' giorni fa';
        },

        // Shows the next purchase; false once nothing is left to show.
        show: function () {
            if (this.isHidden || !this.container) return false;
            if (this.shown >= this.maxPerVisit || !this.queue.length) return false;

            var n = this.queue.shift();
            this.shown++;
            this.markSeen(n);

            // Update content — textContent only, never interpolated HTML.
            var productEl = this.container.querySelector('[data-gh-social-proof-product]');
            var metaEl = this.container.querySelector('[data-gh-social-proof-meta]');
            var imageEl = this.container.querySelector('[data-gh-social-proof-image]');
            var linkEl = this.container.querySelector('[data-gh-social-proof-link]');

            if (productEl) productEl.textContent = n.product || '';

            if (metaEl) {
                metaEl.textContent = [n.city || '', this.ago(n.ts)].filter(Boolean).join(' · ');
            }

            if (imageEl) {
                var imageWrapper = imageEl.closest('.gh-social-proof__image');
                if (n.image) {
                    imageEl.src = n.image;
                    imageEl.alt = n.product || '';
                    if (imageWrapper) imageWrapper.style.display = '';
                } else {
                    imageEl.removeAttribute('src');
                    if (imageWrapper) imageWrapper.style.display = 'none';
                }
            }

            if (linkEl) {
                if (n.url) {
                    linkEl.href = n.url;
                    linkEl.setAttribute('aria-label', 'Vedi ' + (n.product || 'il prodotto'));
                    linkEl.hidden = false;
                } else {
                    linkEl.hidden = true;
                }
            }

            this.container.classList.add('is-visible');
            var self = this;
            if (this.hideTimer) clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(function () { self.hide(); }, this.displayDuration);
            return true;
        },

        hide: function () {
            if (this.container) this.container.classList.remove('is-visible');
        }
    };

    /**
     * Marquee. style.css owns @keyframes gh-marquee and the rules on
     * [data-gh-marquee-track] (duration from var(--gh-marquee-duration),
     * direction from [data-gh-marquee-direction="right"], pause on hover from
     * [data-gh-marquee-pause]). This only clones the track once (the clone is
     * aria-hidden) and sets --gh-marquee-duration = scrollWidth / speed,
     * recomputed by a ResizeObserver (logos loading change the width), with
     * window 'load' as a fallback.
     */
    var Marquee = {
        init: function (root) {
            scan(root, '[data-gh-marquee]').forEach(function (container) {
                if (container.dataset.ghInitMarquee) return;

                var track = container.querySelector('[data-gh-marquee-track]');
                if (!track) return;
                container.dataset.ghInitMarquee = 'true';

                // Reduced motion: no clone and no animation; the track stays
                // still (the CSS switches the animation off).
                if (prefersReducedMotion) return;

                // Clone the track once (idempotent even if the markup was
                // saved with the clone already in it).
                if (!container.querySelector('[data-gh-marquee-track][aria-hidden="true"]')) {
                    var clone = track.cloneNode(true);
                    clone.setAttribute('aria-hidden', 'true');
                    track.parentNode.insertBefore(clone, track.nextSibling);
                }

                var speed = parseInt(container.dataset.ghMarqueeSpeed) || 50; // px/s
                var setDuration = function () {
                    var width = track.scrollWidth;
                    if (!width) return;
                    // On the container, so the track and the clone both inherit it.
                    container.style.setProperty('--gh-marquee-duration', (width / speed) + 's');
                };

                setDuration();

                if ('ResizeObserver' in window) {
                    var ro = new ResizeObserver(setDuration);
                    ro.observe(track);
                }
                // Fallback: recompute once the page (logos included) has loaded.
                window.addEventListener('load', setDuration);
            });
        }
    };

    /**
     * Modals (the data-gh-modal contract, also used by the bank-details drawer).
     * - aria-hidden: closed markup ships with aria-hidden="true"; open() sets
     *   "false", close() puts it back.
     * - Focus: remembers document.activeElement, moves focus to the
     *   [data-gh-modal-close] button, traps Tab inside, restores it on close.
     * - Escape closes only the topmost modal (a stack).
     * - Scroll: GoldenHive.lockScroll() / unlockScroll(), never
     *   body.style.overflow.
     */
    var Modal = {
        stack: [],
        keydownBound: false,

        init: function (root) {
            var self = this;

            scan(root, '[data-gh-modal-trigger]').forEach(function (trigger) {
                if (trigger.dataset.ghInitModalTrigger) return;
                trigger.dataset.ghInitModalTrigger = 'true';
                trigger.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.open(trigger.dataset.ghModalTrigger);
                });
            });

            scan(root, '[data-gh-modal-close]').forEach(function (btn) {
                if (btn.dataset.ghInitModalClose) return;
                btn.dataset.ghInitModalClose = 'true';
                btn.addEventListener('click', function () {
                    var modal = btn.closest('[data-gh-modal]');
                    if (modal) self.close(modal.dataset.ghModal);
                });
            });

            scan(root, '[data-gh-modal]').forEach(function (modal) {
                if (modal.dataset.ghInitModal) return;
                modal.dataset.ghInitModal = 'true';
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) self.close(modal.dataset.ghModal);
                });
            });

            if (!this.keydownBound) {
                this.keydownBound = true;
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        var top = self.stack[self.stack.length - 1];
                        if (top) {
                            self.close(top.modal.dataset.ghModal);
                        } else {
                            // Fallback: a modal opened outside the stack (older markup).
                            var open = document.querySelector('[data-gh-modal].gh-modal--open, [data-gh-modal].gh-promo-modal--open');
                            if (open) self.close(open.dataset.ghModal);
                        }
                    } else if (e.key === 'Tab') {
                        self.trapTab(e);
                    }
                });
            }
        },

        getFocusable: function (modal) {
            var selector = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            var nodes = modal.querySelectorAll(selector);
            var out = [];
            for (var i = 0; i < nodes.length; i++) {
                var n = nodes[i];
                if (n.offsetWidth || n.offsetHeight || n.getClientRects().length) {
                    out.push(n);
                }
            }
            return out;
        },

        trapTab: function (e) {
            var top = this.stack[this.stack.length - 1];
            if (!top) return;

            var items = this.getFocusable(top.modal);
            if (!items.length) {
                e.preventDefault();
                return;
            }

            var first = items[0];
            var last = items[items.length - 1];
            var inside = top.modal.contains(document.activeElement);

            if (e.shiftKey) {
                if (!inside || document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (!inside || document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        },

        open: function (id) {
            var modal = document.querySelector('[data-gh-modal="' + cssEscape(id) + '"]');
            if (!modal) return;

            // Already open: don't lock again or push it twice.
            for (var i = 0; i < this.stack.length; i++) {
                if (this.stack[i].modal === modal) return;
            }

            var lastFocus = document.activeElement;

            if (modal.classList.contains('gh-promo-modal')) {
                modal.classList.add('gh-promo-modal--open');
            } else {
                modal.classList.add('gh-modal--open');
            }
            modal.setAttribute('aria-hidden', 'false');

            this.stack.push({ modal: modal, lastFocus: lastFocus });
            lockScroll();

            var closeBtn = modal.querySelector('[data-gh-modal-close]');
            if (closeBtn) {
                closeBtn.focus();
            } else {
                if (!modal.hasAttribute('tabindex')) modal.setAttribute('tabindex', '-1');
                modal.focus();
            }
        },

        close: function (id) {
            var modal = document.querySelector('[data-gh-modal="' + cssEscape(id) + '"]');
            if (!modal) return;

            modal.classList.remove('gh-modal--open', 'gh-promo-modal--open');
            modal.setAttribute('aria-hidden', 'true');

            for (var i = this.stack.length - 1; i >= 0; i--) {
                if (this.stack[i].modal === modal) {
                    var entry = this.stack.splice(i, 1)[0];
                    unlockScroll();
                    if (entry.lastFocus && typeof entry.lastFocus.focus === 'function' && entry.lastFocus.isConnected) {
                        entry.lastFocus.focus();
                    }
                    break;
                }
            }
        }
    };

    /**
     * Whether another overlay is already open: a plugin modal, the shared
     * scroll lock (Quick View, Quick Add and live search use it), or the
     * Quick View (.ghb-qv-modal.active) and live-search
     * (#rlv-search-modal.is-open) overlays themselves.
     */
    function overlayActive() {
        if (scrollLockCount > 0 || Modal.stack.length > 0) return true;
        if (document.documentElement.classList.contains('gh-scroll-lock')) return true;
        if (document.querySelector('.gh-modal--open, .gh-promo-modal--open')) return true;
        if (document.querySelector('.ghb-qv-modal.active, #rlv-search-modal.is-open')) return true;
        if (document.querySelector('[role="dialog"][aria-hidden="false"]')) return true;
        return false;
    }

    /**
     * Promo modal — opens by itself after data-gh-promo-delay milliseconds.
     * If another overlay is open at that moment it waits instead of stacking
     * (a retry every 5 s, three at most).
     */
    var PromoModal = {
        init: function (root) {
            scan(root, '[data-gh-promo-modal]').forEach(function (modal) {
                if (modal.dataset.ghInitPromo) return;
                modal.dataset.ghInitPromo = 'true';

                var delay = parseInt(modal.dataset.ghPromoDelay) || 5000; // ms
                // The editor once saved seconds by mistake, so values like
                // 5–60 are in saved posts. A real delay under 100 ms makes no
                // sense: read it as seconds.
                if (delay > 0 && delay < 100) delay *= 1000;
                var showOnce = modal.dataset.ghPromoOnce === 'true';
                var storageKey = 'gh_promo_' + modal.dataset.ghModal + '_shown';

                if (showOnce) {
                    try {
                        if (localStorage.getItem(storageKey)) return;
                    } catch (e) { /* storage blocked: show it anyway */ }
                }

                var attempts = 0;
                var tryOpen = function () {
                    if (overlayActive()) {
                        attempts++;
                        if (attempts <= 3) setTimeout(tryOpen, 5000);
                        return;
                    }
                    Modal.open(modal.dataset.ghModal);
                    if (showOnce) {
                        try { localStorage.setItem(storageKey, 'true'); } catch (e) { /* noop */ }
                    }
                };

                setTimeout(tryOpen, delay);
            });
        }
    };

    /**
     * FAQ accordion.
     * - aria-expanded on the trigger.
     * - Once opened, max-height becomes 'none' so the answer is never clipped
     *   if its height changes (images, resize); before closing, the measured
     *   height is put back so the transition has somewhere to start.
     */
    var FAQ = {
        init: function (root) {
            scan(root, '[data-gh-faq]').forEach(function (container) {
                var items = container.querySelectorAll('[data-gh-faq-item]');

                var closeItem = function (item) {
                    var trigger = item.querySelector('[data-gh-faq-trigger]');
                    var content = item.querySelector('[data-gh-faq-content]');
                    if (content && item.classList.contains('gh-faq__item--open')) {
                        if (content.style.maxHeight === 'none' || content.style.maxHeight === '') {
                            content.style.maxHeight = content.scrollHeight + 'px';
                            void content.offsetHeight; // reflow, so the transition starts
                        }
                        content.style.maxHeight = '0';
                    } else if (content) {
                        content.style.maxHeight = '0';
                    }
                    item.classList.remove('gh-faq__item--open');
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                };

                var openItem = function (item) {
                    var trigger = item.querySelector('[data-gh-faq-trigger]');
                    var content = item.querySelector('[data-gh-faq-content]');
                    item.classList.add('gh-faq__item--open');
                    if (trigger) trigger.setAttribute('aria-expanded', 'true');
                    if (!content) return;

                    content.style.maxHeight = content.scrollHeight + 'px';

                    var onEnd = function (ev) {
                        if (ev.target !== content || ev.propertyName !== 'max-height') return;
                        content.removeEventListener('transitionend', onEnd);
                        // Only if it is still open: stop clipping.
                        if (item.classList.contains('gh-faq__item--open')) {
                            content.style.maxHeight = 'none';
                        }
                    };
                    content.addEventListener('transitionend', onEnd);
                };

                items.forEach(function (item) {
                    var trigger = item.querySelector('[data-gh-faq-trigger]');
                    var content = item.querySelector('[data-gh-faq-content]');
                    if (!trigger || !content) return;
                    if (trigger.dataset.ghInitFaq) return;
                    trigger.dataset.ghInitFaq = 'true';

                    trigger.setAttribute('aria-expanded', item.classList.contains('gh-faq__item--open') ? 'true' : 'false');

                    trigger.addEventListener('click', function () {
                        var isOpen = item.classList.contains('gh-faq__item--open');

                        // Close the others
                        if (container.dataset.ghFaqMultiple !== 'true') {
                            items.forEach(function (i) {
                                if (i !== item) closeItem(i);
                            });
                        }

                        if (!isOpen) {
                            openItem(item);
                        } else {
                            closeItem(item);
                        }
                    });
                });
            });
        }
    };

    /**
     * Hero carousel with crossfade.
     * - No autoplay under prefers-reduced-motion.
     * - Pauses while the tab is hidden (visibilitychange) and while the
     *   carousel is off screen (one shared IntersectionObserver).
     * - aria-current="true" on the active dot; aria-hidden + inert on the
     *   inactive slides (the markup already ships them on slides 2+).
     * - Swipes more vertical than horizontal are ignored.
     * - Slides 2+ stay display:none (style.css) until slide 1's image — the
     *   LCP element — has loaded: only then does the carousel get --ready,
     *   their images start and autoplay starts counting. Stacked in the
     *   viewport, loading="lazy" didn't hold them back and they all
     *   downloaded with the first.
     */
    var HeroCarousel = {
        instances: [],
        io: null,
        visibilityBound: false,

        init: function (root) {
            var self = this;

            scan(root, '[data-gh-hero-carousel]').forEach(function (carousel) {
                if (carousel.dataset.ghInitHero) return;

                var slides = carousel.querySelectorAll('[data-gh-hero-slide]');
                if (slides.length < 2) return;
                carousel.dataset.ghInitHero = 'true';

                var dots = carousel.querySelector('[data-gh-hero-dots]');
                var prev = carousel.querySelector('[data-gh-hero-prev]');
                var next = carousel.querySelector('[data-gh-hero-next]');

                var parsed = parseInt(carousel.dataset.ghHeroAutoplay);
                var autoplay = isNaN(parsed) ? 6000 : parsed;
                if (prefersReducedMotion) autoplay = 0;

                var state = {
                    carousel: carousel,
                    current: 0,
                    timer: null,
                    visible: true,
                    hover: false,
                    sync: null
                };

                var applyState = function () {
                    slides.forEach(function (slide, i) {
                        var active = i === state.current;
                        slide.classList.toggle('gh-hero-slide--active', active);
                        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                        if (active) {
                            slide.removeAttribute('inert');
                            try { slide.inert = false; } catch (e) { /* noop */ }
                        } else {
                            slide.setAttribute('inert', '');
                            try { slide.inert = true; } catch (e) { /* noop */ }
                        }
                    });

                    if (dots) {
                        dots.querySelectorAll('button').forEach(function (d, i) {
                            var active = i === state.current;
                            d.classList.toggle('gh-hero-dot--active', active);
                            if (active) {
                                d.setAttribute('aria-current', 'true');
                            } else {
                                d.removeAttribute('aria-current');
                            }
                        });
                    }
                };

                var ready = false;

                var stop = function () {
                    if (state.timer) { clearInterval(state.timer); state.timer = null; }
                };

                var start = function () {
                    stop();
                    if (!ready || !autoplay || state.hover || !state.visible || document.hidden) return;
                    state.timer = setInterval(function () { goTo(state.current + 1); }, autoplay);
                };

                var markReady = function () {
                    if (ready) return;
                    ready = true;
                    carousel.classList.add('gh-hero-carousel--ready');
                    start();
                };

                var goTo = function (index) {
                    // Navigating before --ready: show the slides now and force a
                    // reflow, so the new slide starts from opacity 0 and the
                    // crossfade stays intact.
                    if (!ready) {
                        markReady();
                        void carousel.offsetWidth;
                    }
                    state.current = (index + slides.length) % slides.length;
                    applyState();
                    start(); // restart the timer
                };

                state.sync = start;

                // Dots
                if (dots && !dots.querySelector('button')) {
                    slides.forEach(function (_, i) {
                        var btn = document.createElement('button');
                        btn.className = 'gh-hero-dot';
                        btn.setAttribute('aria-label', 'Slide ' + (i + 1));
                        btn.addEventListener('click', function () { goTo(i); });
                        dots.appendChild(btn);
                    });
                }

                if (prev) prev.addEventListener('click', function () { goTo(state.current - 1); });
                if (next) next.addEventListener('click', function () { goTo(state.current + 1); });

                // Touch — mostly vertical swipes are scrolls: ignore them.
                var startX = 0, startY = 0;
                carousel.addEventListener('touchstart', function (e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                }, { passive: true });
                carousel.addEventListener('touchend', function (e) {
                    var deltaX = startX - e.changedTouches[0].clientX;
                    var deltaY = startY - e.changedTouches[0].clientY;
                    if (Math.abs(deltaY) >= Math.abs(deltaX)) return;
                    if (Math.abs(deltaX) > 50) goTo(state.current + (deltaX > 0 ? 1 : -1));
                }, { passive: true });

                // Pause on hover
                carousel.addEventListener('mouseenter', function () { state.hover = true; stop(); });
                carousel.addEventListener('mouseleave', function () { state.hover = false; start(); });

                self.instances.push(state);

                // One shared observer: pause off screen.
                if ('IntersectionObserver' in window) {
                    if (!self.io) {
                        self.io = new IntersectionObserver(function (entries) {
                            entries.forEach(function (entry) {
                                for (var i = 0; i < self.instances.length; i++) {
                                    if (self.instances[i].carousel === entry.target) {
                                        self.instances[i].visible = entry.isIntersecting;
                                        self.instances[i].sync();
                                        break;
                                    }
                                }
                            });
                        }, { threshold: 0.1 });
                    }
                    self.io.observe(carousel);
                }

                applyState();

                // --ready once slide 1's image has arrived (or failed); window
                // load as a safety net.
                var firstImg = slides[0].querySelector('.gh-hero-slide__bg img');
                if (!firstImg || firstImg.complete) {
                    markReady();
                } else {
                    firstImg.addEventListener('load', markReady);
                    firstImg.addEventListener('error', markReady);
                    window.addEventListener('load', markReady);
                }
            });

            if (!this.visibilityBound) {
                this.visibilityBound = true;
                document.addEventListener('visibilitychange', function () {
                    self.instances.forEach(function (inst) { inst.sync(); });
                });
            }
        }
    };

    /**
     * Newsletter form — a real signup over admin-ajax.
     * The form carries data-gh-newsletter-url and data-gh-newsletter-nonce;
     * it POSTs FormData {action: 'ghb_newsletter_subscribe', nonce, email,
     * sizes[], brands[]}; the JSON answer's data.message goes into the
     * aria-live feedback element.
     */
    var Newsletter = {
        init: function (root) {
            scan(root, '[data-gh-newsletter-form]').forEach(function (form) {
                if (form.dataset.ghInitNewsletter) return;
                form.dataset.ghInitNewsletter = 'true';

                var input = form.querySelector('[data-gh-newsletter-input]');
                var feedback = (form.parentElement && form.parentElement.querySelector('[data-gh-newsletter-feedback]')) ||
                    form.querySelector('[data-gh-newsletter-feedback]');
                var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');

                var setFeedback = function (message, ok) {
                    if (!feedback) return;
                    feedback.textContent = message;
                    feedback.className = 'gh-newsletter__feedback gh-newsletter__feedback--' + (ok ? 'success' : 'error');
                };

                // Size alerts: at most three sizes per signup.
                form.addEventListener('change', function (e) {
                    var box = e.target;
                    if (!box.matches || !box.matches('[data-gh-newsletter-size]') || !box.checked) return;
                    if (form.querySelectorAll('[data-gh-newsletter-size]:checked').length > 3) {
                        box.checked = false;
                        setFeedback('Puoi scegliere fino a 3 taglie.', false);
                    }
                });

                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    if (!input) return;
                    var email = input.value.trim();
                    if (!email) return;

                    var url = form.dataset.ghNewsletterUrl;
                    var nonce = form.dataset.ghNewsletterNonce || '';

                    // Older markup without an endpoint: optimistic feedback.
                    if (!url) {
                        setFeedback('Iscrizione completata! Grazie per esserti unito alla nostra community.', true);
                        input.value = '';
                        return;
                    }

                    if (form.dataset.ghNewsletterPending) return;
                    form.dataset.ghNewsletterPending = 'true';

                    var originalLabel = '';
                    if (submitBtn) {
                        originalLabel = submitBtn.tagName === 'INPUT' ? submitBtn.value : submitBtn.textContent;
                        submitBtn.disabled = true;
                        if (submitBtn.tagName === 'INPUT') {
                            submitBtn.value = 'Invio…';
                        } else {
                            submitBtn.textContent = 'Invio…';
                        }
                    }

                    var done = function () {
                        delete form.dataset.ghNewsletterPending;
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            if (submitBtn.tagName === 'INPUT') {
                                submitBtn.value = originalLabel;
                            } else {
                                submitBtn.textContent = originalLabel;
                            }
                        }
                    };

                    var body = new FormData();
                    body.append('action', 'ghb_newsletter_subscribe');
                    body.append('nonce', nonce);
                    body.append('email', email);

                    // Size alerts (includes/size-alerts.php): checked chips, or
                    // the hidden size of the "Nella tua taglia" empty state.
                    var sizeLabel = '';
                    form.querySelectorAll('[data-gh-newsletter-size]').forEach(function (inp) {
                        if (inp.type !== 'hidden' && !inp.checked) return;
                        body.append('sizes[]', inp.value);
                        sizeLabel = sizeLabel || inp.getAttribute('data-label') || inp.value;
                    });
                    form.querySelectorAll('[data-gh-newsletter-brand]:checked').forEach(function (inp) {
                        body.append('brands[]', inp.value);
                    });

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: body
                    }).then(function (response) {
                        return response.json();
                    }).then(function (json) {
                        var ok = !!(json && json.success);
                        var message = (json && json.data && json.data.message) ||
                            (ok ? 'Iscrizione completata!' : 'Si è verificato un errore — riprova.');
                        setFeedback(message, ok);
                        if (ok) {
                            input.value = '';
                            if (sizeLabel) SizeMemory.save(sizeLabel);
                        }
                        done();
                    }).catch(function () {
                        setFeedback('Errore di rete — riprova.', false);
                        done();
                    });
                });
            });
        }
    };

    /**
     * WhatsApp button — appears after 300px of scrolling.
     * Hidden only while JavaScript runs (html.gh-js in CSS), so without it
     * the button is always there; shown through .gh-whatsapp--visible, no
     * inline style. The passive scroll listener removes itself once shown.
     */
    var WhatsAppButton = {
        init: function (root) {
            scan(root, '[data-gh-whatsapp]').forEach(function (btn) {
                if (btn.dataset.ghInitWhatsapp) return;
                btn.dataset.ghInitWhatsapp = 'true';

                var reveal = function () {
                    btn.classList.add('gh-whatsapp--visible');
                };

                if (window.scrollY > 300) { reveal(); return; }

                var onScroll = function () {
                    if (window.scrollY > 300) {
                        reveal();
                        window.removeEventListener('scroll', onScroll);
                    }
                };
                window.addEventListener('scroll', onScroll, { passive: true });
            });
        }
    };

    /**
     * Background video — [data-gh-video-bg].
     * A muted autoplay video: under prefers-reduced-motion it stays paused on
     * its poster; otherwise one shared IntersectionObserver pauses it off
     * screen and plays it again when it comes back.
     */
    var HeroVideo = {
        io: null,

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-video-bg]').forEach(function (el) {
                var video = el.tagName === 'VIDEO' ? el : el.querySelector('video');
                if (!video || video.dataset.ghInitVideoBg) return;
                video.dataset.ghInitVideoBg = 'true';

                if (prefersReducedMotion) {
                    video.removeAttribute('autoplay');
                    try { video.pause(); } catch (e) { /* noop */ }
                    return;
                }

                if (!('IntersectionObserver' in window)) return;

                if (!self.io) {
                    self.io = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            var v = entry.target;
                            if (entry.isIntersecting) {
                                var p = v.play();
                                if (p && typeof p.catch === 'function') p.catch(function () { /* autoplay blocked */ });
                            } else {
                                v.pause();
                            }
                        });
                    }, { threshold: 0.05 });
                }

                self.io.observe(video);
            });
        }
    };

    /* =====================================================================
     * Refresh / boot
     * =================================================================== */

    /**
     * Run every scan inside `root` (default: document) again. Safe to call
     * any number of times: every init is idempotent. Scroll motion is
     * js/motion.js's.
     */
    var refresh = function (root) {
        root = root || document;
        if (window.GoldenHiveMotion) window.GoldenHiveMotion.init(root);
        SplitText.init(root);
        MagneticCursor.init(root);
        MouseParallax.init(root);
        CountdownTimer.init(root);
        SocialProof.init(root);
        Marquee.init(root);
        Modal.init(root);
        PromoModal.init(root);
        FAQ.init(root);
        HeroCarousel.init(root);
        Newsletter.init(root);
        WhatsAppButton.init(root);
        HeroVideo.init(root);
    };

    /**
     * Size memory — this browser's size, for "Nella tua taglia"
     * (includes/size-rail.php, js/live-blocks.js). Updated wherever the
     * shopper picks a size: the shop's size filter (one value in the URL),
     * Quick View, Quick Add, the product page, the newsletter. Stored as the
     * normalized key, the same as ghb_normalize_size_key() in PHP ("36 2/3"
     * and "36-2-3" → "3623"), plus the label to show.
     */
    var SizeMemory = {
        STORE_KEY: 'ghb_my_size',
        // The size attribute's field (includes/size-match.php ghb_size_attribute()).
        FIELD: 'attribute_' + ((window.ghbSize && window.ghbSize.attribute) || 'pa_taglia'),
        started: false,

        key: function (label) {
            return String(label || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        },

        save: function (label) {
            var key = this.key(label);
            if (!key) return;
            try {
                localStorage.setItem(this.STORE_KEY, JSON.stringify({ key: key, label: String(label).trim(), t: Date.now() }));
            } catch (e) { /* storage off: the rail just asks again */ }
        },

        get: function () {
            try {
                var v = JSON.parse(localStorage.getItem(this.STORE_KEY) || 'null');
                return v && typeof v.key === 'string' && v.key ? v : null;
            } catch (e) {
                return null;
            }
        },

        init: function () {
            if (this.started) return;
            this.started = true;
            var self = this;
            var attr = this.FIELD.replace(/^attribute_/, '');

            document.addEventListener('click', function (e) {
                var pick = e.target.closest && e.target.closest(
                    '.ghb-qv-size[data-variation-id], .ghb-qa-attr-option[data-attr="' + attr + '"]'
                );
                if (pick && !pick.disabled) self.save(pick.textContent.trim());
            });

            document.addEventListener('change', function (e) {
                var select = e.target;
                if (!select.matches || !select.matches('form.variations_form select[name="' + self.FIELD + '"]')) return;
                if (select.value && select.selectedIndex >= 0) {
                    self.save(select.options[select.selectedIndex].text.trim());
                }
            });

            // One size in the shop's size filter (filter_taglia=42).
            try {
                var filtered = new URLSearchParams(location.search).get('filter_' + attr.replace(/^pa_/, ''));
                if (filtered && filtered.indexOf(',') === -1) self.save(filtered.replace(/-/g, ' '));
            } catch (e) { /* old browser: nothing to remember */ }
        }
    };

    /**
     * A node added to the page that matches (or holds) one of these goes
     * through refresh() (the MutationObserver below).
     */
    var DYNAMIC_SELECTOR = [
        '[data-gh-reveal]',
        '[data-gh-split]',
        '[data-gh-magnetic]',
        '[data-gh-mouse-parallax]',
        '[data-gh-countdown]',
        '[data-gh-social-proof]',
        '[data-gh-marquee]',
        '[data-gh-modal]',
        '[data-gh-modal-trigger]',
        '[data-gh-modal-close]',
        '[data-gh-promo-modal]',
        '[data-gh-faq]',
        '[data-gh-hero-carousel]',
        '[data-gh-image-reveal]',
        '[data-gh-newsletter-form]',
        '[data-gh-whatsapp]',
        '[data-gh-video-bg]'
    ].join(',');

    var observerStarted = false;

    var startMutationObserver = function () {
        if (observerStarted || !document.body || !('MutationObserver' in window)) return;
        observerStarted = true;

        var queue = [];
        var scheduled = false;

        var flush = function () {
            scheduled = false;
            var nodes = queue;
            queue = [];
            nodes.forEach(function (node) {
                if (!node.isConnected) return;
                if ((node.matches && node.matches(DYNAMIC_SELECTOR)) ||
                    (node.querySelector && node.querySelector(DYNAMIC_SELECTOR))) {
                    refresh(node);
                }
            });
        };

        var mo = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    if (added[j].nodeType === 1) queue.push(added[j]);
                }
            }
            if (queue.length && !scheduled) {
                scheduled = true;
                requestAnimationFrame(flush);
            }
        });

        mo.observe(document.body, { childList: true, subtree: true });
    };

    /**
     * Initialize
     */
    var init = function () {
        // Tell the inline <head> bootstrap the engine has booted, so its
        // failsafe timer won't force-reveal content (which would skip entrance
        // animations). If the failsafe already fired (slow boot), remove the
        // class so this pageview gets its animations back instead of losing
        // them all. If this never runs — script blocked, deferred away, or
        // thrown before here — the failsafe still fires and content is
        // revealed regardless.
        window.__ghAnimReady = true;
        document.documentElement.classList.remove('gh-anim-failsafe');

        refresh(document);
        startMutationObserver();
        SizeMemory.init();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.GoldenHive = {
        init: init,
        refresh: refresh,
        lockScroll: lockScroll,
        unlockScroll: unlockScroll,
        Modal: Modal,
        SocialProof: SocialProof,
        sizeMemory: SizeMemory
    };
})();
