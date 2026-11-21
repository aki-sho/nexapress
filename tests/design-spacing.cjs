const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkSpacingValues(page, base, output) {
    await page.goto(base + '/admin/design');
    const data = JSON.parse(await page.locator('#nx-design-data').textContent());
    const settled = () => page.waitForFunction(() => document.querySelector('#nx-design-stage').getAttribute('aria-busy') === 'false'
        && document.querySelector('#nx-design-frame').contentDocument.querySelector('.site-footer'));
    let spacing;
    const input = field => spacing.locator('[data-spacing-field="' + field + '"]');
    const origin = field => input(field).locator('..').locator('[data-spacing-origin]');
    const open = async pane => {
        await page.locator('[data-pane="' + pane + '"]').click();
        spacing = page.locator('[data-chrome-details="' + pane + ':spacing"]');
        if (!(await spacing.evaluate(element => element.open))) await spacing.locator('summary').press('Enter');
    };
    const publish = async () => {
        const [response] = await Promise.all([page.waitForResponse(base + '/admin/design/publish'),
            page.locator('[data-action="publish"]').click()]);
        assert.equal(response.status(), 200, await response.text());
        const saved = await response.json();
        await page.locator('[data-action="publish"]:disabled').waitFor();
        await settled();
        return saved;
    };
    await settled();
    for (const [theme, desktop, mobile] of [['default', [16, 24], [16, 16]], ['monoedge', [34, 24], [28, 18]]]) {
        const original = data.themes[theme].state.layout;
        assert(Object.values(original.spacing).every(area => Object.values(area).every(value => value === null)));
        await page.locator('[data-setting="theme"]').selectOption(theme);
        await settled();
        for (const pane of ['header', 'columns', 'footer']) {
            await open(pane);
            const fields = await spacing.locator('[data-spacing-area]').evaluateAll(inputs =>
                inputs.map(input => ({value: input.value, valid: input.validity.valid})));
            assert.equal(fields.length, 8);
            assert(fields.every(field => field.value !== '' && Number.isFinite(Number(field.value)) && field.valid));
            assert(await page.locator('[data-action="publish"]').isDisabled(), 'reading defaults leaves the design unchanged');
        }
        await open('header');
        assert.equal(await input('paddingTop').inputValue(), String(desktop[0]));
        assert.equal(await input('paddingLeft').inputValue(), String(desktop[1]));
        assert.equal(await origin('paddingLeft').textContent(), 'テーマ');
        await page.screenshot({path: path.join(output, 'spacing-defaults-' + theme + '.png')});
        await page.locator('[data-device="mobile"]').click();
        await page.waitForFunction(([top, left]) => document.querySelector('[data-spacing-field="paddingTop"]').value === String(top)
            && document.querySelector('[data-spacing-field="paddingLeft"]').value === String(left), mobile);
        assert(await page.locator('[data-action="publish"]').isDisabled());
        await page.locator('[data-device="desktop"]').click();
        await page.waitForFunction(left => document.querySelector('[data-spacing-field="paddingLeft"]').value === String(left), desktop[1]);

        await open('columns');
        const fractionalMargin = await page.frameLocator('#nx-design-frame').locator('.site-main').evaluate(element => {
            element.style.maxWidth = (element.parentElement.clientWidth % 2 === 0 ? 1081 : 1080) + 'px';
            element.style.marginLeft = 'auto';
            element.style.marginRight = 'auto';
            return parseFloat(getComputedStyle(element).marginLeft);
        });
        assert.equal(Number.isInteger(fractionalMargin), false);
        await open('header');
        await open('columns');
        assert.equal(await input('marginLeft').inputValue(), String(fractionalMargin));
        assert(await input('marginLeft').evaluate(element => element.validity.valid), 'fractional computed values remain valid');

        await open('header');
        await input('paddingLeft').fill('1.5');
        assert.equal(await input('paddingLeft').evaluate(element => element.validity.valid), false, 'manual values still require integers');
        await input('paddingLeft').fill('0');
        await settled();
        assert.equal(await origin('paddingLeft').textContent(), '指定');
        assert.equal(await page.frameLocator('#nx-design-frame').locator('.site-header').evaluate(element => getComputedStyle(element).paddingLeft), '0px');
        await input('paddingLeft').fill('');
        await input('paddingLeft').press('Tab');
        await settled();
        await page.waitForFunction(left => document.querySelector('[data-spacing-field="paddingLeft"]').value === String(left), desktop[1]);
        assert.equal(await origin('paddingLeft').textContent(), 'テーマ');
        assert(await page.locator('[data-action="publish"]').isDisabled(), 'clearing restores the original automatic state');

        await page.locator('[data-setting="headerWidth"]').selectOption(original.headerWidth === 'full' ? 'wide' : 'full');
        await settled();
        const saved = await publish();
        assert.deepEqual(saved.states[theme].layout.spacing, original.spacing, 'publishing another setting preserves automatic defaults');
        await page.locator('[data-setting="headerWidth"]').selectOption(original.headerWidth);
        await settled();
        await publish();
        console.log('PASS: ' + theme + ' current default values, responsive updates, fractional values, manual editing and unchanged automatic settings');
    }
    await page.reload();
    await settled();
};
