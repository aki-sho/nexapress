const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkDesignFrames(page, base, output) {
    await page.goto(base + '/admin/design');
    const initial = JSON.parse(await page.locator('#nx-design-data').textContent());
    const active = initial.active;
    const endpoint = base + '/admin/design/publish';
    const button = page.locator('[data-action="publish"]');
    const settled = () => page.locator('#nx-design-stage[aria-busy="false"]').waitFor();
    let group;
    const numeric = field => group.locator('[data-frame-number="' + field + '"]');
    const mode = () => group.locator('[data-frame-mode]');
    async function open(pane) {
        await page.locator('[data-pane="' + pane + '"]').click();
        group = page.locator('[data-chrome-details="' + pane + ':spacing"] + [data-chrome-details="' + pane + ':frame"]');
        if (!(await group.evaluate(node => node.open))) await group.locator('summary').press('Enter');
    }
    const expectPreview = (selector, values) => page.waitForFunction(({selector, values}) => {
        const stage = document.querySelector('#nx-design-stage');
        const frame = document.querySelector('#nx-design-frame');
        const target = frame.contentDocument.querySelector(selector);
        if (!target || stage.getAttribute('aria-busy') !== 'false') return false;
        const computed = frame.contentWindow.getComputedStyle(target);
        return Object.entries(values).every(([property, value]) => computed[property] === value);
    }, {selector, values});

    for (const theme of ['default', 'monoedge']) {
        await page.locator('[data-setting="theme"]').selectOption(theme);
        await settled();
        for (const [pane, widths] of [['header', [0, 0, 1, 0]], ['columns', [0, 0, 0, 0]], ['footer', [1, 0, 0, 0]]]) {
            await open(pane);
            assert.equal(await mode().inputValue(), 'theme');
            assert.equal(await numeric('radius').inputValue(), '0');
            assert.deepEqual(await Promise.all(['top', 'right', 'bottom', 'left'].map(side => numeric(side).inputValue())),
                widths.map(String));
            assert(await button.isDisabled(), 'reading actual theme values leaves the design clean');
        }
    }
    await page.locator('[data-setting="theme"]').selectOption(active);
    await settled();

    const guest = await page.context().browser().newContext({viewport: {width: 1440, height: 900}, javaScriptEnabled: false});
    const visitor = await guest.newPage();
    await guest.route(base + '/', async route => {
        const response = await route.fetch();
        await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
    });
    const computed = (selector, key) => visitor.locator(selector).evaluate((node, property) => getComputedStyle(node)[property], key);
    let saved = false;
    try {
        await open('header');
        await numeric('bottom').fill('5');
        assert.equal(await mode().inputValue(), 'custom');
        await group.locator('[data-frame-style]').selectOption('dashed');
        await group.locator('[data-color-field="code"]').fill('rgba(17, 34, 51, 0.5)');
        assert.equal(await group.locator('[data-color-field="opacity"]').inputValue(), '50');
        await group.locator('[data-color-field="opacity"]').fill('75');
        await numeric('radius').fill('9');
        const header = {borderBottomWidth: '5px', borderTopWidth: '0px', borderBottomStyle: 'dashed',
            borderBottomColor: 'rgba(17, 34, 51, 0.75)', borderTopLeftRadius: '9px'};
        await expectPreview('.site-header', header);
        await page.screenshot({path: path.join(output, 'frame-header-desktop.png')});
        await visitor.goto(base + '/');
        assert.equal(await computed('.site-header', 'borderBottomWidth'), '1px', 'draft changes leave the public header intact');

        await open('columns');
        await numeric('all').fill('3');
        assert.deepEqual(await Promise.all(['top', 'right', 'bottom', 'left'].map(side => numeric(side).inputValue())), ['3', '3', '3', '3']);
        await numeric('left').fill('0');
        assert.equal(await numeric('all').inputValue(), '');
        await group.locator('[data-frame-style]').selectOption('dotted');
        await numeric('radius').fill('12');
        await expectPreview('.site-main', {borderRightWidth: '3px', borderLeftWidth: '0px',
            borderRightStyle: 'dotted', borderBottomRightRadius: '12px'});
        await page.locator('[data-device="mobile"]').click();
        await expectPreview('.site-main', {borderTopWidth: '3px', borderTopLeftRadius: '12px'});
        await page.screenshot({path: path.join(output, 'frame-body-mobile.png')});
        await page.locator('[data-device="desktop"]').click();

        await open('footer');
        await mode().selectOption('none');
        await expectPreview('.site-footer', {borderTopWidth: '0px'});
        const [publication] = await Promise.all([page.waitForResponse(endpoint), button.click()]);
        assert.equal(publication.status(), 200, await publication.text());
        saved = true;
        const state = (await publication.json()).states[active];
        assert.deepEqual(state.layout.frames.header, {mode: 'custom', radius: 9,
            line: {style: 'dashed', color: 'rgba(17, 34, 51, 0.75)', widths: {top: 0, right: 0, bottom: 5, left: 0}}});
        assert.equal(state.layout.frames.footer.mode, 'none');
        assert.deepEqual(state.layout.spacing, initial.themes[active].state.layout.spacing);
        await visitor.reload();
        for (const [key, value] of Object.entries(header)) assert.equal(await computed('.site-header', key), value);
        assert.equal(await computed('.site-main', 'borderRightStyle'), 'dotted');
        assert.equal(await computed('.site-footer', 'borderTopWidth'), '0px');

        await page.reload();
        await settled();
        await open('header');
        assert.equal(await numeric('bottom').inputValue(), '5');
        await mode().selectOption('none');
        await expectPreview('.site-header', {borderBottomWidth: '0px'});
        await mode().selectOption('custom');
        await expectPreview('.site-header', header);
        await mode().selectOption('theme');
        await expectPreview('.site-header', {borderBottomWidth: '1px', borderTopLeftRadius: '0px'});
        assert.equal(await numeric('bottom').inputValue(), '1');

        const invalid = structuredClone(state);
        invalid.layout.frames.header.line.widths.bottom = -1;
        const result = await page.request.post(endpoint, {form: {
            _csrf_token: initial.token, design: JSON.stringify(invalid), revision: state.revision,
        }});
        assert.equal(result.status(), 422);
        assert.equal((await page.request.get(base + '/nexapress/assets/site-frame', {
            params: {'header[mode]': 'custom', 'header[radius]': '-1'},
        })).status(), 400);
    } finally {
        await guest.close();
        if (saved) {
            const response = await page.request.get(base + '/admin/design');
            const data = JSON.parse((await response.text()).match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
            const state = data.themes[active].state;
            state.layout.frames = initial.themes[active].state.layout.frames;
            const restored = await page.request.post(endpoint, {form: {
                _csrf_token: data.token, design: JSON.stringify(state), revision: state.revision,
            }});
            assert.equal(restored.status(), 200, await restored.text());
        }
        await page.goto(base + '/admin/design');
    }
    console.log('PASS: initial theme frames, individual and bulk dimensions, RGBA and opacity, corner radius, modes, preview and ordinary published rendering');
};
