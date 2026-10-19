/**
 * Golden Hive Blocks — live blocks.
 *
 * The parts of the blocks that depend on "now" or on this browser, so they
 * stay true on a cached page:
 *
 *   • SizeRail — "Nella tua taglia" ([gh_size_rail], includes/size-rail.php):
 *     the size this browser remembers (js/animations.js SizeMemory), or a
 *     size picker; the rail itself comes from ghb_size_rail.
 *   • Drops — every [data-ghb-drop] (UTC seconds): the product page's
 *     countdown (it reloads the page at the release, includes/drops.php), the
 *     Drop Countdown and Drop Calendar blocks ("Esce tra 3 giorni",
 *     "Ricordamelo" → "Acquista ora").
 *   • StoreHours — [data-ghb-hours] (Vieni in negozio, Orari): "Aperto ora ·
 *     chiude alle 19:30", today's row, in the store's time zone, weekends,
 *     Italian holidays and closed dates included (js/calendar.js).
 *   • MapFacade — [data-gh-map]: the Google map loads only when asked
 *     (faster pages, no Google cookies before then).
 *
 * Loaded only by the blocks and shortcodes that need it. Pure helpers (no
 * DOM) are exported under Node for tests/live-blocks-test.js.
 */
(function () {
    'use strict';

    var isNode = typeof module !== 'undefined' && module.exports;
    var cal = isNode ? require('./calendar.js') : window.ghbCalendar;

    /* ── Pure helpers ── */

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    // Milliseconds left → whole days, hours, minutes, seconds.
    function dropParts(ms) {
        var s = Math.max(0, Math.floor(ms / 1000));
        return { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
    }

    // "Esce tra 3 giorni", "Esce tra 5 h 20 min", "Esce tra 12 min 05 s",
    // "Esce tra 45 s", "Disponibile ora".
    function dropStatus(ms) {
        if (ms <= 0) return 'Disponibile ora';
        var p = dropParts(ms);
        if (p.d >= 1) return p.d === 1 ? 'Esce tra 1 giorno' : 'Esce tra ' + p.d + ' giorni';
        if (p.h >= 1) return 'Esce tra ' + p.h + ' h ' + p.m + ' min';
        if (p.m >= 1) return 'Esce tra ' + p.m + ' min ' + pad(p.s) + ' s';
        return 'Esce tra ' + p.s + ' s';
    }

    // "10.30 - 13.00 | 15.30 - 19.30" → [[630, 780], [930, 1170]]; "Chiuso" → []; unreadable → null.
    function parseHours(text) {
        var t = String(text || '').trim().toLowerCase();
        if (!t) return null;
        if (/^chius/.test(t)) return [];
        var ranges = [];
        var re = /(\d{1,2})(?:[.:](\d{2}))?\s*[-–—]\s*(\d{1,2})(?:[.:](\d{2}))?/g;
        var m;
        while ((m = re.exec(t))) {
            var from = parseInt(m[1], 10) * 60 + parseInt(m[2] || '0', 10);
            var to = parseInt(m[3], 10) * 60 + parseInt(m[4] || '0', 10);
            if (to > from) ranges.push([from, to]);
        }
        return ranges.length ? ranges : null;
    }

    // "Lunedì" → 1 … "Sabato" → 6, "Domenica" → 0 (Date.getDay numbering).
    function dayIndex(name) {
        var map = { lun: 1, mar: 2, mer: 3, gio: 4, ven: 5, sab: 6, dom: 0 };
        var k = String(name || '').trim().toLowerCase().slice(0, 3);
        return Object.prototype.hasOwnProperty.call(map, k) ? map[k] : null;
    }

    // Rows {day, time} → {0..6: ranges | null}.
    function weekFrom(rows) {
        var week = {};
        (rows || []).forEach(function (row) {
            var i = dayIndex(row.day);
            if (i !== null) week[i] = parseHours(row.time);
        });
        return week;
    }

    function hhmm(min) {
        return pad(Math.floor(min / 60) % 24) + ':' + pad(min % 60);
    }

    /**
     * Open or closed now, and until when.
     *
     * @param {{y,m,d,h,mi}} now  Wall-clock time in the store's time zone.
     * @param {Object} week       weekFrom(rows).
     * @param {{holidays:boolean, closed:string[]}} opts
     * @return {{open:boolean, soon:boolean, text:string}|null} null when no hours can be read.
     */
    function storeStatus(now, week, opts) {
        opts = opts || {};
        var known = Object.keys(week).some(function (k) { return week[k] !== null && week[k] !== undefined; });
        if (!known) return null;

        var closed = opts.closed || [];
        var today = { y: now.y, m: now.m, d: now.d };
        var holiday = function (day) { return !!opts.holidays && cal.isHoliday(day); };
        var shut = function (day) { return holiday(day) || closed.indexOf(cal.iso(day)) !== -1; };
        var mins = now.h * 60 + now.mi;
        var todays = shut(today) ? [] : (week[cal.weekday(today)] || []);
        var i;

        for (i = 0; i < todays.length; i++) {
            if (mins >= todays[i][0] && mins < todays[i][1]) {
                var left = todays[i][1] - mins;
                return left <= 30
                    ? { open: true, soon: true, text: 'Aperto · chiude tra ' + left + ' min' }
                    : { open: true, soon: false, text: 'Aperto ora · chiude alle ' + hhmm(todays[i][1]) };
            }
        }
        for (i = 0; i < todays.length; i++) {
            if (mins < todays[i][0]) {
                return { open: false, soon: false, text: 'Chiuso ora · ' + (i > 0 ? 'riapre' : 'apre') + ' alle ' + hhmm(todays[i][0]) };
            }
        }

        var lead = holiday(today) ? 'Chiuso per festività' : 'Chiuso';
        for (i = 1; i <= 7; i++) {
            var day = cal.addDays(today, i);
            var ranges = shut(day) ? [] : (week[cal.weekday(day)] || []);
            if (ranges.length) {
                var when = i === 1 ? 'domani' : cal.format(day, { weekday: 'long' });
                return { open: false, soon: false, text: lead + ' · apre ' + when + ' alle ' + hhmm(ranges[0][0]) };
            }
        }
        return { open: false, soon: false, text: lead };
    }

    var api = {
        dropParts: dropParts,
        dropStatus: dropStatus,
        parseHours: parseHours,
        dayIndex: dayIndex,
        weekFrom: weekFrom,
        storeStatus: storeStatus
    };

    if (isNode) {
        module.exports = api;
        return;
    }

    /* ── Browser ── */

    // js/animations.js SizeMemory, or the same storage read directly.
    var memory = {
        STORE_KEY: 'ghb_my_size',
        get: function () {
            var m = window.GoldenHive && window.GoldenHive.sizeMemory;
            if (m) return m.get();
            try {
                var v = JSON.parse(localStorage.getItem(this.STORE_KEY) || 'null');
                return v && v.key ? v : null;
            } catch (e) {
                return null;
            }
        },
        save: function (label) {
            var m = window.GoldenHive && window.GoldenHive.sizeMemory;
            if (m) {
                m.save(label);
                return;
            }
            var key = String(label || '').toLowerCase().replace(/[^a-z0-9]/g, '');
            if (!key) return;
            try {
                localStorage.setItem(this.STORE_KEY, JSON.stringify({ key: key, label: String(label).trim(), t: Date.now() }));
            } catch (e) { /* storage off */ }
        }
    };

    // Runs fn once the element is about to scroll into view.
    function whenNear(el, fn) {
        if (!('IntersectionObserver' in window)) {
            fn();
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            if (!entries.some(function (e) { return e.isIntersecting; })) return;
            io.disconnect();
            fn();
        }, { rootMargin: '400px 0px' });
        io.observe(el);
    }

    /* SizeRail */

    var SizeRail = {
        init: function () {
            document.querySelectorAll('[data-ghb-size-rail]').forEach(function (root) {
                if (root.hasAttribute('data-ghb-ready')) return;
                root.setAttribute('data-ghb-ready', '');
                SizeRail.setup(root);
            });
        },

        setup: function (root) {
            var src = root.getAttribute('data-src');
            var picker = root.querySelector('[data-ghb-size-picker]');
            var body = root.querySelector('[data-ghb-size-body]');
            var current = root.querySelector('[data-ghb-size-current]');
            var change = root.querySelector('[data-ghb-size-change]');
            if (!src || !picker || !body) return;

            var request = 0;

            function mark(key) {
                picker.querySelectorAll('[data-ghb-size-key]').forEach(function (chip) {
                    chip.setAttribute('aria-pressed', chip.getAttribute('data-ghb-size-key') === key ? 'true' : 'false');
                });
            }

            function showPicker(show) {
                picker.hidden = !show;
                if (change) change.hidden = show || !current || current.hidden;
                root.classList.toggle('is-picking', show);
            }

            function setCurrent(label) {
                if (!current) return;
                current.textContent = label || '';
                current.hidden = !label;
            }

            function load(key, label) {
                var mine = ++request;
                mark(key);
                setCurrent(label);
                showPicker(false);
                root.classList.add('is-loading');
                body.innerHTML = '<div class="ghb-size-rail__skeleton" aria-hidden="true"><span></span><span></span><span></span><span></span></div>';

                fetch(src + '&size=' + encodeURIComponent(key))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (mine !== request) return;
                        root.classList.remove('is-loading');
                        if (!res || !res.success || !res.data) {
                            // A size the store no longer carries: ask again.
                            body.textContent = '';
                            setCurrent('');
                            showPicker(true);
                            return;
                        }
                        setCurrent(res.data.label);
                        var saved = memory.get();
                        if (!saved || saved.label !== res.data.label) memory.save(res.data.label);
                        // Server-rendered product cards (the rail template).
                        body.innerHTML = res.data.html || res.data.empty || '';
                        if (res.data.html && window.ghbProductRail) window.ghbProductRail.init(body);
                    })
                    .catch(function () {
                        if (mine !== request) return;
                        root.classList.remove('is-loading');
                        body.textContent = '';
                        showPicker(true);
                    });
            }

            picker.addEventListener('click', function (e) {
                var chip = e.target.closest('[data-ghb-size-key]');
                if (!chip) return;
                var label = chip.getAttribute('data-ghb-size-label') || chip.textContent.trim();
                memory.save(label);
                load(chip.getAttribute('data-ghb-size-key'), label);
            });

            if (change) {
                change.addEventListener('click', function () {
                    showPicker(true);
                    var first = picker.querySelector('[aria-pressed="true"]') || picker.querySelector('[data-ghb-size-key]');
                    if (first) first.focus();
                });
            }

            var saved = memory.get();
            if (!saved) {
                showPicker(true);
                return;
            }
            setCurrent(saved.label);
            showPicker(false);
            whenNear(root, function () { load(saved.key, saved.label); });
        }
    };

    /* Drops */

    var Drops = {
        init: function () {
            document.querySelectorAll('[data-ghb-drop]').forEach(function (root) {
                if (root.hasAttribute('data-ghb-ready')) return;
                root.setAttribute('data-ghb-ready', '');
                Drops.setup(root);
            });
            // The Drop Countdown block's own timer (js/animations.js) ended.
            document.addEventListener('gh:countdown-end', function (e) {
                var root = e.target.closest && e.target.closest('[data-ghb-drop]');
                if (root) Drops.release(root);
            });
        },

        setup: function (root) {
            var ts = parseInt(root.getAttribute('data-ghb-drop'), 10) * 1000;
            if (!ts) return;
            var timer = root.querySelector('[data-ghb-drop-timer]');
            var status = root.querySelector('[data-ghb-drop-status]');

            function tick() {
                var left = ts - Date.now();
                if (timer) {
                    var p = dropParts(left);
                    ['d', 'h', 'm', 's'].forEach(function (u) {
                        var cell = timer.querySelector('[data-unit="' + u + '"]');
                        if (cell) cell.textContent = pad(p[u]);
                    });
                }
                if (status) status.textContent = dropStatus(left);
                if (left <= 0) {
                    Drops.release(root);
                    return false;
                }
                return true;
            }

            if (!tick()) return;
            var handle = setInterval(function () {
                if (!tick()) clearInterval(handle);
            }, 1000);
        },

        // Out: "Acquista ora" replaces "Ricordamelo"; a product page reloads.
        release: function (root) {
            if (root.classList.contains('is-released')) return;
            root.classList.add('is-released', 'is-live');
            root.querySelectorAll('[data-ghb-drop-before]').forEach(function (n) { n.hidden = true; });
            root.querySelectorAll('[data-ghb-drop-after]').forEach(function (n) { n.hidden = false; });
            var status = root.querySelector('[data-ghb-drop-status]');
            if (status) status.textContent = 'Disponibile ora';
            if (root.hasAttribute('data-ghb-drop-reload')) Drops.reload(root.getAttribute('data-ghb-drop'));
        },

        // The server unlocks the product at the release: load the page fresh
        // (a query string skips the page cache). If it still isn't out — this
        // clock runs ahead of the server's — try again, a few times.
        reload: function (ts) {
            var key = 'ghb_drop_reload_' + ts;
            var tries = 0;
            try { tries = parseInt(sessionStorage.getItem(key) || '0', 10); } catch (e) { /* storage off */ }
            if (tries >= 4) return;
            try { sessionStorage.setItem(key, String(tries + 1)); } catch (e) { /* storage off */ }
            setTimeout(function () {
                var url = new URL(location.href);
                url.searchParams.set('drop', ts);
                location.replace(url.toString());
            }, tries === 0 ? 1500 : 20000);
        }
    };

    /* StoreHours */

    var StoreHours = {
        init: function () {
            if (!cal) return;
            document.querySelectorAll('[data-ghb-hours]').forEach(function (root) {
                if (root.hasAttribute('data-ghb-ready')) return;
                root.setAttribute('data-ghb-ready', '');
                StoreHours.setup(root);
            });
        },

        setup: function (root) {
            var conf;
            try { conf = JSON.parse(root.getAttribute('data-ghb-hours')); } catch (e) { return; }
            var week = weekFrom(conf.rows);
            var statusEl = root.querySelector('[data-ghb-hours-status]');

            function paint() {
                var now = cal.nowIn(conf.tz);
                var today = cal.weekday({ y: now.y, m: now.m, d: now.d });
                root.querySelectorAll('[data-ghb-day]').forEach(function (row) {
                    var isToday = dayIndex(row.getAttribute('data-ghb-day')) === today;
                    row.classList.toggle('is-today', isToday);
                    row.classList.toggle('gh-store-hours__row--today', isToday);
                    var badge = row.querySelector('[data-ghb-today-badge]');
                    if (badge) badge.hidden = !isToday;
                });
                if (!statusEl) return;
                var st = storeStatus(now, week, { holidays: !!conf.holidays, closed: conf.closed || [] });
                if (!st) {
                    statusEl.hidden = true;
                    return;
                }
                statusEl.hidden = false;
                statusEl.classList.toggle('is-open', st.open);
                statusEl.classList.toggle('is-soon', st.soon);
                var text = statusEl.querySelector('[data-ghb-hours-text]') || statusEl;
                text.textContent = st.text;
            }

            paint();
            setInterval(paint, 60000);
        }
    };

    /* MapFacade */

    var MapFacade = {
        init: function () {
            document.addEventListener('click', function (e) {
                var btn = e.target.closest && e.target.closest('[data-gh-map-load]');
                if (!btn) return;
                var box = btn.closest('[data-gh-map]');
                var tpl = box && box.querySelector('template[data-gh-map-frame]');
                if (!tpl) return;
                var frame = tpl.content.cloneNode(true);
                box.querySelectorAll('[data-gh-map-cover]').forEach(function (n) { n.remove(); });
                box.appendChild(frame);
                box.classList.add('is-loaded');
                var iframe = box.querySelector('iframe');
                if (iframe) iframe.focus();
            });
        }
    };

    function boot() {
        SizeRail.init();
        Drops.init();
        StoreHours.init();
        MapFacade.init();
    }

    if (document.readyState !== 'loading') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot);
    }
})();
