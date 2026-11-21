const assert = require('assert/strict');
const path = require('path');
const selectWidgetLocation = require('./widget-location.cjs');

module.exports = async function checkWidgetHighlight(page, base, output) {
    await page.goto(base + '/admin/design');
    const highlight = page.locator('#nx-design-widget-highlight');
    let toggle = page.locator('[data-chrome-details="header:widgets"] [data-action="toggle-widget-highlight"]');
    const note = page.locator('#nx-design-widget-note');
    const frame = page.locator('#nx-design-frame');
    const publish = page.locator('[data-action="publish"]');
    const group = (pane, type = 'widgets') => page.locator('[data-chrome-details="' + pane + ':' + type + '"]');
    const open = async (pane, type = 'widgets') => {
        const details = group(pane, type);
        if (!(await details.evaluate(element => element.open))) await details.locator('summary').press('Enter');
        return details;
    };
    const aligned = async (area, inViewport = true) => {
        const attribute = ['header_after', 'footer_before'].includes(area) ? 'data-design-area' : 'data-widget-area';
        await page.waitForFunction(({area, attribute, inViewport}) => {
            const stage = document.querySelector('#nx-design-stage');
            const iframe = document.querySelector('#nx-design-frame');
            const highlight = document.querySelector('#nx-design-widget-highlight');
            const target = iframe.contentDocument.querySelector('[' + attribute + '="' + area + '"]');
            if (stage.getAttribute('aria-busy') === 'true' || highlight.hidden || !target) return false;
            const frameBox = iframe.getBoundingClientRect();
            const scale = frameBox.width / iframe.clientWidth;
            const expected = target.getBoundingClientRect();
            const actual = highlight.getBoundingClientRect();
            return (!inViewport || (expected.top < iframe.clientHeight && expected.bottom > 0)) &&
                Math.abs(actual.left - (frameBox.left + expected.left * scale)) < 1 &&
                Math.abs(actual.top - (frameBox.top + expected.top * scale)) < 1 &&
                Math.abs(actual.width - expected.width * scale) < 1 &&
                Math.abs(actual.height - expected.height * scale) < 1;
        }, {area, attribute, inViewport});
    };
    assert.equal(await group('header').locator('summary > [data-action="toggle-widget-highlight"]').count(), 1);
    assert(await toggle.isVisible(), 'the toggle is available with the widget group closed');
    assert.equal(await toggle.textContent(), '表示');
    const header = group('header');
    await header.locator('summary').click({position: {x: 8, y: 12}});
    await aligned('header1');
    assert.equal(await toggle.textContent(), '非表示');
    await open('header_after');
    await aligned('header_after');
    assert.equal(await toggle.textContent(), '表示');
    assert.equal(await highlight.evaluate(element => getComputedStyle(element).pointerEvents), 'none');
    assert(await publish.isDisabled(), 'checking locations does not change the design');
    await toggle.click();
    await aligned('header1');
    await toggle.click();
    await highlight.waitFor({state: 'hidden'});
    assert.equal(await toggle.textContent(), '表示');
    assert(await publish.isDisabled());

    await page.locator('[data-pane="footer"]').click();
    await open('footer', 'structure');
    await page.locator('[data-action="footer-row"][data-row="above"]').click();
    const columns = await open('footer', 'above');
    await columns.locator('[data-chrome-field="columns"]').selectOption('2');
    const footer = await open('footer');
    toggle = footer.locator('[data-action="toggle-widget-highlight"]');
    await selectWidgetLocation(footer, 'footer_above2');
    await aligned('footer_above2');
    assert.equal(await frame.evaluate(iframe =>
        iframe.contentDocument.querySelectorAll('[data-widget-area="footer_above2"] .nx-design-empty').length), 1);
    await page.screenshot({path: path.join(output, 'widget-placement-desktop.png')});

    await footer.locator('[data-action="add"]').click();
    const form = footer.locator('[data-designer-form]');
    await form.locator('[name="title"]').fill('確認用の見出し');
    assert.equal(await form.locator('[data-widget-row="form"] option[value="footer_before"]').count(), 0);
    await selectWidgetLocation(form, 'footer1', true);
    await aligned('footer1');
    await selectWidgetLocation(form, 'footer_above1', true);
    await aligned('footer_above1');
    assert.equal(await form.locator('[name="title"]').inputValue(), '確認用の見出し');
    await form.locator('[data-action="cancel"]').click();
    await aligned('footer_above2');

    await page.locator('[data-device="mobile"]').click();
    await aligned('footer_above2');
    const beforeScroll = await frame.evaluate(iframe => iframe.contentDocument.scrollingElement.scrollTop);
    const bounds = await frame.boundingBox();
    await page.mouse.move(bounds.x + Math.min(100, bounds.width / 2), bounds.y + 150);
    await page.mouse.wheel(0, -140);
    await page.waitForFunction(before => document.querySelector('#nx-design-frame').contentDocument.scrollingElement.scrollTop < before, beforeScroll);
    await aligned('footer_above2', false);
    await toggle.click();
    await highlight.waitFor({state: 'hidden'});
    await toggle.click();
    await aligned('footer_above2');
    await page.screenshot({path: path.join(output, 'widget-placement-mobile.png')});

    await page.locator('[data-mode="published"]').click();
    await note.waitFor({state: 'visible'});
    assert.match(await note.textContent(), /「編集中」で確認/);
    assert(await highlight.isHidden(), 'a draft-only row is not highlighted in the published preview');
    await open('footer_before');
    await aligned('footer_before');
    assert(await note.isHidden());
    await selectWidgetLocation(footer, 'footer_above2');
    await note.waitFor({state: 'visible'});
    await page.locator('[data-mode="draft"]').click();
    await aligned('footer_above2');
    await footer.locator('summary').press('Enter');
    assert.equal(await footer.evaluate(element => element.open), false);
    assert(await toggle.isVisible());
    assert.equal(await toggle.getAttribute('aria-label'), 'ウィジェットの配置範囲を非表示');
    await toggle.press('Enter');
    await highlight.waitFor({state: 'hidden'});
    assert.equal(await toggle.textContent(), '表示');
    await toggle.press('Space');
    await aligned('footer_above2');
    assert.equal(await footer.evaluate(element => element.open), false);

    await page.locator('[data-pane="columns"]').click();
    await page.locator('[data-setting="columns"]').selectOption('left');
    await open('columns');
    await aligned('sidebar');
    await page.locator('[data-pane="general"]').click();
    await highlight.waitFor({state: 'hidden'});
    assert(await toggle.isHidden());
    const response = await page.request.get(base + '/');
    assert(!(await response.text()).includes('nx-design-widget-highlight'));
    await page.goto(base + '/admin/design');
    assert(await publish.isDisabled());
    console.log('PASS: highlight is aligned to selected rows, columns, outside regions and sidebar, with a toggle and preview-only state');
};
