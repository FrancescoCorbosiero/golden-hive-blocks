/**
 * Golden Hive Blocks — shared calendar helpers.
 *
 * Used by the delivery estimate (js/product-page.js) and by the store's
 * opening hours and the drops (js/live-blocks.js). A day is a plain
 * {y, m, d} (m 1–12) worked on as a UTC date, so daylight-saving changes
 * never shift it; "now" is read as wall-clock time in the store's time zone.
 * Italian national holidays, Easter Monday included.
 *
 * Pure (no DOM): window.ghbCalendar in the browser, module.exports under
 * Node for the tests.
 */
(function (root) {
    'use strict';

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

    // 0 = Sunday … 6 = Saturday.
    function weekday(day) {
        return utc(day).getUTCDay();
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

    // Italian national holidays.
    var FIXED_HOLIDAYS = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'];

    function isHoliday(day) {
        if (FIXED_HOLIDAYS.indexOf(iso(day).slice(5)) !== -1) return true;
        return iso(day) === iso(addDays(easter(day.y), 1)); // Pasquetta
    }

    function isWorkday(day) {
        var dow = weekday(day);
        return dow !== 0 && dow !== 6 && !isHoliday(day);
    }

    /**
     * Wall-clock time in a time zone: {y, m, d, h, mi}. Falls back to the
     * device's own clock where the zone isn't known.
     */
    function nowIn(tz, date) {
        var out = {};
        date = date || new Date();
        try {
            new Intl.DateTimeFormat('en-GB', {
                timeZone: tz, hourCycle: 'h23',
                year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric'
            }).formatToParts(date).forEach(function (p) { out[p.type] = parseInt(p.value, 10); });
        } catch (e) {
            out = { year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate(), hour: date.getHours(), minute: date.getMinutes() };
        }
        return { y: out.year, m: out.month, d: out.day, h: out.hour % 24, mi: out.minute };
    }

    // "lunedì 20 ottobre", "mar 21 ott": Intl in Italian, read as a UTC day.
    function format(day, opts) {
        var o = {};
        for (var k in opts) o[k] = opts[k];
        o.timeZone = 'UTC';
        return new Intl.DateTimeFormat('it-IT', o).format(utc(day));
    }

    var api = {
        utc: utc,
        fromUtc: fromUtc,
        addDays: addDays,
        iso: iso,
        weekday: weekday,
        easter: easter,
        isHoliday: isHoliday,
        isWorkday: isWorkday,
        nowIn: nowIn,
        format: format
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.ghbCalendar = api;
    }
})(this);
