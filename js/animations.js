/**
 * Golden Hive Blocks - Premium Animation Library
 * Animazioni moderne per e-commerce streetwear
 */

(function() {
    'use strict';

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Split Text Animation - Animazione carattere per carattere
     * Skips elements that already contain inline markup (e.g. a hero title
     * with a highlighted <span>) so author-provided HTML isn't destroyed.
     */
    const SplitText = {
        init() {
            document.querySelectorAll('[data-gh-split]').forEach(el => {
                if (el.dataset.ghSplitProcessed) return;

                // If the element contains any child elements, don't rewrite it —
                // preserve author markup (inline spans, links, <br>, etc.).
                if (el.children.length > 0) {
                    el.dataset.ghSplitProcessed = 'true';
                    return;
                }

                const type = el.dataset.ghSplit || 'chars';
                const text = el.textContent.trim();
                if (!text) return;

                if (type === 'chars') {
                    const totalChars = text.replace(/\s/g, '').length;
                    el.innerHTML = text.split('').map((char, i) =>
                        char === ' '
                            ? '<span class="gh-char gh-char--space">&nbsp;</span>'
                            : `<span class="gh-char" style="--char-index: ${i}; --char-total: ${totalChars}">${char}</span>`
                    ).join('');
                } else if (type === 'words') {
                    const words = text.split(/\s+/);
                    el.innerHTML = words.map((word, i) =>
                        `<span class="gh-word" style="--word-index: ${i}; --word-total: ${words.length}">${word}</span>`
                    ).join(' ');
                }

                el.dataset.ghSplitProcessed = 'true';
            });
        }
    };

    /**
     * Magnetic Cursor - Elementi attratti dal mouse
     * rAF-throttled so mousemove never writes more than once per frame.
     */
    const MagneticCursor = {
        init() {
            if (prefersReducedMotion || 'ontouchstart' in window) return;

            document.querySelectorAll('[data-gh-magnetic]').forEach(el => {
                const strength = parseFloat(el.dataset.ghMagnetic) || 0.3;
                let bounds = null;
                let targetX = 0, targetY = 0;
                let rafId = 0;

                const apply = () => {
                    rafId = 0;
                    el.style.transform = `translate3d(${targetX}px, ${targetY}px, 0)`;
                };

                el.addEventListener('mouseenter', () => {
                    bounds = el.getBoundingClientRect();
                    el.style.transition = 'transform 0.1s ease-out';
                }, { passive: true });

                el.addEventListener('mousemove', (e) => {
                    if (!bounds) return;
                    targetX = (e.clientX - bounds.left - bounds.width / 2) * strength;
                    targetY = (e.clientY - bounds.top - bounds.height / 2) * strength;
                    if (!rafId) rafId = requestAnimationFrame(apply);
                }, { passive: true });

                el.addEventListener('mouseleave', () => {
                    if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
                    el.style.transition = 'transform 0.4s cubic-bezier(0.33, 1, 0.68, 1)';
                    el.style.transform = 'translate3d(0, 0, 0)';
                }, { passive: true });
            });
        }
    };

    /**
     * Depth Parallax - Parallax multi-layer con scroll
     * - rAF-coalesced scroll handler (one update per frame max)
     * - IntersectionObserver gate: only animate layers currently in viewport
     * - Mouse parallax throttled via rAF
     */
    const DepthParallax = {
        layers: [],

        init() {
            if (prefersReducedMotion) return;

            const nodes = document.querySelectorAll('[data-gh-depth]');
            nodes.forEach(el => {
                this.layers.push({
                    el,
                    depth: parseFloat(el.dataset.ghDepth) || 0.5,
                    direction: el.dataset.ghDepthDir || 'y',
                    visible: false
                });
                el.style.willChange = 'transform';
            });

            if (this.layers.length) {
                // Only update layers that are on-screen
                const io = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        const layer = this.layers.find(l => l.el === entry.target);
                        if (layer) layer.visible = entry.isIntersecting;
                    });
                }, { rootMargin: '200px 0px' });
                this.layers.forEach(l => io.observe(l.el));

                let ticking = false;
                const onScroll = () => {
                    if (ticking) return;
                    ticking = true;
                    requestAnimationFrame(() => {
                        this.update();
                        ticking = false;
                    });
                };
                this.update();
                window.addEventListener('scroll', onScroll, { passive: true });
                window.addEventListener('resize', onScroll, { passive: true });
            }

            // Mouse parallax per container — rAF throttled
            document.querySelectorAll('[data-gh-mouse-parallax]').forEach(container => {
                const layers = container.querySelectorAll('[data-gh-mouse-layer]');
                if (!layers.length) return;

                let mx = 0, my = 0, rect = null, rafId = 0;

                const apply = () => {
                    rafId = 0;
                    layers.forEach(layer => {
                        const depth = parseFloat(layer.dataset.ghMouseLayer) || 30;
                        layer.style.transform = `translate3d(${mx * depth}px, ${my * depth}px, 0)`;
                    });
                };

                container.addEventListener('mouseenter', () => {
                    rect = container.getBoundingClientRect();
                }, { passive: true });

                container.addEventListener('mousemove', (e) => {
                    if (!rect) rect = container.getBoundingClientRect();
                    mx = (e.clientX - rect.left) / rect.width - 0.5;
                    my = (e.clientY - rect.top) / rect.height - 0.5;
                    if (!rafId) rafId = requestAnimationFrame(apply);
                }, { passive: true });

                container.addEventListener('mouseleave', () => {
                    if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
                    layers.forEach(layer => {
                        layer.style.transition = 'transform 0.6s cubic-bezier(0.33, 1, 0.68, 1)';
                        layer.style.transform = 'translate3d(0, 0, 0)';
                        setTimeout(() => layer.style.transition = '', 600);
                    });
                }, { passive: true });
            });
        },

        update() {
            const vh = window.innerHeight;

            this.layers.forEach((layer) => {
                if (!layer.visible) return;
                const { el, depth, direction } = layer;
                const rect = el.getBoundingClientRect();
                const elementCenter = rect.top + rect.height / 2;
                const distance = (elementCenter - vh / 2) / vh;
                const movement = distance * depth * -100;

                if (direction === 'x') {
                    el.style.transform = `translate3d(${movement}px, 0, 0)`;
                } else {
                    el.style.transform = `translate3d(0, ${movement}px, 0)`;
                }
            });
        }
    };

    /**
     * Scroll Reveal con Animazioni Premium
     */
    const ScrollReveal = {
        init() {
            const elements = document.querySelectorAll('[data-gh-reveal]');
            if (!elements.length) return;

            if (prefersReducedMotion) {
                elements.forEach(el => el.classList.add('gh-revealed'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const delay = parseInt(entry.target.dataset.ghRevealDelay) || 0;
                        setTimeout(() => entry.target.classList.add('gh-revealed'), delay);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

            elements.forEach(el => observer.observe(el));
        }
    };

    /**
     * Countdown Premium con Flip Animation
     */
    const CountdownTimer = {
        init() {
            document.querySelectorAll('[data-gh-countdown]').forEach(el => {
                const targetDate = new Date(el.dataset.ghCountdown).getTime();
                if (isNaN(targetDate)) return;

                this.update(el, targetDate);
                const interval = setInterval(() => {
                    if (this.update(el, targetDate) <= 0) {
                        clearInterval(interval);
                        this.handleExpired(el);
                    }
                }, 1000);
            });
        },

        update(el, targetDate) {
            const remaining = targetDate - Date.now();
            if (remaining <= 0) return remaining;

            const units = {
                giorni: Math.floor(remaining / 86400000),
                ore: Math.floor((remaining % 86400000) / 3600000),
                minuti: Math.floor((remaining % 3600000) / 60000),
                secondi: Math.floor((remaining % 60000) / 1000)
            };

            Object.entries(units).forEach(([unit, value]) => {
                const digitEl = el.querySelector(`[data-gh-countdown-${unit}]`);
                if (!digitEl) return;

                const newVal = String(value).padStart(2, '0');
                if (digitEl.textContent !== newVal) {
                    digitEl.classList.add('gh-digit-flip');
                    setTimeout(() => {
                        digitEl.textContent = newVal;
                        digitEl.classList.remove('gh-digit-flip');
                    }, 200);
                }
            });

            return remaining;
        },

        handleExpired(el) {
            el.innerHTML = `<div class="gh-countdown__expired">${el.dataset.ghCountdownExpired || 'DISPONIBILE ORA'}</div>`;
            el.classList.add('gh-countdown--expired');
        }
    };

    /**
     * Social Proof Notification - Shopify style
     * Gentle timing, non-aggressive popups
     */
    const SocialProof = {
        notifications: [],
        currentIndex: 0,
        container: null,
        isHidden: false,

        init() {
            this.container = document.querySelector('[data-gh-social-proof]');
            if (!this.container) return;

            try {
                this.notifications = JSON.parse(this.container.dataset.ghSocialProofItems || '[]');
            } catch (e) { return; }

            if (!this.notifications.length) return;

            // Gentle timing: show every 25-35 seconds, start after 15 seconds
            const interval = parseInt(this.container.dataset.ghSocialProofInterval) || 30000;
            const delay = parseInt(this.container.dataset.ghSocialProofDelay) || 15000;
            const duration = parseInt(this.container.dataset.ghSocialProofDuration) || 6000;

            this.displayDuration = duration;

            // Close button
            this.container.querySelector('[data-gh-social-proof-close]')?.addEventListener('click', (e) => {
                e.stopPropagation();
                this.hide();
                this.isHidden = true; // User dismissed, stop showing
            });

            // Start showing notifications after delay
            setTimeout(() => {
                this.show();
                setInterval(() => {
                    if (!this.isHidden) this.show();
                }, interval);
            }, delay);
        },

        show() {
            if (this.isHidden) return;

            const n = this.notifications[this.currentIndex];
            this.currentIndex = (this.currentIndex + 1) % this.notifications.length;

            // Update content
            const textEl = this.container.querySelector('[data-gh-social-proof-text]');
            const metaEl = this.container.querySelector('[data-gh-social-proof-meta]');
            const imageEl = this.container.querySelector('[data-gh-social-proof-image]');

            if (textEl) {
                textEl.innerHTML = `Someone purchased <strong>${n.product}</strong>`;
            }
            if (metaEl) {
                const location = n.location ? ` from ${n.location}` : '';
                metaEl.textContent = `${n.time}${location}`;
            }
            if (imageEl) {
                const imageWrapper = imageEl.closest('.gh-social-proof__image');
                if (n.image) {
                    imageEl.src = n.image;
                    imageEl.alt = n.product;
                    if (imageWrapper) imageWrapper.style.display = '';
                } else {
                    imageEl.src = '';
                    if (imageWrapper) imageWrapper.style.display = 'none';
                }
            }

            this.container.classList.add('is-visible');
            setTimeout(() => this.hide(), this.displayDuration);
        },

        hide() {
            this.container.classList.remove('is-visible');
        }
    };

    /**
     * Smooth Marquee
     */
    const Marquee = {
        init() {
            document.querySelectorAll('[data-gh-marquee]').forEach(container => {
                const track = container.querySelector('[data-gh-marquee-track]');
                if (!track) return;

                const clone = track.cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                container.appendChild(clone);

                const speed = parseInt(container.dataset.ghMarqueeSpeed) || 50;
                const dir = container.dataset.ghMarqueeDirection === 'right' ? 'reverse' : 'normal';
                const duration = track.scrollWidth / speed;

                [track, clone].forEach(t => {
                    t.style.animation = `gh-marquee-scroll ${duration}s linear infinite ${dir}`;
                });

                if (container.dataset.ghMarqueePause !== 'false') {
                    container.addEventListener('mouseenter', () => {
                        [track, clone].forEach(t => t.style.animationPlayState = 'paused');
                    });
                    container.addEventListener('mouseleave', () => {
                        [track, clone].forEach(t => t.style.animationPlayState = 'running');
                    });
                }
            });

            if (!document.getElementById('gh-marquee-keyframes')) {
                const style = document.createElement('style');
                style.id = 'gh-marquee-keyframes';
                style.textContent = `@keyframes gh-marquee-scroll { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }`;
                document.head.appendChild(style);
            }
        }
    };

    /**
     * Modal System
     */
    const Modal = {
        init() {
            document.querySelectorAll('[data-gh-modal-trigger]').forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.open(trigger.dataset.ghModalTrigger);
                });
            });

            document.querySelectorAll('[data-gh-modal-close]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modal = btn.closest('[data-gh-modal]');
                    if (modal) this.close(modal.dataset.ghModal);
                });
            });

            document.querySelectorAll('[data-gh-modal]').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) this.close(modal.dataset.ghModal);
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const open = document.querySelector('[data-gh-modal].gh-modal--open, [data-gh-modal].gh-promo-modal--open');
                    if (open) this.close(open.dataset.ghModal);
                }
            });
        },

        open(id) {
            const modal = document.querySelector(`[data-gh-modal="${id}"]`);
            if (!modal) return;
            if (modal.classList.contains('gh-promo-modal')) {
                modal.classList.add('gh-promo-modal--open');
            } else {
                modal.classList.add('gh-modal--open');
            }
            document.body.style.overflow = 'hidden';
        },

        close(id) {
            const modal = document.querySelector(`[data-gh-modal="${id}"]`);
            if (!modal) return;
            modal.classList.remove('gh-modal--open', 'gh-promo-modal--open');
            document.body.style.overflow = '';
        }
    };

    /**
     * Promo Modal - Auto show
     */
    const PromoModal = {
        init() {
            document.querySelectorAll('[data-gh-promo-modal]').forEach(modal => {
                const delay = parseInt(modal.dataset.ghPromoDelay) || 5000;
                const showOnce = modal.dataset.ghPromoOnce === 'true';
                const storageKey = `gh_promo_${modal.dataset.ghModal}_shown`;

                if (showOnce && localStorage.getItem(storageKey)) return;

                setTimeout(() => {
                    Modal.open(modal.dataset.ghModal);
                    if (showOnce) localStorage.setItem(storageKey, 'true');
                }, delay);
            });
        }
    };

    /**
     * FAQ Accordion
     */
    const FAQ = {
        init() {
            document.querySelectorAll('[data-gh-faq]').forEach(container => {
                const items = container.querySelectorAll('[data-gh-faq-item]');

                items.forEach(item => {
                    const trigger = item.querySelector('[data-gh-faq-trigger]');
                    const content = item.querySelector('[data-gh-faq-content]');
                    if (!trigger || !content) return;

                    trigger.addEventListener('click', () => {
                        const isOpen = item.classList.contains('gh-faq__item--open');

                        // Chiudi gli altri
                        if (container.dataset.ghFaqMultiple !== 'true') {
                            items.forEach(i => {
                                i.classList.remove('gh-faq__item--open');
                                const c = i.querySelector('[data-gh-faq-content]');
                                if (c) c.style.maxHeight = '0';
                            });
                        }

                        if (!isOpen) {
                            item.classList.add('gh-faq__item--open');
                            content.style.maxHeight = content.scrollHeight + 'px';
                        } else {
                            item.classList.remove('gh-faq__item--open');
                            content.style.maxHeight = '0';
                        }
                    });
                });
            });
        }
    };

    /**
     * Hero Carousel con Crossfade
     */
    const HeroCarousel = {
        init() {
            document.querySelectorAll('[data-gh-hero-carousel]').forEach(carousel => {
                const slides = carousel.querySelectorAll('[data-gh-hero-slide]');
                const dots = carousel.querySelector('[data-gh-hero-dots]');
                const prev = carousel.querySelector('[data-gh-hero-prev]');
                const next = carousel.querySelector('[data-gh-hero-next]');

                if (slides.length < 2) return;

                let current = 0;
                let timer = null;
                const autoplay = parseInt(carousel.dataset.ghHeroAutoplay) || 6000;

                const goTo = (index) => {
                    slides[current].classList.remove('gh-hero-slide--active');
                    current = (index + slides.length) % slides.length;
                    slides[current].classList.add('gh-hero-slide--active');

                    if (dots) {
                        dots.querySelectorAll('button').forEach((d, i) => {
                            d.classList.toggle('gh-hero-dot--active', i === current);
                        });
                    }

                    resetTimer();
                };

                const resetTimer = () => {
                    if (timer) clearInterval(timer);
                    if (autoplay > 0) timer = setInterval(() => goTo(current + 1), autoplay);
                };

                // Crea dots
                if (dots) {
                    slides.forEach((_, i) => {
                        const btn = document.createElement('button');
                        btn.className = 'gh-hero-dot' + (i === 0 ? ' gh-hero-dot--active' : '');
                        btn.setAttribute('aria-label', `Slide ${i + 1}`);
                        btn.addEventListener('click', () => goTo(i));
                        dots.appendChild(btn);
                    });
                }

                if (prev) prev.addEventListener('click', () => goTo(current - 1));
                if (next) next.addEventListener('click', () => goTo(current + 1));

                // Touch
                let startX = 0;
                carousel.addEventListener('touchstart', e => startX = e.touches[0].clientX, { passive: true });
                carousel.addEventListener('touchend', e => {
                    const diff = startX - e.changedTouches[0].clientX;
                    if (Math.abs(diff) > 50) goTo(current + (diff > 0 ? 1 : -1));
                }, { passive: true });

                // Pausa on hover
                carousel.addEventListener('mouseenter', () => timer && clearInterval(timer));
                carousel.addEventListener('mouseleave', resetTimer);

                slides[0].classList.add('gh-hero-slide--active');
                resetTimer();
            });
        }
    };

    /**
     * Size Selector
     */
    const SizeSelector = {
        init() {
            document.querySelectorAll('[data-gh-size-selector]').forEach(container => {
                const sizes = container.querySelectorAll('[data-gh-size]');
                const display = container.querySelector('[data-gh-size-selected]');

                sizes.forEach(size => {
                    if (size.classList.contains('gh-size--unavailable')) return;

                    size.addEventListener('click', () => {
                        sizes.forEach(s => s.classList.remove('gh-size--selected'));
                        size.classList.add('gh-size--selected');
                        if (display) display.textContent = size.dataset.ghSize;
                    });
                });
            });
        }
    };

    /**
     * Image Reveal Effect
     */
    const ImageReveal = {
        init() {
            if (prefersReducedMotion) return;

            document.querySelectorAll('[data-gh-image-reveal]').forEach(el => {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('gh-image-revealed');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.2 });

                observer.observe(el);
            });
        }
    };

    /**
     * Newsletter Form
     */
    const Newsletter = {
        init() {
            document.querySelectorAll('[data-gh-newsletter-form]').forEach(form => {
                const input = form.querySelector('[data-gh-newsletter-input]');
                const feedback = form.parentElement.querySelector('[data-gh-newsletter-feedback]');

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    const email = input.value.trim();
                    if (!email) return;

                    // Simula invio (sostituire con logica reale)
                    if (feedback) {
                        feedback.textContent = 'Iscrizione completata! Grazie per esserti unito alla nostra community.';
                        feedback.className = 'gh-newsletter__feedback gh-newsletter__feedback--success';
                    }

                    input.value = '';
                });
            });
        }
    };

    /**
     * WhatsApp Button - Lazy visibility
     * Uses a one-shot IntersectionObserver sentinel so the scroll handler
     * runs exactly once then detaches itself.
     */
    const WhatsAppButton = {
        init() {
            const btn = document.querySelector('[data-gh-whatsapp]');
            if (!btn) return;

            const reveal = () => {
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            };

            if (window.scrollY > 300) { reveal(); return; }

            const onScroll = () => {
                if (window.scrollY > 300) {
                    reveal();
                    window.removeEventListener('scroll', onScroll);
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
        }
    };

    /**
     * Initialize
     */
    const init = () => {
        SplitText.init();
        MagneticCursor.init();
        DepthParallax.init();
        ScrollReveal.init();
        CountdownTimer.init();
        SocialProof.init();
        Marquee.init();
        Modal.init();
        PromoModal.init();
        FAQ.init();
        HeroCarousel.init();
        SizeSelector.init();
        ImageReveal.init();
        Newsletter.init();
        WhatsAppButton.init();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.GoldenHive = { init, Modal, SocialProof };
})();
