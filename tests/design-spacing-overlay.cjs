const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkSpacingOverlay(page, base, output) {
    await page.goto(base + '/admin/design');
    const original = JSON.parse(await page.locator('#nx-design-data').textContent());
    const overlay = page.locator('#nx-design-spacing-overlay');
    const toggle = page.locator('[data-action="toggle-spacing"]');
    const settled = () => page.waitForFunction(() => document.querySelector('#nx-design-stage').getAttribute('aria-busy') === 'false'
        && document.querySelector('#nx-design-frame').contentDocument.querySelector('.site-footer'));
    const open = async pane => {
        await page.locator('[data-pane="' + pane + '"]').click();
        const details = page.locator('[data-chrome-details="' + pane + ':spacing"]');
        if (!(await details.evaluate(element => element.open))) await details.locator('summary').click();
        assert.equal(await details.locator('.nx-chrome-fields > :first-child [data-action="toggle-spacing"]').count(), 1);
    };
    const aligned = async area => {
        await page.waitForFunction(area => {
            const frame = document.querySelector('#nx-design-frame');
            const overlay = document.querySelector('#nx-design-spacing-overlay');
            const element = frame.contentDocument.querySelector(area === 'body' ? '.site-main' : '.site-' + area);
            const outline = overlay.querySelector('.nx-spacing-outline');
            return !overlay.hidden && overlay.dataset.spacingArea === area && outline
                && Math.abs(parseFloat(outline.style.top) - element.getBoundingClientRect().top) < .1
                && Math.abs(parseFloat(outline.style.width) - element.getBoundingClientRect().width) < .1;
        }, area);
        const differences = await page.evaluate(area => {
            const frame = document.querySelector('#nx-design-frame');
            const frameBox = frame.getBoundingClientRect();
            const scale = frameBox.width / frame.clientWidth;
            const element = frame.contentDocument.querySelector(area === 'body' ? '.site-main' : '.site-' + area);
            const box = element.getBoundingClientRect();
            const outline = document.querySelector('#nx-design-spacing-overlay .nx-spacing-outline').getBoundingClientRect();
            const pad = parseFloat(frame.contentWindow.getComputedStyle(element).paddingLeft) > 0 ? element
                : element.querySelector(':scope > .site-' + area + '-inner');
            const band = document.querySelector('#nx-design-spacing-overlay [data-spacing-type="padding"][data-spacing-side="left"]');
            return [outline.left - (frameBox.left + box.left * scale), outline.top - (frameBox.top + box.top * scale),
                outline.width - box.width * scale,
                band.getBoundingClientRect().width - parseFloat(frame.contentWindow.getComputedStyle(pad).paddingLeft) * scale];
        }, area);
        assert(differences.every(difference => Math.abs(difference) < 1), JSON.stringify(differences));
    };
    await settled();
    assert(await overlay.isHidden());
    for (const theme of ['default', 'monoedge']) {
        await page.locator('[data-setting="theme"]').selectOption(theme);
        await settled();
        for (const [pane, area] of [['header', 'header'], ['columns', 'body'], ['footer', 'footer']]) {
            await open(pane);
            assert(await overlay.isHidden());
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
            const before = await page.locator('[data-action="publish"]').isDisabled();
            await toggle.click();
            await aligned(area);
            assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
            assert.equal(await page.locator('[data-action="publish"]').isDisabled(), before);
            assert.equal(await overlay.evaluate(element => getComputedStyle(element).pointerEvents), 'none');
            if (area !== 'header') {
                assert(await overlay.locator('[data-spacing-type="margin"]').count() > 0);
            }
            assert(await overlay.locator('[data-spacing-type="padding"]').count() > 0);
            const frame = page.locator('#nx-design-frame');
            const bounds = await frame.boundingBox();
            const scrollBefore = await frame.evaluate(element => element.contentDocument.scrollingElement.scrollTop);
            await page.mouse.move(bounds.x + Math.min(bounds.width / 2, 100), bounds.y + 180);
            await page.mouse.wheel(0, scrollBefore > 100 ? -100 : 100);
            await page.waitForFunction(before => document.querySelector('#nx-design-frame').contentDocument.scrollingElement.scrollTop !== before, scrollBefore);
            await aligned(area);
            await page.locator('[data-device="mobile"]').click();
            await aligned(area);
            await page.screenshot({path: path.join(output, 'spacing-overlay-' + theme + '-' + area + '-mobile.png')});
            await page.locator('[data-device="desktop"]').click();
            await aligned(area);
            await page.screenshot({path: path.join(output, 'spacing-overlay-' + theme + '-' + area + '.png')});
            await toggle.click();
            await page.waitForFunction(() => document.querySelector('#nx-design-spacing-overlay').hidden);
            assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
        }
        await open('header');
        await toggle.click();
        await aligned('header');
        await page.locator('[data-spacing-field="paddingTop"]').fill('48');
        await settled();
        await aligned('header');
        assert.equal(await overlay.locator('[data-spacing-type="padding"][data-spacing-side="top"]').first().evaluate(element => parseFloat(element.style.height)), 48);
        await page.locator('[data-spacing-field="paddingTop"]').fill('');
        await page.locator('[data-spacing-field="paddingTop"]').press('Tab');
        await settled();
        await aligned('header');
        assert(await page.locator('[data-action="publish"]').isDisabled());
        await page.locator('[data-mode="published"]').click();
        await settled();
        await aligned('header');
        await page.locator('[data-mode="draft"]').click();
        await settled();
        await aligned('header');
        await open('footer');
        assert(await overlay.isHidden(), 'switching regions clears the previous region highlight');
        assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
        console.log('PASS: ' + theme + ' region-only box model, on/off, scroll, mobile scaling, live edits and published preview');
    }
    const current = JSON.parse((await (await page.request.get(base + '/admin/design')).text()).match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
    assert.deepEqual(current.themes, original.themes, 'overlay controls preserve stored design settings');
    await page.reload();
    await settled();
    assert(await overlay.isHidden());
    const guest = await page.context().browser().newContext({javaScriptEnabled: false});
    try {
        const response = await guest.request.get(base + '/page/legacy');
        assert(!((await response.text()).includes('nx-design-spacing-overlay')));
    } finally {
        await guest.close();
    }
    console.log('PASS: highlighting is limited to the admin preview and leaves layout/settings intact');
};
