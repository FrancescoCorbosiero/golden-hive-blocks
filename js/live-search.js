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

    function open() {
        lastFocus = document.activeElement;
        modal.classList.add('is-open');
        document.body.classList.add('rlv-open');
        setTimeout(function () { if (input) input.focus(); }, 60);
    }
    function close() {
        modal.classList.remove('is-open');
        document.body.classList.remove('rlv-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-rlv-open]'))  { e.preventDefault(); open();  return; }
        if (e.target.closest('[data-rlv-close]')) { e.preventDefault(); close(); }
    });

    document.addEventListener('keydown', function (e) {
        var typing = /^(input|textarea|select)$/i.test(e.target.tagName || '');
        if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); open(); }
        else if (e.key === 'Escape' && modal.classList.contains('is-open')) { close(); }
        else if (e.key === '/' && !typing && !modal.classList.contains('is-open')) { e.preventDefault(); open(); }
    });

    if (input) {
        input.addEventListener('input', function () {
            modal.classList.toggle('has-query', input.value.trim().length > 0);
        });
    }

    if (results) {
        new MutationObserver(function () {
            modal.classList.toggle('has-results', results.children.length > 0);
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
