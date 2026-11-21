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
    const shadow = (key = 'shadow') => group.locator('[data-frame-shadow="' + key + '"]');
    const shadowMode = (key = 'shadow') => shadow(key).locator('[data-shadow-mode]');
    const shadowNumber = (field, key) => shadow(key).locator('[data-shadow-number="' + field + '"]');
    const card = '.site-main :is(.post-card, .post-detail)';
    const cardShadow = theme => theme === 'monoedge' ? 'rgba(15, 23, 42, 0.08) 0px 12px 30px 0px' : 'rgba(0, 0, 0, 0.04) 0px 4px 12px 0px';
    const detailPages = [
        {url: '/legacy-post', kind: 'post', id: initial.targets.post.find(item => item.label === '既存投稿').id},
        {url: '/page/legacy', kind: 'page', id: initial.targets.page.find(item => item.label === '既存ページ').id},
    ];
    async function detailLayouts() {
        return Promise.all(detailPages.map(async ({kind, id}) => {
            const response = await page.request.get(base + '/admin/design/' + kind + '-layout/' + id);
            assert.equal(response.status(), 200);
            const {revision, overrides} = await response.json();
            return {kind, id, revision, overrides};
        }));
    }
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
            assert.equal(await shadowMode().inputValue(), 'theme');
            if (pane === 'columns') {
                assert.equal(await shadowMode('cardShadow').inputValue(), 'theme');
                await expectPreview(card, {boxShadow: cardShadow(theme)});
            }
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
    const publicUrls = ['/', ...detailPages.map(item => item.url)].map(url => base + url);
    await guest.route(url => publicUrls.includes(url.href), async route => {
        const response = await route.fetch();
        await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
    });
    const computed = (selector, key) => visitor.locator(selector).evaluate((node, property) => getComputedStyle(node)[property], key);
    const archiveShadows = () => visitor.locator('.post-card').evaluateAll(cards => [...new Set(cards.map(card => getComputedStyle(card).boxShadow))]);
    let saved = false;
    let originalDetails;
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
        await shadowMode().selectOption('custom');
        assert.equal(await shadowNumber('y').inputValue(), '4');
        assert.equal(await shadowNumber('blur').inputValue(), '12');
        await shadowNumber('x').fill('-4.5');
        await shadowNumber('y').fill('6');
        await shadowNumber('blur').fill('16');
        await shadowNumber('spread').fill('-1');
        await shadow().locator('[data-color-field="code"]').fill('rgba(17, 34, 51, 0.4)');
        await shadow().locator('[data-color-field="opacity"]').fill('25');
        header.boxShadow = 'rgba(17, 34, 51, 0.25) -4.5px 6px 16px -1px';
        await expectPreview('.site-header', header);
        await shadowMode().selectOption('none');
        await expectPreview('.site-header', {...header, boxShadow: 'none'});
        await shadowMode().selectOption('custom');
        await expectPreview('.site-header', header);
        await page.screenshot({path: path.join(output, 'frame-header-desktop.png')});
        await visitor.goto(base + '/');
        assert.equal(await computed('.site-header', 'borderBottomWidth'), '1px', 'draft changes leave the public header intact');
        assert.equal(await computed('.site-header', 'boxShadow'), 'none');
        assert.deepEqual(await archiveShadows(), [cardShadow(active)]);

        await open('columns');
        await numeric('all').fill('3');
        assert.deepEqual(await Promise.all(['top', 'right', 'bottom', 'left'].map(side => numeric(side).inputValue())), ['3', '3', '3', '3']);
        await numeric('left').fill('0');
        assert.equal(await numeric('all').inputValue(), '');
        await group.locator('[data-frame-style]').selectOption('dotted');
        await numeric('radius').fill('12');
        await expectPreview('.site-main', {borderRightWidth: '3px', borderLeftWidth: '0px',
            borderRightStyle: 'dotted', borderBottomRightRadius: '12px'});
        await shadowMode('cardShadow').selectOption('custom');
        assert.equal(await shadowNumber('blur', 'cardShadow').inputValue(), active === 'monoedge' ? '30' : '12');
        assert.equal(await shadow('cardShadow').locator('[data-color-field="opacity"]').inputValue(), active === 'monoedge' ? '8' : '4');
        await expectPreview(card, {boxShadow: cardShadow(active)});
        await shadowMode('cardShadow').selectOption('none');
        await expectPreview(card, {boxShadow: 'none'});
        await shadowMode().selectOption('custom');
        const bodyShadow = 'rgba(0, 0, 0, 0.12) 0px 4px 12px 0px';
        await page.locator('[data-device="mobile"]').click();
        await expectPreview('.site-main', {borderTopWidth: '3px', borderTopLeftRadius: '12px', boxShadow: bodyShadow});
        await expectPreview(card, {boxShadow: 'none'});
        await page.screenshot({path: path.join(output, 'frame-body-mobile.png')});
        await page.locator('[data-device="desktop"]').click();

        await open('footer');
        await mode().selectOption('none');
        await expectPreview('.site-footer', {borderTopWidth: '0px'});
        await shadowMode().selectOption('custom');
        assert.equal(await shadowNumber('y').inputValue(), '-4');
        const footerShadow = 'rgba(0, 0, 0, 0.12) 0px -4px 12px 0px';
        await expectPreview('.site-footer', {borderTopWidth: '0px', boxShadow: footerShadow});
        const [publication] = await Promise.all([page.waitForResponse(endpoint), button.click()]);
        assert.equal(publication.status(), 200, await publication.text());
        saved = true;
        const state = (await publication.json()).states[active];
        assert.deepEqual(state.layout.frames.header, {mode: 'custom', radius: 9,
            line: {style: 'dashed', color: 'rgba(17, 34, 51, 0.75)', widths: {top: 0, right: 0, bottom: 5, left: 0}},
            shadow: {mode: 'custom', value: {x: -4.5, y: 6, blur: 16, spread: -1, color: 'rgba(17, 34, 51, 0.25)'}}});
        assert.equal(state.layout.frames.body.cardShadow.mode, 'none');
        assert.equal(state.layout.frames.footer.mode, 'none');
        assert.deepEqual(state.layout.spacing, initial.themes[active].state.layout.spacing);
        await visitor.reload();
        for (const [key, value] of Object.entries(header)) assert.equal(await computed('.site-header', key), value);
        assert.equal(await computed('.site-main', 'borderRightStyle'), 'dotted');
        assert.equal(await computed('.site-main', 'boxShadow'), bodyShadow);
        assert.equal(await computed('.site-footer', 'borderTopWidth'), '0px');
        assert.equal(await computed('.site-footer', 'boxShadow'), footerShadow);
        assert.deepEqual(await archiveShadows(), ['none']);
        const details = await detailLayouts();
        const plainDetails = await page.request.post(endpoint, {form: {
            _csrf_token: initial.token, revision: state.revision,
            design: JSON.stringify({...state, content: details.map(item =>
                ({...item, overrides: {...item.overrides, enabled: false}}))}),
        }});
        assert.equal(plainDetails.status(), 200, await plainDetails.text());
        originalDetails = details;
        for (const {url} of detailPages) {
            const response = await visitor.goto(base + url);
            assert.equal(response.status(), 200);
            assert.equal(await computed('.post-detail', 'boxShadow'), 'none');
        }

        await page.reload();
        await settled();
        await open('header');
        assert.equal(await numeric('bottom').inputValue(), '5');
        assert.equal(await shadowNumber('x').inputValue(), '-4.5');
        await mode().selectOption('none');
        await expectPreview('.site-header', {borderBottomWidth: '0px', boxShadow: header.boxShadow});
        await mode().selectOption('custom');
        await expectPreview('.site-header', header);
        await mode().selectOption('theme');
        await expectPreview('.site-header', {borderBottomWidth: '1px', borderTopLeftRadius: '0px', boxShadow: header.boxShadow});
        assert.equal(await numeric('bottom').inputValue(), '1');
        await shadowMode().selectOption('theme');
        await expectPreview('.site-header', {boxShadow: 'none', borderBottomWidth: '1px'});
        await open('columns');
        assert.equal(await shadowMode('cardShadow').inputValue(), 'none');
        await shadowMode('cardShadow').selectOption('theme');
        await expectPreview(card, {boxShadow: cardShadow(active)});
        assert.equal(await computed('.post-detail', 'boxShadow'), 'none', 'the theme shadow is restored in the draft only');

        const invalid = structuredClone(state);
        invalid.layout.frames.header.line.widths.bottom = -1;
        const result = await page.request.post(endpoint, {form: {
            _csrf_token: initial.token, design: JSON.stringify(invalid), revision: state.revision,
        }});
        assert.equal(result.status(), 422);
        invalid.layout.frames.header.line.widths.bottom = 5;
        invalid.layout.frames.header.shadow.value.blur = -1;
        const invalidShadow = await page.request.post(endpoint, {form: {
            _csrf_token: initial.token, design: JSON.stringify(invalid), revision: state.revision,
        }});
        assert.equal(invalidShadow.status(), 422);
        assert.equal((await page.request.get(base + '/nexapress/assets/site-frame', {
            params: {'header[mode]': 'theme', 'header[shadow][mode]': 'custom'},
        })).status(), 400);
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
            if (originalDetails) {
                const currentDetails = await detailLayouts();
                state.content = currentDetails.map((item, index) => ({...item, overrides: originalDetails[index].overrides}));
            }
            const restored = await page.request.post(endpoint, {form: {
                _csrf_token: data.token, design: JSON.stringify(state), revision: state.revision,
            }});
            assert.equal(restored.status(), 200, await restored.text());
        }
        await page.goto(base + '/admin/design');
    }
    console.log('PASS: frame lines, corner radius, independent header/body/footer and card shadows, defaults, live previews, publishing and reloading');
};
