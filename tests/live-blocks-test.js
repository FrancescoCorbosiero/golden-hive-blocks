/**
 * Tests for the live blocks' pure helpers (js/live-blocks.js): the drop
 * countdown text and the store's opening hours.
 *
 * Run from the plugin root:   node tests/live-blocks-test.js
 */
'use strict';

const path = require('path');
const lb = require(path.join(__dirname, '..', 'js', 'live-blocks.js'));

let passes = 0;
let failures = 0;

function same(actual, expected, what) {
    const a = JSON.stringify(actual);
    const e = JSON.stringify(expected);
    if (a === e) {
        passes++;
        return;
    }
    failures++;
    console.log(`FAIL: ${what}\n      expected ${e}\n      actual   ${a}`);
}

const at = (y, m, d, h, mi) => ({ y, m, d, h, mi });
const H = 3600e3;

/* ---------------------------------------------------------------- */

// Drops
same(lb.dropParts(((2 * 24 + 3) * 60 + 4) * 60e3 + 5e3), { d: 2, h: 3, m: 4, s: 5 }, 'milliseconds split into d h m s');
same(lb.dropParts(-5), { d: 0, h: 0, m: 0, s: 0 }, 'past is zero');
same(lb.dropStatus(3 * 24 * H + 1000), 'Esce tra 3 giorni', 'days away');
same(lb.dropStatus(24 * H + 1000), 'Esce tra 1 giorno', 'one day away, singular');
same(lb.dropStatus(5 * H + 20 * 60e3), 'Esce tra 5 h 20 min', 'hours away');
same(lb.dropStatus(12 * 60e3 + 5e3), 'Esce tra 12 min 05 s', 'the last hour counts seconds');
same(lb.dropStatus(45e3), 'Esce tra 45 s', 'the last minute counts seconds only');
same(lb.dropStatus(0), 'Disponibile ora', 'released');

// Hours parsing
same(lb.parseHours('10.30 - 13.00 | 15.30 - 19.30'), [[630, 780], [930, 1170]], 'two ranges with dots');
same(lb.parseHours('10:00–19:30'), [[600, 1170]], 'colons and an en dash');
same(lb.parseHours('9 - 13'), [[540, 780]], 'whole hours');
same(lb.parseHours('Chiuso'), [], 'closed');
same(lb.parseHours('Su appuntamento'), null, 'unreadable');
same(lb.dayIndex('Lunedì'), 1, 'Monday');
same(lb.dayIndex('domenica'), 0, 'Sunday, lowercase');
same(lb.dayIndex('Holiday'), null, 'not a day');

// The store's week: Monday–Saturday 10.30–13.00 | 15.30–19.30, Sunday closed.
const rows = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato']
    .map((day) => ({ day, time: '10.30 - 13.00 | 15.30 - 19.30' }))
    .concat([{ day: 'Domenica', time: 'Chiuso' }]);
const week = lb.weekFrom(rows);
const opts = { holidays: true, closed: [] };
const text = (now, o) => lb.storeStatus(now, week, o || opts).text;

same(text(at(2026, 10, 19, 11, 0)), 'Aperto ora · chiude alle 13:00', 'Monday morning, open');
same(text(at(2026, 10, 19, 12, 45)), 'Aperto · chiude tra 15 min', 'closing soon');
same(lb.storeStatus(at(2026, 10, 19, 12, 45), week, opts).soon, true, 'flagged as closing soon');
same(text(at(2026, 10, 19, 9, 0)), 'Chiuso ora · apre alle 10:30', 'before opening');
same(text(at(2026, 10, 19, 14, 0)), 'Chiuso ora · riapre alle 15:30', 'lunch break');
same(text(at(2026, 10, 19, 20, 0)), 'Chiuso · apre domani alle 10:30', 'evening');
same(text(at(2026, 10, 24, 20, 0)), 'Chiuso · apre lunedì alle 10:30', 'Saturday evening: Sunday is closed');
same(text(at(2026, 12, 8, 11, 0)), 'Chiuso per festività · apre domani alle 10:30', 'Immacolata is a holiday');
same(text(at(2026, 12, 8, 11, 0), { holidays: false, closed: [] }), 'Aperto ora · chiude alle 13:00', 'unless holidays are open');
same(text(at(2026, 10, 20, 11, 0), { holidays: true, closed: ['2026-10-20'] }), 'Chiuso · apre domani alle 10:30', 'a closed date: open again the next day');
same(text(at(2026, 12, 24, 20, 0)), 'Chiuso · apre lunedì alle 10:30', 'Christmas and Santo Stefano skipped, Sunday closed: Monday 28');
same(lb.storeStatus(at(2026, 10, 19, 11, 0), lb.weekFrom([{ day: 'Lunedì', time: 'Su appuntamento' }]), opts), null, 'no readable hours, no status');

/* ---------------------------------------------------------------- */

console.log(`${passes} passed, ${failures} failed`);
process.exit(failures > 0 ? 1 : 0);
