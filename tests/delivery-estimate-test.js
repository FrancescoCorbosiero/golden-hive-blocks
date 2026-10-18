/**
 * Tests for the delivery estimate's date logic (js/product-page.js).
 *
 * Run from the plugin root:   node tests/delivery-estimate-test.js
 *
 * No framework, no browser: the calendar part of the script is pure and
 * exported under Node.
 */
'use strict';

const path = require('path');
const { easter, isWorkday, estimate } = require(path.join(__dirname, '..', 'js', 'product-page.js'));

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

const day = (y, m, d) => ({ y, m, d });
const at = (y, m, d, h, mi) => ({ y, m, d, h, mi });
const cfg = { cutoff: '13:00', min: 1, max: 3, closed: [] };

/* ---------------------------------------------------------------- */

same(easter(2026), day(2026, 4, 5), 'Easter 2026 is 5 April');
same(easter(2027), day(2027, 3, 28), 'Easter 2027 is 28 March');
same(isWorkday(day(2026, 4, 6)), false, 'Pasquetta is a holiday');
same(isWorkday(day(2026, 12, 8)), false, 'Immacolata is a holiday');
same(isWorkday(day(2026, 10, 17)), false, 'Saturday is not a workday');
same(isWorkday(day(2026, 10, 19)), true, 'an ordinary Monday is');

// Monday 19 Oct 2026, 10:30: ships today, delivered Tue–Thu.
let e = estimate(at(2026, 10, 19, 10, 30), cfg);
same(e.today, true, 'before the cutoff it ships today');
same(e.minutesLeft, 150, 'with 2 h 30 min left');
same([e.from, e.to], [day(2026, 10, 20), day(2026, 10, 22)], 'delivery Tue 20 – Thu 22');

// Same Monday at the cutoff: ships Tuesday.
e = estimate(at(2026, 10, 19, 13, 0), cfg);
same(e.today, false, 'at the cutoff it no longer ships today');
same(e.ship, day(2026, 10, 20), 'it ships the next workday');

// Friday after the cutoff: ships Monday, delivery skips the weekend.
e = estimate(at(2026, 10, 23, 18, 0), cfg);
same(e.ship, day(2026, 10, 26), 'Friday evening ships on Monday');
same([e.from, e.to], [day(2026, 10, 27), day(2026, 10, 29)], 'delivered Tue 27 – Thu 29');

// Saturday morning: nothing ships on a weekend.
e = estimate(at(2026, 10, 24, 9, 0), cfg);
same([e.today, e.ship], [false, day(2026, 10, 26)], 'Saturday ships on Monday');

// Thursday 31 Dec after the cutoff: Friday 1 Jan is a holiday, then the weekend.
e = estimate(at(2026, 12, 31, 15, 0), cfg);
same(e.ship, day(2027, 1, 4), 'New Year: Fri 1 is a holiday, the weekend follows, ships Mon 4');
same(e.from, day(2027, 1, 5), 'and arrives from Tue 5');

// A store closure moves the shipping day, not the carriers' days.
e = estimate(at(2026, 10, 19, 10, 0), Object.assign({}, cfg, { closed: ['2026-10-19', '2026-10-20'] }));
same([e.today, e.ship], [false, day(2026, 10, 21)], 'closed days are skipped for shipping');

// Delivery window of one day.
e = estimate(at(2026, 10, 19, 10, 0), Object.assign({}, cfg, { min: 2, max: 2 }));
same([e.from, e.to], [day(2026, 10, 21), day(2026, 10, 21)], 'min = max gives one day');

// Pasquetta 2026 is Monday 6 April: Friday 3rd ships, delivery skips the weekend and Monday.
e = estimate(at(2026, 4, 3, 9, 0), cfg);
same([e.ship, e.from, e.to], [day(2026, 4, 3), day(2026, 4, 7), day(2026, 4, 9)], 'Easter weekend and Monday are skipped');

/* ---------------------------------------------------------------- */

console.log(`${passes} passed, ${failures} failed`);
process.exit(failures > 0 ? 1 : 0);
