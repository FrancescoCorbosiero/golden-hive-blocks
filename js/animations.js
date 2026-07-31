/**
 * Golden Hive Blocks - Premium Animation Library
 * Animazioni moderne per e-commerce streetwear
 *
 * Architettura:
 * - Ogni modulo espone init(root) ed è idempotente: ogni elemento riceve un
 *   guard data-gh-init-* così re-inizializzare non duplica listener, cloni,
 *   interval o observer.
 * - GoldenHive.refresh(root) ri-esegue tutte le scansioni dentro `root`;
 *   un MutationObserver batchato su document.body instrada automaticamente
 *   i nodi [data-gh-*] aggiunti via AJAX (quick view, infinite scroll, ecc.)
 *   così il contenuto iniettato non resta mai bloccato a opacity:0.
 * - GoldenHive.lockScroll()/unlockScroll(): lock condiviso a contatore che
 *   attiva html.gh-scroll-lock (la CSS esiste già in style.css).
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
     * Scroll lock condiviso — contatore, così due overlay annidati (es. promo
     * modal sopra quick view) non si rubano lo sblocco a vicenda.
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
     * Split Text Animation - Animazione carattere per carattere
     * Skips elements that already contain inline markup (e.g. a hero title
     * with a highlighted <span>) so author-provided HTML isn't destroyed.
     *
     * La segmentazione usa Intl.Segmenter('it', grapheme) quando disponibile
     * (emoji, accenti combinati e legature restano interi); il fallback
     * Array.from itera per code point, mai per code unit.
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

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-split]').forEach(function (el) {
                if (el.dataset.ghSplitProcessed) return;

                // If the element contains any child elements, don't rewrite it —
                // preserve author markup (inline spans, links, <br>, etc.).
                if (el.children.length > 0) {
                    el.dataset.ghSplitProcessed = 'true';
                    return;
                }

                var type = el.dataset.ghSplit || 'chars';
                var text = el.textContent.trim();
                if (!text) return;

                // Accessibilità: il testo integro vive nell'aria-label del
                // contenitore, gli span frammentati sono nascosti agli screen
                // reader tramite il wrapper aria-hidden.
                el.setAttribute('aria-label', text);
                var wrapper = document.createElement('span');
                wrapper.setAttribute('aria-hidden', 'true');

                if (type === 'chars') {
                    var chars = self.segment(text);
                    var totalChars = 0;
                    chars.forEach(function (c) {
                        if (!/^\s+$/.test(c)) totalChars++;
                    });

                    chars.forEach(function (char, i) {
                        var span = document.createElement('span');
                        if (/^\s+$/.test(char)) {
                            span.className = 'gh-char gh-char--space';
                            span.textContent = '\u00A0';
                        } else {
                            span.className = 'gh-char';
                            span.style.setProperty('--char-index', String(i));
                            span.style.setProperty('--char-total', String(totalChars));
                            span.textContent = char;
                        }
                        wrapper.appendChild(span);
                    });
                } else if (type === 'words') {
                    var words = text.split(/\s+/);
                    words.forEach(function (word, i) {
                        if (i > 0) wrapper.appendChild(document.createTextNode(' '));
                        var span = document.createElement('span');
                        span.className = 'gh-word';
                        span.style.setProperty('--word-index', String(i));
                        span.style.setProperty('--word-total', String(words.length));
                        span.textContent = word;
                        wrapper.appendChild(span);
                    });
                } else {
                    return;
                }

                el.textContent = '';
                el.appendChild(wrapper);
                el.dataset.ghSplitProcessed = 'true';
            });
        }
    };

    /**
     * Magnetic Cursor - Elementi attratti dal mouse
     * rAF-throttled so mousemove never writes more than once per frame.
     * Il rect in cache viene aggiornato durante lo scroll (listener passivo
     * attivo solo mentre l'elemento è in hover); al mouseleave il transform
     * inline viene rimosso del tutto così i transform CSS di :hover tornano
     * a funzionare.
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
     * Mouse Parallax - layer che seguono il mouse dentro un container
     * (usato da parallax-section). Throttled via rAF.
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
     * Scroll Reveal con Animazioni Premium
     * Un solo IntersectionObserver condiviso; lo stagger vive interamente in
     * CSS (transition-delay: var(--gh-reveal-delay)) — niente setTimeout JS.
     */
    var ScrollReveal = {
        observer: null,

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-reveal]').forEach(function (el) {
                if (el.dataset.ghInitReveal) return;
                el.dataset.ghInitReveal = 'true';

                if (prefersReducedMotion || !('IntersectionObserver' in window)) {
                    el.classList.add('gh-revealed');
                    return;
                }

                if (!self.observer) {
                    self.observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('gh-revealed');
                                self.observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });
                }

                self.observer.observe(el);
            });
        }
    };

    /**
     * Countdown Premium con Flip Animation
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
        }
    };

    /**
     * Social Proof Notification - Shopify style
     * Gentle timing, non-aggressive popups.
     * Il nome prodotto va via textContent nel nodo dedicato
     * <strong data-gh-social-proof-product> — mai innerHTML interpolato.
     */
    var SocialProof = {
        notifications: [],
        currentIndex: 0,
        container: null,
        isHidden: false,
        displayDuration: 6000,
        hideTimer: null,

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-social-proof]').forEach(function (container) {
                if (container.dataset.ghInitSocialProof) return;
                container.dataset.ghInitSocialProof = 'true';
                if (self.container) return; // una sola istanza per pagina
                self.setup(container);
            });
        },

        setup: function (container) {
            this.container = container;

            try {
                this.notifications = JSON.parse(container.dataset.ghSocialProofItems || '[]');
            } catch (e) { return; }

            if (!this.notifications.length) return;

            // Gentle timing: show every 25-35 seconds, start after 15 seconds
            var interval = parseInt(container.dataset.ghSocialProofInterval) || 30000;
            var delay = parseInt(container.dataset.ghSocialProofDelay) || 15000;
            var duration = parseInt(container.dataset.ghSocialProofDuration) || 6000;

            this.displayDuration = duration;

            // Reduced motion: il toast appare/scompare comunque ma senza
            // l'animazione di slide (transition disattivata inline).
            if (prefersReducedMotion) {
                container.style.transition = 'none';
            }

            var self = this;

            // Close button
            var closeBtn = container.querySelector('[data-gh-social-proof-close]');
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    self.hide();
                    self.isHidden = true; // User dismissed, stop showing
                });
            }

            // Start showing notifications after delay
            setTimeout(function () {
                self.show();
                setInterval(function () {
                    if (!self.isHidden) self.show();
                }, interval);
            }, delay);
        },

        show: function () {
            if (this.isHidden || !this.container) return;

            var n = this.notifications[this.currentIndex];
            this.currentIndex = (this.currentIndex + 1) % this.notifications.length;

            // Update content — solo textContent, mai HTML interpolato.
            var textEl = this.container.querySelector('[data-gh-social-proof-text]');
            var productEl = this.container.querySelector('[data-gh-social-proof-product]');
            var metaEl = this.container.querySelector('[data-gh-social-proof-meta]');
            var imageEl = this.container.querySelector('[data-gh-social-proof-image]');

            if (productEl) {
                productEl.textContent = n.product || '';
            } else if (textEl) {
                // Fallback per markup legacy senza il nodo dedicato:
                // ricostruito via DOM, comunque senza innerHTML.
                textEl.textContent = 'Qualcuno ha acquistato ';
                var strong = document.createElement('strong');
                strong.setAttribute('data-gh-social-proof-product', '');
                strong.textContent = n.product || '';
                textEl.appendChild(strong);
            }

            if (metaEl) {
                var location = n.location ? ' da ' + n.location : '';
                metaEl.textContent = (n.time || '') + location;
            }

            if (imageEl) {
                var imageWrapper = imageEl.closest('.gh-social-proof__image');
                if (n.image) {
                    imageEl.src = n.image;
                    imageEl.alt = n.product || '';
                    if (imageWrapper) imageWrapper.style.display = '';
                } else {
                    imageEl.src = '';
                    if (imageWrapper) imageWrapper.style.display = 'none';
                }
            }

            this.container.classList.add('is-visible');
            var self = this;
            if (this.hideTimer) clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(function () { self.hide(); }, this.displayDuration);
        },

        hide: function () {
            if (this.container) this.container.classList.remove('is-visible');
        }
    };

    /**
     * Smooth Marquee — contratto condiviso:
     * style.css possiede @keyframes gh-marquee e le regole su
     * [data-gh-marquee-track] (durata via var(--gh-marquee-duration),
     * direzione via [data-gh-marquee-direction="right"], pausa hover via
     * [data-gh-marquee-pause]). Il JS si limita a: clonare la track una sola
     * volta (clone aria-hidden) e impostare --gh-marquee-duration =
     * scrollWidth/speed, ricalcolato in un ResizeObserver (i loghi che
     * caricano cambiano la larghezza) con fallback su window 'load'.
     */
    var Marquee = {
        init: function (root) {
            scan(root, '[data-gh-marquee]').forEach(function (container) {
                if (container.dataset.ghInitMarquee) return;

                var track = container.querySelector('[data-gh-marquee-track]');
                if (!track) return;
                container.dataset.ghInitMarquee = 'true';

                // Niente clone né animazione con reduced motion: resta la
                // track statica (la CSS disattiva l'animazione).
                if (prefersReducedMotion) return;

                // Clona la track una sola volta (idempotente anche se il
                // markup è stato serializzato con il clone già presente).
                if (!container.querySelector('[data-gh-marquee-track][aria-hidden="true"]')) {
                    var clone = track.cloneNode(true);
                    clone.setAttribute('aria-hidden', 'true');
                    track.parentNode.insertBefore(clone, track.nextSibling);
                }

                var speed = parseInt(container.dataset.ghMarqueeSpeed) || 50; // px/s
                var setDuration = function () {
                    var width = track.scrollWidth;
                    if (!width) return;
                    // Sul container così la ereditano sia la track sia il clone.
                    container.style.setProperty('--gh-marquee-duration', (width / speed) + 's');
                };

                setDuration();

                if ('ResizeObserver' in window) {
                    var ro = new ResizeObserver(setDuration);
                    ro.observe(track);
                }
                // Fallback/cintura di sicurezza: ricalcola a pagina caricata
                // (immagini dei loghi incluse).
                window.addEventListener('load', setDuration);
            });
        }
    };

    /**
     * Modal System
     * - aria-hidden: il markup chiuso arriva con aria-hidden="true"; open()
     *   lo mette a "false", close() lo ripristina.
     * - Focus: salva document.activeElement, sposta il focus sul bottone
     *   [data-gh-modal-close], intrappola Tab nel dialog, ripristina alla
     *   chiusura.
     * - Escape chiude SOLO l'ultimo modal aperto (stack).
     * - Scroll: GoldenHive.lockScroll()/unlockScroll(), niente
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
                            // Fallback: modal aperto fuori dallo stack (markup legacy).
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

            // Già aperto: non rilockare né duplicare nello stack.
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
     * Verifica se un altro overlay è già aperto/visibile: modali GH, lock di
     * scroll condiviso (quick view / quick add / live search lo usano), o gli
     * overlay quick-view (.rp-qv-modal.active) e live-search
     * (#rlv-search-modal.is-open) direttamente.
     */
    function overlayActive() {
        if (scrollLockCount > 0 || Modal.stack.length > 0) return true;
        if (document.documentElement.classList.contains('gh-scroll-lock')) return true;
        if (document.querySelector('.gh-modal--open, .gh-promo-modal--open')) return true;
        if (document.querySelector('.rp-qv-modal.active, #rlv-search-modal.is-open')) return true;
        if (document.querySelector('[role="dialog"][aria-hidden="false"]')) return true;
        return false;
    }

    /**
     * Promo Modal - Auto show
     * data-gh-promo-delay resta in MILLISECONDI. Se al momento dell'apertura
     * automatica c'è già un altro overlay aperto, l'apertura viene rimandata
     * (retry ogni 5s, massimo 3 tentativi) invece di sovrapporsi.
     */
    var PromoModal = {
        init: function (root) {
            scan(root, '[data-gh-promo-modal]').forEach(function (modal) {
                if (modal.dataset.ghInitPromo) return;
                modal.dataset.ghInitPromo = 'true';

                var delay = parseInt(modal.dataset.ghPromoDelay) || 5000; // ms
                // Retrocompatibilità: mentre l'editor salvava secondi (bug
                // unità), valori tipo 5-60 sono finiti nei post salvati.
                // Un ritardo reale sotto i 100ms non ha senso: trattalo
                // come secondi.
                if (delay > 0 && delay < 100) delay *= 1000;
                var showOnce = modal.dataset.ghPromoOnce === 'true';
                var storageKey = 'gh_promo_' + modal.dataset.ghModal + '_shown';

                if (showOnce) {
                    try {
                        if (localStorage.getItem(storageKey)) return;
                    } catch (e) { /* storage bloccato: mostra comunque */ }
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
     * FAQ Accordion
     * - aria-expanded sul trigger.
     * - Dopo la transizione di apertura maxHeight passa a 'none' così il
     *   contenuto non resta clippato se cambia altezza (immagini, resize);
     *   prima di chiudere si ripristina il valore misurato per far partire
     *   la transizione.
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
                            void content.offsetHeight; // reflow per far partire la transizione
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
                        // Solo se è ancora aperto: sblocca il clipping.
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

                        // Chiudi gli altri
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
     * Hero Carousel con Crossfade
     * - Niente autoplay con prefers-reduced-motion.
     * - Pausa quando il documento è nascosto (visibilitychange) e quando il
     *   carousel esce dal viewport (un solo IntersectionObserver condiviso).
     * - aria-current="true" sul dot attivo; aria-hidden + inert sulle slide
     *   inattive (il markup le spedisce già sulle slide 2+).
     * - Gli swipe più verticali che orizzontali vengono ignorati.
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

                var stop = function () {
                    if (state.timer) { clearInterval(state.timer); state.timer = null; }
                };

                var start = function () {
                    stop();
                    if (!autoplay || state.hover || !state.visible || document.hidden) return;
                    state.timer = setInterval(function () { goTo(state.current + 1); }, autoplay);
                };

                var goTo = function (index) {
                    state.current = (index + slides.length) % slides.length;
                    applyState();
                    start(); // reset del timer
                };

                state.sync = start;

                // Crea dots
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

                // Touch — ignora gli swipe prevalentemente verticali (scroll).
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

                // Pausa on hover
                carousel.addEventListener('mouseenter', function () { state.hover = true; stop(); });
                carousel.addEventListener('mouseleave', function () { state.hover = false; start(); });

                self.instances.push(state);

                // Un solo IO condiviso: pausa fuori viewport.
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
                start();
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
     * Image Reveal Effect — un solo IntersectionObserver condiviso.
     */
    var ImageReveal = {
        observer: null,

        init: function (root) {
            var self = this;
            scan(root, '[data-gh-image-reveal]').forEach(function (el) {
                if (el.dataset.ghInitImageReveal) return;
                el.dataset.ghInitImageReveal = 'true';

                if (prefersReducedMotion || !('IntersectionObserver' in window)) {
                    // Reduced motion: rivela subito, altrimenti l'overlay
                    // coprirebbe l'immagine per sempre.
                    el.classList.add('gh-image-revealed');
                    return;
                }

                if (!self.observer) {
                    self.observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('gh-image-revealed');
                                self.observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.2 });
                }

                self.observer.observe(el);
            });
        }
    };

    /**
     * Newsletter Form — invio reale via admin-ajax.
     * Contratto: il form porta data-gh-newsletter-url e
     * data-gh-newsletter-nonce; POST FormData {action:
     * 'ghb_newsletter_subscribe', nonce, email}; la risposta JSON espone
     * data.message, mostrato nell'elemento feedback aria-live.
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

                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    if (!input) return;
                    var email = input.value.trim();
                    if (!email) return;

                    var url = form.dataset.ghNewsletterUrl;
                    var nonce = form.dataset.ghNewsletterNonce || '';

                    // Markup legacy senza endpoint: feedback ottimistico come prima.
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
                        if (ok) input.value = '';
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
     * WhatsApp Button - Lazy visibility
     * Nascosto finché non si scorre oltre 300px (solo con JS attivo:
     * html.gh-js in CSS, così senza JS il bottone resta sempre visibile);
     * la visibilità è gestita dalla classe .gh-whatsapp--visible, nessuno
     * stile inline. Listener di scroll passivo che si stacca da solo dopo
     * la prima rivelazione.
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
     * Hero Video Background — [data-gh-video-bg]
     * Video di sfondo muted/autoplay: con prefers-reduced-motion il video
     * resta in pausa sul poster; altrimenti un solo IntersectionObserver lo
     * mette in pausa fuori viewport e lo riavvia quando torna visibile.
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
                                if (p && typeof p.catch === 'function') p.catch(function () { /* autoplay bloccato */ });
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
     * Ri-esegue tutte le scansioni dentro `root` (default: document).
     * Sicuro da chiamare più volte: ogni init è idempotente.
     */
    var refresh = function (root) {
        root = root || document;
        SplitText.init(root);
        MagneticCursor.init(root);
        MouseParallax.init(root);
        ScrollReveal.init(root);
        CountdownTimer.init(root);
        SocialProof.init(root);
        Marquee.init(root);
        Modal.init(root);
        PromoModal.init(root);
        FAQ.init(root);
        HeroCarousel.init(root);
        ImageReveal.init(root);
        Newsletter.init(root);
        WhatsAppButton.init(root);
        HeroVideo.init(root);
    };

    /**
     * Selettori "dinamici": un nodo aggiunto al DOM che matcha (o contiene)
     * uno di questi viene instradato in refresh() dal MutationObserver.
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
        SocialProof: SocialProof
    };
})();
