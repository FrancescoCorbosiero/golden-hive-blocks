/**
 * Golden Hive — Bank details drawer: copy-to-clipboard only.
 *
 * Open/close, ESC, backdrop, focus trap e scroll lock sono gestiti dal
 * modal engine condiviso (js/animations.js) via data-gh-modal. Qui vive
 * solo la copia di IBAN/BIC, con fallback per i browser senza
 * navigator.clipboard (richiede HTTPS) e annuncio aria-live.
 */
(function () {
    'use strict';

    var drawer = document.getElementById('gh-bank-details');
    if (!drawer) return;

    var status = drawer.querySelector('[data-gh-copy-status]');

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        drawer.appendChild(ta);
        ta.select();
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) { /* niente copia disponibile */ }
        drawer.removeChild(ta);
        return ok ? Promise.resolve() : Promise.reject();
    }

    function copy(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () {
                return fallbackCopy(text);
            });
        }
        return fallbackCopy(text);
    }

    drawer.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-gh-copy]');
        if (!btn) return;

        var valueEl = document.getElementById(btn.getAttribute('data-gh-copy'));
        if (!valueEl) return;

        var text = (valueEl.textContent || '').trim();
        var label = btn.textContent;

        copy(text).then(function () {
            btn.textContent = 'Copiato!';
            btn.classList.add('is-copied');
            if (status) status.textContent = 'Copiato negli appunti';
            setTimeout(function () {
                btn.textContent = label;
                btn.classList.remove('is-copied');
                if (status) status.textContent = '';
            }, 2000);
        }).catch(function () {
            if (status) status.textContent = 'Copia non riuscita — seleziona e copia manualmente';
        });
    });
})();
