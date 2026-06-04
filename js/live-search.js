/**
 * Golden Hive Blocks — Live Ajax Search modal behaviour.
 *
 * Vanilla, no build step. Opens/closes the search modal, manages focus, and
 * toggles state classes the CSS keys off. The Relevanssi Live Ajax Search
 * plugin handles the as-you-type querying and renders into #rlv-modal-results.
 */
(function () {
    var modal = document.getElementById('rlv-search-modal');
    if (!modal) return;
    var input   = document.getElementById('rlv-input');
    var results = document.getElementById('rlv-modal-results');
    var lastFocus = null;
    var justClosed = false;

    // Theme search form(s) to hijack into the modal. Server-provided + filterable
    // (ghb_live_search_trigger_selector); defaults to the header's .site-search.
    var cfg = window.ghbLiveSearch || {};
    var triggerSel = cfg.triggerSelector || '';

    function open(returnFocusEl) {
        if (!modal.classList.contains('is-open')) {
            lastFocus = returnFocusEl || document.activeElement;
        }
        modal.classList.add('is-open');
        document.body.classList.add('rlv-open');
        setTimeout(function () { if (input) input.focus(); }, 60);
    }
    function close() {
        modal.classList.remove('is-open');
        document.body.classList.remove('rlv-open');
        if (lastFocus && lastFocus.focus) {
            // Returning focus to a hijacked field would re-fire focusin and
            // reopen the modal — suppress that for one tick.
            justClosed = true;
            lastFocus.focus();
            setTimeout(function () { justClosed = false; }, 100);
        }
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-rlv-open]'))  { e.preventDefault(); open();  return; }
        if (e.target.closest('[data-rlv-close]')) { e.preventDefault(); close(); return; }
        // Clicking anywhere in a hijacked theme search form opens the modal
        // instead of using the inline field / submitting it natively.
        if (triggerSel && !modal.contains(e.target) && e.target.closest(triggerSel)) {
            e.preventDefault();
            open();
        }
    });

    // Keyboard users tabbing into the hijacked field also get the modal.
    document.addEventListener('focusin', function (e) {
        if (justClosed || !triggerSel) return;
        if (!modal.contains(e.target) && e.target.closest(triggerSel)) {
            var trigger = e.target;
            if (trigger.blur) trigger.blur();
            open(trigger);
        }
    });

    document.addEventListener('keydown', function (e) {
        var typing = /^(input|textarea|select)$/i.test(e.target.tagName || '');
        if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); open(); }
        else if (e.key === 'Escape' && modal.classList.contains('is-open')) { close(); }
        else if (e.key === '/' && !typing && !modal.classList.contains('is-open')) { e.preventDefault(); open(); }
    });

    // The bundled results template always lands either a .rlv-results-list (hits)
    // or .rlv-no-results (miss) inside #rlv-modal-results. We treat "the user has
    // typed but neither has arrived yet" as "searching" and surface a spinner, so
    // the panel is never just blank while the AJAX request is in flight.
    var SETTLED_SEL = '.rlv-results-list, .rlv-no-results';
    var searchGuard = null;

    function setBusy(busy) {
        modal.classList.toggle('is-searching', busy);
        if (results) results.setAttribute('aria-busy', busy ? 'true' : 'false');
    }
    function refreshHasResults() {
        modal.classList.toggle('has-results', !!(results && results.querySelector('.rlv-results-list')));
    }

    if (input) {
        input.addEventListener('input', function () {
            var q = input.value.trim();
            modal.classList.toggle('has-query', q.length > 0);
            clearTimeout(searchGuard);

            if (!q) {                                  // back to the idle hint
                if (results) results.innerHTML = '';
                setBusy(false);
                refreshHasResults();
                return;
            }

            setBusy(true);                             // optimistic — show it the instant they type
            searchGuard = setTimeout(function () {     // never spin forever if the request fails
                setBusy(false);
            }, 8000);
        });
    }

    if (results) {
        new MutationObserver(function () {
            refreshHasResults();
            if (results.querySelector(SETTLED_SEL)) {  // template landed → request done
                clearTimeout(searchGuard);
                setBusy(false);
            }
            results.scrollTop = 0;
        }).observe(results, { childList: true, subtree: true });
    }

    // Keep Tab within the panel while it's open.
    modal.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !modal.classList.contains('is-open')) return;
        var f = Array.prototype.slice.call(
            modal.querySelectorAll('a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])')
        ).filter(function (el) { return el.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first)      { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
})();
