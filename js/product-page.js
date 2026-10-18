/**
 * Golden Hive Blocks — product page additions (includes/product-page.php).
 *
 *   • "Visti di recente": remembers the products this browser opened
 *     (localStorage, newest first) and, when the slot nears the viewport,
 *     fetches them as a product rail (ghb_recent_rail).
 *   • "Chiedi su WhatsApp": the message names the size picked in the
 *     variations form at click time. "Condividi": the system share sheet,
 *     or the link copied where there is none.
 *   • Delivery estimate: "Ordina entro 2 h 15 min e spediamo oggi" and the
 *     delivery window, from the store's cutoff time and transit days. Worked
 *     out here, not in PHP, because the page is cached and "now" is the
 *     visitor's. Business days skip weekends, Italian national holidays and
 *     the store's closed dates.
 *
 * Vanilla JS. The date logic is pure (no DOM) and exported under Node for
 * tests/delivery-estimate-test.js.
 */
(function () {
    'use strict';

    /* ── Calendar (pure) ──
     * A day is a plain {y, m, d} (m 1–12) worked on as a UTC date, so
     * daylight-saving changes never shift it. */

    function utc(day) {
        return new Date(Date.UTC(day.y, day.m - 1, day.d));
    }

    function fromUtc(date) {
        return { y: date.getUTCFullYear(), m: date.getUTCMonth() + 1, d: date.getUTCDate() };
    }

    function addDays(day, n) {
        var date = utc(day);
        date.setUTCDate(date.getUTCDate() + n);
        return fromUtc(date);
    }

    function iso(day) {
        return day.y + '-' + (day.m < 10 ? '0' : '') + day.m + '-' + (day.d < 10 ? '0' : '') + day.d;
    }

    // Easter Sunday (anonymous Gregorian algorithm).
    function easter(y) {
        var a = y % 19, b = Math.floor(y / 100), c = y % 100;
        var d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
        var g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
        var i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7;
        var m = Math.floor((a + 11 * h + 22 * l) / 451);
        var month = Math.floor((h + l - 7 * m + 114) / 31);
        return { y: y, m: month, d: ((h + l - 7 * m + 114) % 31) + 1 };
    }

    // Italian national holidays: no shipping, no deliveries.
    var FIXED_HOLIDAYS = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];

    function isHoliday(day) {
        if (FIXED_HOLIDAYS.indexOf(iso(day).slice(5)) !== -1) return true;
        return iso(day) === iso(addDays(easter(day.y), 1)); // Pasquetta
    }

    function isWorkday(day) {
        var dow = utc(day).getUTCDay();
        return dow !== 0 && dow !== 6 && !isHoliday(day);
    }

    function nextWorkday(day, closed) {
        var next = addDays(day, 1);
        while (!isWorkday(next) || closed.indexOf(iso(next)) !== -1) next = addDays(next, 1);
        return next;
    }

    function addWorkdays(day, n) {
        var out = day;
        for (var i = 0; i < n; i++) {
            out = addDays(out, 1);
            while (!isWorkday(out)) out = addDays(out, 1);
        }
        return out;
    }

    /**
     * When an order placed now ships and arrives.
     *
     * @param {{y,m,d,h,mi}} now  Wall-clock time in the store's time zone.
     * @param {{cutoff:string,min:number,max:number,closed:string[]}} cfg
     * @return {{today:boolean, minutesLeft:number, ship:{y,m,d}, from:{y,m,d}, to:{y,m,d}}}
     */
    function estimate(now, cfg) {
        var closed = cfg.closed || [];
        var parts = String(cfg.cutoff || '13:00').split(':');
        var cutoff = (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
        var today = { y: now.y, m: now.m, d: now.d };
        var minutesLeft = cutoff - (now.h * 60 + now.mi);
        var shipsToday = isWorkday(today) && closed.indexOf(iso(today)) === -1 && minutesLeft > 0;
        var ship = shipsToday ? today : nextWorkday(today, closed);

        return {
            today: shipsToday,
            minutesLeft: shipsToday ? minutesLeft : 0,
            ship: ship,
            from: addWorkdays(ship, cfg.min || 0),
            to: addWorkdays(ship, Math.max(cfg.min || 0, cfg.max || 0))
        };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { easter: easter, isWorkday: isWorkday, estimate: estimate };
        return;
    }

    /* ── Browser ── */

    var cfg = window.ghbProductPage || {};

    /* Delivery estimate */

    function storeNow(tz) {
        var out = {};
        try {
            new Intl.DateTimeFormat('en-GB', {
                timeZone: tz, hourCycle: 'h23',
                year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric'
            }).formatToParts(new Date()).forEach(function (p) { out[p.type] = parseInt(p.value, 10); });
        } catch (e) {
            var d = new Date();
            out = { year: d.getFullYear(), month: d.getMonth() + 1, day: d.getDate(), hour: d.getHours(), minute: d.getMinutes() };
        }
        return { y: out.year, m: out.month, d: out.day, h: out.hour % 24, mi: out.minute };
    }

    function fmt(day, opts) {
        opts.timeZone = 'UTC';
        return new Intl.DateTimeFormat('it-IT', opts).format(utc(day));
    }

    function duration(minutes) {
        var h = Math.floor(minutes / 60), m = minutes % 60;
        return h ? h + ' h ' + m + ' min' : m + ' min';
    }

    function line(text, strong, after) {
        var p = document.createElement('p');
        p.className = 'ghb-delivery__line';
        p.appendChild(document.createTextNode(text));
        var b = document.createElement('strong');
        b.textContent = strong;
        p.appendChild(b);
        if (after) p.appendChild(document.createTextNode(after));
        return p;
    }

    function renderDelivery(box, conf) {
        var now = storeNow(conf.tz);
        var est = estimate(now, conf);
        var short = { weekday: 'short', day: 'numeric', month: 'short' };

        box.textContent = '';
        if (est.today) {
            box.appendChild(line('Ordina entro ', duration(est.minutesLeft), ' e spediamo oggi'));
        } else {
            var tomorrow = addDays({ y: now.y, m: now.m, d: now.d }, 1);
            box.appendChild(line('Spediamo ', iso(est.ship) === iso(tomorrow)
                ? 'domani'
                : fmt(est.ship, { weekday: 'long', day: 'numeric', month: 'long' })));
        }
        box.appendChild(line('Consegna stimata ', iso(est.from) === iso(est.to)
            ? fmt(est.from, short)
            : fmt(est.from, short) + ' – ' + fmt(est.to, short)));
        box.hidden = false;
    }

    function initDelivery() {
        var box = document.querySelector('[data-ghb-delivery]');
        if (!box) return;
        var conf;
        try { conf = JSON.parse(box.getAttribute('data-ghb-delivery')); } catch (e) { return; }

        renderDelivery(box, conf);
        // The countdown and the "today" cutoff move with the clock.
        setInterval(function () {
            if (!box.classList.contains('is-oos')) renderDelivery(box, conf);
        }, 60000);

        // A sold-out size picked (CommerceKit's waitlist) ships nothing.
        var form = document.querySelector('form.variations_form');
        if (form && window.jQuery) {
            window.jQuery(form)
                .on('found_variation', function (e, variation) {
                    var oos = !!variation && !variation.is_in_stock;
                    box.classList.toggle('is-oos', oos);
                    box.hidden = oos;
                })
                .on('reset_data', function () {
                    box.classList.remove('is-oos');
                    box.hidden = false;
                });
        }
    }

    /* WhatsApp + share */

    // The size picked in the variations form: CommerceKit's swatches and
    // WooCommerce's dropdown both keep the form's <select> current.
    function pickedSize(field) {
        var form = document.querySelector('form.variations_form');
        var select = form && field ? form.querySelector('select[name="' + field + '"]') : null;
        if (!select || !select.value || select.selectedIndex < 0) return '';
        return select.options[select.selectedIndex].text.trim();
    }

    function initActions() {
        var row = document.querySelector('.ghb-pp-actions');
        if (!row) return;
        var name = row.getAttribute('data-name') || '';
        var url = row.getAttribute('data-url') || location.href;

        var wa = row.querySelector('[data-ghb-whatsapp]');
        if (wa) {
            // Rewritten before the browser follows it, with the size picked by then.
            wa.addEventListener('click', function () {
                var size = pickedSize(row.getAttribute('data-size-field'));
                var text = 'Ciao! Vorrei informazioni su ' + name + (size ? ', taglia ' + size : '') + ': ' + url;
                wa.href = 'https://wa.me/' + wa.getAttribute('data-ghb-whatsapp') + '?text=' + encodeURIComponent(text);
            });
        }

        var share = row.querySelector('[data-ghb-share]');
        if (!share || !(navigator.share || (navigator.clipboard && window.isSecureContext))) return;
        share.hidden = false;
        var label = share.querySelector('span');
        share.addEventListener('click', function () {
            if (navigator.share) {
                navigator.share({ title: name, url: url }).catch(function () {});
                return;
            }
            navigator.clipboard.writeText(url).then(function () {
                if (!label) return;
                label.textContent = 'Link copiato';
                setTimeout(function () { label.textContent = 'Condividi'; }, 2000);
            }).catch(function () {});
        });
    }

    /* Recently viewed */

    var RECENT_KEY = 'ghb_recent_products';
    var RECENT_MAX = 12; // GHB_RECENT_MAX in includes/product-page.php
    var RECENT_MIN = 2;  // fewer than this isn't worth a rail

    function readRecent() {
        try {
            var ids = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
            return Array.isArray(ids)
                ? ids.filter(function (id) { return typeof id === 'number' && id > 0; })
                : [];
        } catch (e) {
            return [];
        }
    }

    function remember(id, ids) {
        try {
            var next = [id].concat(ids.filter(function (x) { return x !== id; })).slice(0, RECENT_MAX);
            localStorage.setItem(RECENT_KEY, JSON.stringify(next));
        } catch (e) { /* private mode / storage off: no rail, nothing else lost */ }
    }

    function initRecent() {
        var slot = document.querySelector('[data-ghb-recent]');
        if (!slot) return;
        var current = parseInt(slot.getAttribute('data-ghb-recent'), 10);
        var stored = readRecent();
        var others = stored.filter(function (id) { return id !== current; }).slice(0, RECENT_MAX);
        if (current > 0) remember(current, stored);
        if (others.length < RECENT_MIN || !cfg.ajaxUrl) return;

        var load = function () {
            fetch(cfg.ajaxUrl + '?action=ghb_recent_rail&ids=' + others.join(','))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res || !res.success || !res.data || !res.data.html) return;
                    // Server-rendered product cards (the rail template).
                    slot.innerHTML = res.data.html;
                    if (window.ghbProductRail) window.ghbProductRail.init(slot);
                })
                .catch(function () {});
        };

        if (!('IntersectionObserver' in window)) {
            load();
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            if (!entries.some(function (e) { return e.isIntersecting; })) return;
            io.disconnect();
            load();
        }, { rootMargin: '600px 0px' });
        io.observe(slot);
    }

    function boot() {
        initDelivery();
        initActions();
        initRecent();
    }

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot);
    }
})();
