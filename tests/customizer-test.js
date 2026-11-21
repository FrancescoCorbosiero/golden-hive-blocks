/**
 * Tests for the Customizer controls' pure helpers (js/customizer-controls.js):
 * the rows a list shows, moving one, and a checklist's value.
 *
 * Run from the plugin root:   node tests/customizer-test.js
 */
'use strict';

const path = require('path');
const cc = require(path.join(__dirname, '..', 'js', 'customizer-controls.js'));

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

/* ---------------------------------------------------------------- *
 * Free lists (badges, tiles)
 * ---------------------------------------------------------------- */

const badge = {
    fields: [
        { key: 'icon', type: 'select', choices: [['authentic', 'Autenticità'], ['shipping', 'Spedizione']] },
        { key: 'title', type: 'text' },
    ],
    max: 3,
};

same(cc.rowFrom({}, badge.fields), { icon: 'authentic', title: '' }, 'a new row: the first choice, empty text');
same(cc.rowFrom({ icon: 'shipping', title: 7, extra: 'x' }, badge.fields), { icon: 'shipping', title: '7' }, 'only the fields, as strings');
same(cc.rowsFor(null, badge), [], 'nothing saved, no rows');
same(cc.rowsFor('nonsense', badge), [], 'a stray value is no rows');
same(cc.rowsFor([{ title: 'a' }, null, 'x', { title: 'b' }, { title: 'c' }, { title: 'd' }], badge).map((r) => r.title), ['a', 'b', 'c'], 'non-rows dropped, at most max');

/* ---------------------------------------------------------------- *
 * Fixed lists (the shop filters)
 * ---------------------------------------------------------------- */

const facets = {
    fields: [{ key: 'on', type: 'checkbox' }, { key: 'label', type: 'text' }],
    fixed: [['price', 'Prezzo'], ['cat', 'Categoria'], ['pa_taglia', 'Taglia'], ['pa_marca', 'Marca']],
};

same(
    cc.rowsFor([{ key: 'pa_taglia', on: true, label: 'Taglia' }, { key: 'price', on: 'yes', label: 'Prezzo' }], facets),
    [
        { on: true, label: 'Taglia', key: 'pa_taglia' },
        { on: true, label: 'Prezzo', key: 'price' },
        { on: false, label: 'Categoria', key: 'cat' },
        { on: false, label: 'Marca', key: 'pa_marca' },
    ],
    'saved rows keep their order; the rest follow, off, with their own name'
);
same(
    cc.rowsFor([{ key: 'pa_anno', on: true, label: 'Anno' }, { key: 'cat', on: true, label: 'Cat' }, { key: 'cat', on: false, label: 'again' }], facets).map((r) => r.key),
    ['cat', 'price', 'pa_taglia', 'pa_marca'],
    'a key the store no longer has is dropped, a repeated one kept once'
);

/* ---------------------------------------------------------------- *
 * Moving and ticking
 * ---------------------------------------------------------------- */

const rows = ['a', 'b', 'c'];
same(cc.move(rows, 0, -1), false, 'the first row cannot go up');
same(cc.move(rows, 2, 1), false, 'the last row cannot go down');
same(cc.move(rows, 1, -1) && rows, ['b', 'a', 'c'], 'moved up');
same(cc.picked([[12, 'Senza categoria'], [7, 'Saldi'], [30, 'Nuovi']], (v) => v !== '7'), ['12', '30'], 'ticked values as strings, in the options\' order');

console.log(`${passes} passed, ${failures} failed`);
process.exit(failures > 0 ? 1 : 0);
