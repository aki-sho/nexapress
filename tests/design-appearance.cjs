const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkDesignAppearance(page, base, output) {
    const endpoint = base + '/admin/design/publish';
    const fixtureName = 'site-background-fixture';
    await page.goto(base + '/admin/media');
    const image = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 180;
        const context = canvas.getContext('2d');
        context.fillStyle = '#76a6b7';
        context.fillRect(0, 0, canvas.width, canvas.height);
        return canvas.toDataURL().split(',')[1];
    });
    const upload = page.locator('.media-upload-form');
    await upload.locator('input[type="file"]').setInputFiles({
        name: fixtureName + '.png', mimeType: 'image/png', buffer: Buffer.from(image, 'base64'),
    });
    await upload.getByRole('button', {name: 'アップロード', exact: true}).click();
    const card = page.locator('.media-card').filter({has: page.getByText(fixtureName, {exact: true})});
    const source = await card.locator('img').getAttribute('src');

    await page.goto(base + '/admin/design');
    const data = JSON.parse(await page.locator('#nx-design-data').textContent());
    const original = data.themes[data.active].state;
    assert.equal(original.layout.headerRows, null);
    assert.equal(original.layout.headerWidth, 'theme');
    assert.equal(original.layout.footerWidth, 'theme');
    const fixed = data.targets.page.find(item => item.label === '既存ページ');
    assert(fixed);
    const snapshotUrl = base + '/admin/design/page-layout/' + fixed.id;
    const originalPage = await (await page.request.get(snapshotUrl)).json();
    const open = name => page.locator('[data-pane="' + name + '"]').click();
    const choose = (name, value) => page.locator('[data-setting="' + name + '"]').selectOption(value);
    const slot = area => '[data-widget-area="' + area + '"]';
    const main = '.site-header > .site-header-inner:not(.nx-design-header-band)';
    const preview = page.frameLocator('#nx-design-frame');
    const settled = () => page.locator('#nx-design-stage[aria-busy="false"]').waitFor();
    const publish = async () => {
        const [response] = await Promise.all([page.waitForResponse(endpoint), page.locator('[data-action="publish"]').click()]);
        assert.equal(response.status(), 200, await response.text());
        await page.locator('[data-action="publish"]:disabled').waitFor();
        return response.json();
    };
    const mainGeometry = () => preview.locator(main).evaluate(node => [...node.children].map(child => {
        const box = child.getBoundingClientRect();
        return [box.x, box.width, box.height];
    }));
    const widths = () => preview.locator('body').evaluate(body =>
        ['.site-header', '.site-footer'].map(selector => body.querySelector(selector).getBoundingClientRect().width));
    await settled();
    const baseline = await mainGeometry();
    await page.getByRole('button', {name: '＋ 上に段を追加', exact: true}).click();
    await settled();
    assert.equal(await preview.locator(slot('header_above')).count(), 1);
    assert.equal(await preview.locator(slot('header_bottom1')).count(), 0);
    const widgets = page.locator('[data-chrome-details="header:widgets"]');
    async function addText(place, label, text) {
        assert.equal(await widgets.locator('[data-setting="area"]').inputValue(), place);
        assert.equal(await widgets.locator('[data-widget-row="list"] option:checked').textContent(), label);
        await widgets.locator('[data-action="add"]').click();
        const form = page.locator('[data-designer-form]');
        await form.locator('[name="text"]').fill(text);
        await form.getByRole('button', {name: '追加', exact: true}).click();
        await settled();
        assert.match(await preview.locator(slot(place)).textContent(), new RegExp(text));
    }
    await addText('header_above', '上段', '営業時間のお知らせ');
    await page.getByRole('button', {name: '＋ 下に段を追加', exact: true}).click();
    await addText('header_bottom1', '下段', 'ご来店のご案内');
    assert.deepEqual(await mainGeometry(), baseline, 'adding both rows preserves native header positions and geometry');
    assert.deepEqual(await preview.locator('.site-header > div').evaluateAll(rows =>
        rows.map(row => row.dataset.widgetArea || 'main')), ['header_above', 'main', 'header_bottom1']);
    for (const [value, width] of [['standard', 1080], ['wide', 1440], ['full', 1600]]) {
        await choose('headerWidth', value);
        await settled();
        assert.deepEqual(await widths(), [width, 1600]);
    }
    await choose('headerWidth', 'wide');
    await open('footer');
    await choose('footerWidth', 'standard');
    await settled();
    assert.deepEqual(await widths(), [1440, 1080]);

    await open('general');
    await choose('background-mode', 'color');
    const siteColors = page.locator('[data-design-color="site"]');
    await siteColors.locator('[data-color-field="code"]').fill('rgba(215, 230, 255, .4)');
    await siteColors.locator('[data-color-field="code"]').press('Tab');
    await siteColors.locator('[data-color-field="opacity"]').fill('60');
    assert.equal(await siteColors.locator('[data-color-opacity]').textContent(), '60%');
    const siteCode = siteColors.locator('[data-color-field="code"]');
    await siteCode.fill('');
    await siteCode.pressSequentially('#D7E6FF');
    await siteCode.press('Tab');
    assert.equal(await siteCode.inputValue(), 'rgba(215, 230, 255, 0.6)');
    await settled();
    const color = frame => frame.locator('body').evaluate(body => getComputedStyle(body).backgroundImage);
    assert.match(await color(preview), /rgba\(215, 230, 255, 0\.6\)/);
    await page.locator('[data-mode="published"]').click();
    await settled();
    assert.equal(await color(preview), 'none');
    await page.locator('[data-mode="draft"]').click();

    await open('page_layout');
    await page.locator('[data-layout-scope="page_item"] > summary').click();
    await page.locator('[data-page-field="page"]').selectOption(String(fixed.id));
    const enable = page.locator('[data-page-field="enabled"]');
    if (!(await enable.isChecked())) await enable.check();
    await page.locator('[data-page-field="background"]').selectOption('full');
    const details = page.locator('[data-page-details="colors"]');
    if (!(await details.evaluate(element => element.open))) await details.locator('summary').click();
    const sectionCode = details.locator('[data-color-field="code"]');
    await sectionCode.fill('rgba(24, 36, 58, .45)');
    await sectionCode.press('Tab');
    await settled();
    const sectionColor = frame => frame.locator('[data-layout-section="0"]').evaluate(node =>
        getComputedStyle(node, '::before').backgroundColor);
    assert.equal(await sectionColor(preview), 'rgba(24, 36, 58, 0.45)');
    await page.screenshot({path: path.join(output, 'appearance-rgba-editor.png')});
    const saved = await publish();
    assert.equal(saved.states[data.active].layout.headerRows, null);
    assert.equal(saved.states[data.active].background.color, 'rgba(215, 230, 255, 0.6)');

    const guest = await page.context().browser().newContext({viewport: {width: 1600, height: 960}, javaScriptEnabled: false});
    try {
        const visitor = await guest.newPage();
        await guest.route(base + '/page/legacy', async route => {
            const response = await route.fetch();
            await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
        });
        await visitor.goto(base + '/page/legacy');
        assert.equal(await sectionColor(visitor), 'rgba(24, 36, 58, 0.45)');
        assert.match(await color(visitor), /rgba\(215, 230, 255, 0\.6\)/);
        assert.match(await visitor.locator(slot('header_bottom1')).textContent(), /ご来店のご案内/);
        await page.reload();
        await open('general');
        assert.equal(await page.locator('[data-setting="background-mode"]').inputValue(), 'color');
        await choose('background-mode', 'image');
        await page.locator('[data-setting="background-image"]').selectOption({label: fixtureName});
        await settled();
        assert((await color(preview)).includes(source));
        const [uploadResponse] = await Promise.all([
            page.waitForResponse(base + '/admin/media/upload-image'),
            page.locator('[data-setting="background-file"]').setInputFiles({
                name: 'direct-design-background.png', mimeType: 'image/png', buffer: Buffer.from(image, 'base64'),
            }),
        ]);
        assert.equal(uploadResponse.status(), 201, await uploadResponse.text());
        const uploaded = (await uploadResponse.json()).image;
        await page.waitForFunction(id => document.querySelector('[data-setting="background-image"]').value === String(id), uploaded.id);
        await settled();
        assert((await color(preview)).includes(uploaded.url));
        await publish();
        const download = new URL(uploaded.url, base);
        const [imageRequest] = await Promise.all([
            visitor.waitForResponse(response => response.url() === download.href && response.ok()),
            visitor.reload(),
        ]);
        assert.match(imageRequest.headers()['content-type'], /^image\/png/);
        assert((await color(visitor)).includes(uploaded.url));
        await visitor.setViewportSize({width: 390, height: 844});
        assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await visitor.screenshot({path: path.join(output, 'appearance-mobile-background.png'), fullPage: true});
        await open('header');
        await page.getByRole('button', {name: '上段を削除'}).click();
        await preview.locator(slot('header_above')).waitFor({state: 'detached'});
        await settled();
        assert.equal(await preview.locator(slot('header_bottom1')).count(), 1);
        await page.getByRole('button', {name: '下段を削除'}).click();
        await publish();
        await visitor.reload();
        assert.equal(await visitor.locator(slot('header_above') + ', ' + slot('header_bottom1')).count(), 0);
        await page.getByRole('button', {name: '＋ 上に段を追加', exact: true}).click();
        await settled();
        assert.match(await preview.locator(slot('header_above')).textContent(), /営業時間のお知らせ/);
        await page.getByRole('button', {name: '上段を削除'}).click();
    } finally {
        await guest.close();
    }

    const catalog = await (await page.request.get(base + '/admin/design')).text();
    const current = JSON.parse(catalog.match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
    const pageLayout = await (await page.request.get(snapshotUrl)).json();
    const restore = await page.request.post(endpoint, {form: {
        _csrf_token: current.token, revision: current.themes[data.active].state.revision,
        design: JSON.stringify({...original, content: [{
            kind: 'page', id: fixed.id, revision: pageLayout.revision, overrides: originalPage.overrides,
        }]}),
    }});
    assert.equal(restore.status(), 200, await restore.text());
    await page.reload();
    console.log('PASS: independent header rows without shifting the original header, matched widths, site image and RGBA, public CSP, responsive and persistent settings');
};
