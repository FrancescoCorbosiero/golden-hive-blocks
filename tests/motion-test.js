/**
 * Tests for the scroll motion (js/motion.js) in a real browser.
 *
 * Run from the plugin root:   node tests/motion-test.js
 *
 * Loads tests/fixtures/motion.html (block-shaped markup on the real
 * stylesheets and scripts) in headless Chromium through Playwright, and checks
 * the rules motion.js promises: the first screen never moves, nothing is
 * hidden without JavaScript or under reduced motion, a split heading keeps
 * its height, every move hands the element back to the stylesheet, a jump
 * shows what it skipped, keyboard focus shows a waiting block.
 *
 * Needs Playwright with Chromium (pre-installed in Claude Code cloud sessions;
 * CI installs it). Without it the test says so and exits 0.
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

const url = 'file://' + path.join(__dirname, 'fixtures', 'motion.html');
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

/** What a test needs to know about one element. */
function state(page, id) {
    return page.evaluate((id) => {
        const el = document.getElementById(id);
        return {
            opacity: getComputedStyle(el).opacity,
            pending: el.hasAttribute('data-gh-motion-pending'),
            // Script animations still running (CSS scroll-driven drift excluded).
            running: el.getAnimations({ subtree: true })
                .filter((a) => a.playState !== 'finished' && !(a instanceof CSSAnimation)).length,
            inline: el.getAttribute('style') || '',
            lines: el.querySelectorAll('.gh-line').length,
            height: el.getBoundingClientRect().height,
            text: el.textContent.trim(),
        };
    }, id);
}

const scrollTo = (page, id) =>
    page.evaluate((id) => document.getElementById(id).scrollIntoView({ block: 'center' }), id);

(async () => {
    const browser = await chromium.launch();
    const open = async (options) => {
        const context = await browser.newContext(Object.assign({ viewport: { width: 1280, height: 800 } }, options));
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.goto(url);
        await page.waitForTimeout(200);
        return { context, page, errors };
    };

    // A normal visit, scrolled through.
    {
        const { context, page, errors } = await open();

        const first = await state(page, 'first');
        check(first.opacity === '1' && !first.pending && first.running === 0, 'the first screen is left alone', first);
        check((await state(page, 'firsth')).lines === 0, 'a heading in the first screen is not split');

        const block = await state(page, 'faq1');
        check(block.opacity === '0' && block.pending, 'a block below the fold waits', block);
        check(!block.inline.includes('opacity') && !block.inline.includes('transform'), 'it waits without an inline style', block.inline);

        const plain = await state(page, 'faqh');
        check(plain.pending, 'a heading below the fold waits');
        await scrollTo(page, 'faqh');
        await page.waitForTimeout(120);
        const split = await state(page, 'faqh');
        check(split.lines >= 2, 'the heading plays split into its lines', split.lines);
        check(Math.abs(split.height - plain.height) < 1, 'the split heading keeps its height (no layout shift)', [plain.height, split.height]);
        await page.waitForTimeout(1800);
        const done = await state(page, 'faqh');
        check(done.lines === 0 && done.text.startsWith('Domande frequenti') && done.opacity === '1', 'the heading is plain text again afterwards', done);

        await scrollTo(page, 'faq3');
        await page.waitForTimeout(1500);
        const landed = await state(page, 'faq3');
        check(landed.opacity === '1' && !landed.pending && landed.running === 0, 'a block lands and the stylesheet takes over', landed);

        await scrollTo(page, 'cta');
        await page.waitForTimeout(1600);
        const clip = await page.evaluate(() => {
            const v = document.getElementById('cta');
            return {
                clip: getComputedStyle(v).clipPath,
                after: getComputedStyle(v, '::after').content,
                drift: v.querySelector('img').getAnimations().map((a) => a.animationName || 'script'),
            };
        });
        check(clip.clip === 'none', 'the photo is unclipped after it opens', clip.clip);
        check(clip.after !== 'none', 'the CTA image keeps its gradient', clip.after);
        check(clip.drift.join() === 'gh-drift', 'the photo drifts on a CSS scroll timeline', clip.drift);

        await scrollTo(page, 'rail');
        await page.waitForTimeout(1700);
        const cards = await page.evaluate(() => [...document.querySelectorAll('#rail li')].map((li) => getComputedStyle(li).opacity));
        check(cards.every((o) => o === '1'), 'every rail card lands', cards);

        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(150);
        const skipped = await state(page, 'about');
        check(!skipped.pending && skipped.opacity === '1' && skipped.running === 0, 'a jump shows what it skipped, at once', skipped);
        check(!(await state(page, 'last')).pending, 'the block the jump lands on plays');

        check(errors.length === 0, 'no script errors', errors);
        await context.close();
    }

    // Keyboard: focus inside a waiting block shows it now.
    {
        const { context, page } = await open();
        await page.focus('#faqlink');
        await page.waitForTimeout(50);
        const focused = await state(page, 'faq1');
        check(!focused.pending && focused.opacity === '1', 'keyboard focus shows a waiting block', focused);
        await context.close();
    }

    // Reduced motion: nothing hidden, nothing moving.
    {
        const { context, page } = await open({ reducedMotion: 'reduce' });
        const s = await state(page, 'faq1');
        check(s.opacity === '1' && !s.pending, 'reduced motion: nothing is hidden', s);
        const drift = await page.evaluate(() => document.querySelector('#cta img').getAnimations().length);
        check(drift === 0, 'reduced motion: no drift', drift);
        await context.close();
    }

    // No JavaScript: the page is complete.
    {
        const { context, page } = await open({ javaScriptEnabled: false });
        const opacities = await page.$$eval('[data-gh-reveal], [data-gh-image-reveal], [data-gh-split], #rail li',
            (els) => els.map((e) => getComputedStyle(e).opacity));
        check(opacities.every((o) => o === '1'), 'without JavaScript everything is visible', opacities);
        await context.close();
    }

    // A phone.
    {
        const { context, page } = await open({ viewport: { width: 390, height: 844 } });
        await scrollTo(page, 'faqh');
        await page.waitForTimeout(2000);
        const s = await state(page, 'faqh');
        check(s.lines === 0 && s.opacity === '1', 'phone: the heading plays and is restored', s);
        await context.close();
    }

    await browser.close();
    console.log(passes + ' passed, ' + failures + ' failed');
    process.exit(failures > 0 ? 1 : 0);
})().catch((e) => {
    console.log('FAIL ' + e.message);
    process.exit(1);
});
