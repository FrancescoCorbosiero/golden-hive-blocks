/**
 * Tests for the rail arrows (js/product-rail.js) in a real browser.
 *
 * Run from the plugin root:   node tests/rail-test.js
 *
 * Loads tests/fixtures/rail.html (a rail with its arrows below the row and one
 * with them on the sides) in headless Chromium through Playwright. Needs
 * Playwright with Chromium; without it the test says so and exits 0.
 */
'use strict';

const path = require('path');

let chromium;
try {
    ({ chromium } = require('playwright'));
} catch (e) {
    console.log('skipped: Playwright is not installed');
    process.exit(0);
}

const url = 'file://' + path.join(__dirname, 'fixtures', 'rail.html');
let failures = 0;
let passes = 0;

function check(cond, label, detail) {
    if (cond) {
        passes++;
    } else {
        failures++;
        console.log('FAIL ' + label + (detail !== undefined ? ' — ' + JSON.stringify(detail) : ''));
    }
}

const left = (page, sel) => page.$eval(sel + ' .gh-rail__track', (t) => Math.round(t.scrollLeft));

async function clickNext(page, sel) {
    await page.click(sel + ' .gh-rail__nav--next');
    await page.waitForTimeout(700);
}

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(url);
    await page.waitForTimeout(300);

    // Arrows below the row.
    check(await page.$eval('#below', (r) => r.classList.contains('is-scrollable')), 'a rail that overflows is scrollable');
    const start = await left(page, '#below');
    await clickNext(page, '#below');
    const moved = await left(page, '#below');
    check(moved - start > 1000, 'the next arrow moves a whole row', [start, moved]);
    const bar = await page.$eval('#below .gh-rail__progress span', (s) => s.style.width);
    check(/%$/.test(bar) && parseFloat(bar) > 30 && parseFloat(bar) < 50, 'the progress line shows the visible share', bar);
    await page.click('#below .gh-rail__nav--prev');
    await page.waitForTimeout(700);
    check(await left(page, '#below') <= 2, 'the previous arrow comes back');

    // Arrows on the sides.
    const box = await page.$eval('#sides', (r) => {
        const v = r.querySelector('.gh-rail__viewport').getBoundingClientRect();
        const n = r.querySelector('.gh-rail__nav--next').getBoundingClientRect();
        return { inside: n.top > v.top && n.bottom < v.bottom && n.right <= v.right, bar: !!r.querySelector('.gh-rail__bar') };
    });
    check(box.inside && !box.bar, 'side arrows sit over the row, with no bar', box);
    check(await page.$eval('#sides .gh-rail__nav--prev', (b) => getComputedStyle(b).opacity) === '0', 'at the start the back arrow steps aside');
    await clickNext(page, '#sides');
    check(await left(page, '#sides') > 1000, 'a side arrow moves a whole row');

    // A rail copied in after load keeps the markup, not the listeners.
    await page.evaluate(() => {
        const copy = document.getElementById('below').cloneNode(true);
        copy.id = 'copy';
        copy.querySelector('.gh-rail__track').scrollLeft = 0;
        document.getElementById('later').appendChild(copy);
    });
    await page.$eval('#copy', (r) => r.scrollIntoView());
    await clickNext(page, '#copy');
    check(await left(page, '#copy') > 1000, 'a rail copied in after load still moves');

    // The end of the row.
    for (let i = 0; i < 4; i++) {
        if (await page.$eval('#below .gh-rail__nav--next', (b) => b.disabled)) break;
        await clickNext(page, '#below');
    }
    check(await page.$eval('#below .gh-rail__nav--next', (b) => b.disabled), 'at the end the next arrow is off');

    check(errors.length === 0, 'no script errors', errors);
    await browser.close();
    console.log(passes + ' passed, ' + failures + ' failed');
    process.exit(failures > 0 ? 1 : 0);
})().catch((e) => {
    console.log('FAIL ' + e.message);
    process.exit(1);
});
