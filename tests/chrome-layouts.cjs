const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkChromeLayouts(page, base, output) {
    const control = (kind, row, field) => page.locator(
        '[data-chrome="' + kind + '"][data-chrome-row="' + row + '"][data-chrome-field="' + field + '"]');
    async function open(kind) { await page.locator('[data-pane="' + kind + '"]').click(); }
    async function content(kind) {
        const details = page.locator('[data-chrome-details="' + kind + ':content"]');
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').click();
    }
    async function set(kind, row, field, value) {
        await control(kind, row, field).selectOption(String(value));
    }
    async function publish() {
        const [response] = await Promise.all([
            page.waitForResponse(base + '/admin/design/publish'),
            page.getByRole('button', {name: '公開', exact: true}).click(),
        ]);
        assert.equal(response.status(), 200, await response.text());
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
    }
    async function addWidget(kind, area, text) {
        await open(kind);
        await content(kind);
        await page.locator('[data-action="area"][data-area="' + area + '"]').click();
        assert.equal(await page.locator('[data-setting="area"]').inputValue(), area);
        await page.locator('[data-action="add"]').click();
        const form = page.locator('[data-designer-form]');
        await form.locator('[data-form-setting="type"]').selectOption('text');
        await form.locator('[name="title"]').fill(text);
        await form.locator('[name="text"]').fill(text + 'の内容');
        await form.getByRole('button', {name: '追加', exact: true}).click();
    }
    async function waitRows(counts) {
        await page.waitForFunction(counts => {
            const doc = document.querySelector('#nx-design-frame').contentDocument;
            return ['header', 'footer'].every((kind, index) => ['top', 'bottom'].every((row, offset) => {
                const element = doc.querySelector('[data-chrome="' + kind + '"][data-chrome-row="' + row + '"]');
                return element && Number(element.dataset.columns) === counts[index * 2 + offset] &&
                    element.children.length === counts[index * 2 + offset];
            })) && doc.body.textContent.includes('フッター下段5の内容');
        }, counts);
    }
    await page.goto(base + '/admin/design');
    await page.locator('[data-pane="menus"]').click();
    await page.locator('[data-action="add"]').click();
    const menuForm = page.locator('[data-designer-form]');
    await menuForm.locator('[data-form-setting="type"]').selectOption('custom');
    await menuForm.locator('[name="label"]').fill('ヘッダー配置確認');
    await menuForm.locator('[name="url"]').fill('/');
    await menuForm.getByRole('button', {name: '追加', exact: true}).click();
    await open('header');
    for (const section of ['structure', 'top', 'content']) {
        assert.equal(await page.locator('[data-chrome-details="header:' + section + '"]').count(), 1);
    }
    await set('header', '', 'split', 2);
    await set('header', 'top', 'columns', 5);
    await set('header', 'bottom', 'columns', 2);
    await set('header', 'top', 'align', 'flex-start');
    await set('header', 'bottom', 'align', 'center');
    await content('header');
    await page.locator('[data-chrome-part="brand"]').selectOption('top:1');
    await page.locator('[data-chrome-part="menu"]').selectOption('bottom:1');
    await page.locator('[data-chrome-part="search"]').selectOption('top:5');
    assert.equal(await page.locator('[data-chrome-part="search"]').evaluate(el => el === document.activeElement), true);
    await addWidget('header', 'header_bottom2', 'ヘッダー下段2');
    await open('footer');
    await set('footer', '', 'split', 2);
    await set('footer', 'top', 'columns', 4);
    await set('footer', 'bottom', 'columns', 5);
    await set('footer', 'top', 'align', 'flex-end');
    await set('footer', 'bottom', 'align', 'space-between');
    assert.equal(await control('footer', 'bottom', 'columns').locator('option').count(), 5);
    assert.equal(await control('footer', 'bottom', 'align').locator('option').count(), 4);
    await addWidget('footer', 'footer4', 'フッター上段4');
    await addWidget('footer', 'footer_bottom5', 'フッター下段5');
    await waitRows([5, 2, 4, 5]);
    await open('footer');
    await page.screenshot({path: path.join(output, 'footer-upper-lower.png')});
    await open('header');
    await page.screenshot({path: path.join(output, 'header-upper-lower.png')});
    await publish();
    await page.reload();
    await open('header');
    assert.equal(await control('header', 'top', 'columns').inputValue(), '5');
    assert.equal(await control('header', 'bottom', 'columns').inputValue(), '2');
    await open('footer');
    assert.equal(await control('footer', 'top', 'columns').inputValue(), '4');
    assert.equal(await control('footer', 'bottom', 'columns').inputValue(), '5');
    const data = JSON.parse(await page.locator('#nx-design-data').textContent());
    assert.deepEqual(data.themes.default.state.layout.headerRows.positions.search, {row: 'top', column: 5});
    const guest = await page.context().browser().newContext({viewport: {width: 1440, height: 1000}, javaScriptEnabled: false});
    const visitor = await guest.newPage();
    await guest.route(base + '/', async route => {
        const response = await route.fetch();
        await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
    });
    await visitor.goto(base + '/');
    const expected = [
        ['header', 'top', 5, 'flex-start'], ['header', 'bottom', 2, 'center'],
        ['footer', 'top', 4, 'flex-end'], ['footer', 'bottom', 5, 'space-between'],
    ];
    for (const [kind, row, count, align] of expected) {
        const element = visitor.locator('[data-chrome="' + kind + '"][data-chrome-row="' + row + '"]');
        assert.equal(await element.locator(':scope > .nx-chrome-column').count(), count);
        const geometry = await element.evaluate(element => {
            const style = getComputedStyle(element);
            const available = element.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const columns = [...element.children];
            return {
                align: style.justifyContent,
                innerAlign: getComputedStyle(columns[0]).justifyContent,
                widths: columns.map(column => column.getBoundingClientRect().width),
                expected: (available - (columns.length - 1) * 16) / columns.length,
            };
        });
        const normalizedAlign = value => value.replace('flow-start', 'flex-start').replace('flow-end', 'flex-end');
        assert.equal(normalizedAlign(geometry.align), align);
        assert.equal(normalizedAlign(geometry.innerAlign), align);
        assert(geometry.widths.every(width => Math.abs(width - geometry.expected) < 1), JSON.stringify(geometry));
    }
    assert.equal(await visitor.locator('[data-widget-area="header5"] .public-search').count(), 1);
    assert.equal(await visitor.locator('[data-widget-area="header_bottom1"] .site-nav').count(), 1);
    assert((await visitor.locator('[data-widget-area="header_bottom2"]').textContent()).includes('ヘッダー下段2'));
    assert((await visitor.locator('[data-widget-area="footer_bottom5"]').textContent()).includes('フッター下段5'));
    await visitor.screenshot({path: path.join(output, 'chrome-public-desktop.png'), fullPage: true});
    await visitor.setViewportSize({width: 390, height: 844});
    assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    for (const [kind, row] of expected) {
        const columns = visitor.locator('[data-chrome="' + kind + '"][data-chrome-row="' + row + '"] > .nx-chrome-column');
        const positions = await columns.evaluateAll(columns => columns.map(column => column.getBoundingClientRect().x));
        assert(positions.every(x => Math.abs(x - positions[0]) < 1));
    }
    await visitor.screenshot({path: path.join(output, 'chrome-public-mobile.png'), fullPage: true});
    await guest.close();
    for (const mutate of [
        state => { state.layout.headerRows.top.columns = 6; },
        state => { state.layout.footer = 6; },
        state => { state.layout.footerRows.bottom.align = 'url(https://example.test/)'; },
        state => { state.layout.headerRows.positions.menu.row = 'other'; },
    ]) {
        const state = structuredClone(data.themes.default.state);
        mutate(state);
        const response = await page.request.post(base + '/admin/design/publish', {
            form: {_csrf_token: data.token, design: JSON.stringify(state), revision: state.revision},
        });
        assert.equal(response.status(), 422);
    }
    await open('footer');
    await set('footer', '', 'split', 1);
    await publish();
    await page.reload();
    await open('footer');
    assert.equal(await control('footer', '', 'split').inputValue(), '1');
    await set('footer', '', 'split', 2);
    await waitRows([5, 2, 4, 5]);
    await publish();
    await page.locator('[data-setting="theme"]').selectOption('monoedge');
    await open('header');
    await set('header', '', 'split', 2);
    await set('header', 'top', 'columns', 5);
    await set('header', 'bottom', 'columns', 1);
    await open('footer');
    await set('footer', '', 'split', 2);
    await set('footer', 'top', 'columns', 2);
    await set('footer', 'bottom', 'columns', 5);
    await page.waitForFunction(() => {
        const doc = document.querySelector('#nx-design-frame').contentDocument;
        return doc.querySelector('.site-header [data-columns="5"]') &&
            doc.querySelector('.site-footer [data-chrome-row="bottom"][data-columns="5"]');
    });
    await page.screenshot({path: path.join(output, 'monoedge-chrome-rows.png')});
    await publish();
    await page.locator('[data-setting="theme"]').selectOption('default');
    await open('footer');
    assert.equal(await control('footer', 'top', 'columns').inputValue(), '4');
    assert.equal(await control('footer', 'bottom', 'columns').inputValue(), '5');
    console.log('PASS: upper/lower header/footer columns (1–5), four Flex alignments, content slots, strict CSP, responsive layout, validation, retained rows and independent themes');
};
