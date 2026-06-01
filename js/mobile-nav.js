/**
 * Golden Hive Blocks — Mobile nav caret (+/-) toggle.
 *
 * THEME-SPECIFIC (Shoptimizer / CommerceGurus). See includes/mobile-nav.php.
 *
 *  - matchMedia is checked INSIDE the handlers, so behaviour survives
 *    resize/rotate without re-binding.
 *  - One delegated listener per .main-navigation (bound once), so it covers
 *    dynamically added menu items and toggles only the nearest parent.
 *  - ARIA (role/aria-expanded), keyboard (Enter/Space), focus ring on the caret.
 *  - Accordion: opening a branch closes its open siblings (set ACCORDION=false
 *    to allow multiple open branches).
 */
(function () {
    var MOBILE = '(max-width: 991px)';
    var ACCORDION = true;

    function isMobile() { return window.matchMedia(MOBILE).matches; }

    function setExpanded(li, open) {
        var caret = li.querySelector(':scope > .caret');
        if (caret) caret.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function close(li) {
        if (!li.classList.contains('cg-open')) return;
        li.classList.remove('cg-open');
        setExpanded(li, false);
    }

    function toggle(li) {
        var willOpen = !li.classList.contains('cg-open');

        // Accordion: close sibling branches at the same level before opening.
        if (willOpen && ACCORDION && li.parentElement) {
            Array.prototype.forEach.call(li.parentElement.children, function (sib) {
                if (sib !== li && sib.classList && sib.classList.contains('cg-open')) {
                    close(sib);
                }
            });
        }

        li.classList.toggle('cg-open');
        setExpanded(li, willOpen);
    }

    function enhance(nav) {
        if (nav.dataset.cgBound) return;   // bind once per menu
        nav.dataset.cgBound = '1';

        // Prime ARIA on existing carets.
        nav.querySelectorAll('.menu-item-has-children > .caret').forEach(function (c) {
            c.setAttribute('role', 'button');
            c.setAttribute('tabindex', '0');
            c.setAttribute('aria-expanded', 'false');
            c.setAttribute('aria-label', 'Apri / chiudi sottomenu');
        });

        // ONE delegated click handler for the whole menu.
        nav.addEventListener('click', function (e) {
            if (!isMobile()) return;

            var caret = e.target.closest('.caret');
            var li    = (caret || e.target).closest('.menu-item-has-children');
            if (!li || !nav.contains(li)) return;

            // Caret tapped -> toggle.
            if (caret && li.contains(caret)) {
                e.preventDefault();
                e.stopPropagation();
                toggle(li);
                return;
            }

            // The link itself -> let it navigate.
            var link = li.querySelector(':scope > a');
            if (link && (e.target === link || link.contains(e.target))) return;

            // Inside an already-open submenu -> leave it alone.
            var sub = li.querySelector(':scope > .sub-menu-wrapper');
            if (sub && (e.target === sub || sub.contains(e.target))) return;

            // Bare row -> toggle this item.
            e.preventDefault();
            toggle(li);
        });

        // Keyboard support on carets.
        nav.addEventListener('keydown', function (e) {
            if (!isMobile()) return;
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
            var caret = e.target.closest('.caret');
            if (!caret) return;
            var li = caret.closest('.menu-item-has-children');
            if (!li) return;
            e.preventDefault();
            toggle(li);
        });
    }

    function init() {
        document.querySelectorAll('.main-navigation').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
