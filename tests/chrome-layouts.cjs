const assert = require('assert/strict');
const path = require('path');
const selectWidgetLocation = require('./widget-location.cjs');

module.exports = async function checkChromeLayouts(page, base, output) {
    const control = (kind, row, field) => page.locator(
        '[data-chrome="' + kind + '"][data-chrome-row="' + row + '"][data-chrome-field="' + field + '"]');
    const group = (kind, key) => page.locator('[data-chrome-details="' + kind + ':' + key + '"]');
    const frame = page.frameLocator('#nx-design-frame');
    const region = (surface, area) => surface.locator('[data-design-area="' + area + '"]');
    async function open(kind) { await page.locator('[data-pane="' + kind + '"]').click(); }
    async function openGroup(kind, key) {
        const details = group(kind, key);
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').press('Enter');
        return details;
    }
    async function links(kind) {
        await open(kind);
        return openGroup(kind, 'links');
    }
    async function set(kind, row, field, value) {
        if (row) await openGroup(kind, row);
        await control(kind, row, field).selectOption(String(value));
    }
    async function publish() {
        const [response] = await Promise.all([
            page.waitForResponse(base + '/admin/design/publish'),
            page.getByRole('button', {name: '公開', exact: true}).click(),
        ]);
        assert.equal(response.status(), 200, await response.text());
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
        return response.json();
    }
    async function addWidget(kind, area, text, destination = area) {
        await open(kind);
        const widgets = await openGroup(kind, 'widgets');
        await selectWidgetLocation(widgets, area);
        await widgets.locator('[data-action="add"]').click();
        const form = page.locator('[data-designer-form]');
        await form.locator('[data-form-setting="type"]').selectOption('text');
        await form.locator('[name="title"]').fill(text);
        await form.locator('[name="text"]').fill(text + 'の内容');
        if (destination !== area) await selectWidgetLocation(form, destination, true);
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
    const initial = JSON.parse(await page.locator('#nx-design-data').textContent());
    assert.deepEqual(await page.locator('.nx-design-tabs > button').allTextContents(),
        ['ヘッダー', '本文カラム', 'フッター', 'ページ毎のレイアウト']);
    const headerWidgets = await openGroup('header', 'widgets');
    await selectWidgetLocation(headerWidgets, 'header1');
    assert.equal(await page.locator('[data-action="publish"]').isDisabled(), true, 'selecting a header slot keeps the layout clean');
    await links('header');
    assert.equal(await page.locator('[data-action="publish"]').isDisabled(), true, 'opening link settings does not change the layout');
    await group('header', 'links').locator('[data-action="add"]').click();
    const menuForm = page.locator('[data-designer-form]');
    const fixed = initial.targets.page.find(item => item.label === '既存ページ');
    assert(fixed);
    await menuForm.locator('[name="reference_id"]').selectOption(String(fixed.id));
    assert.equal(await menuForm.locator('[name="label"]').inputValue(), '既存ページ');
    await menuForm.locator('[data-form-setting="type"]').selectOption('custom');
    await menuForm.locator('[name="label"]').fill('ヘッダー配置確認');
    await menuForm.locator('[name="url"]').fill('/');
    await menuForm.getByRole('button', {name: '追加', exact: true}).click();
    await links('footer');
    assert.equal(await page.locator('[data-pane="header"]').getAttribute('aria-pressed'), 'false');
    assert.equal(await page.locator('[data-pane="footer"]').getAttribute('aria-pressed'), 'true');
    assert.equal(await page.locator('#nx-design-panel > h2').textContent(), 'フッター');
    await group('footer', 'links').locator('[data-action="add"]').click();
    await menuForm.locator('[data-form-setting="type"]').selectOption('category');
    const category = initial.targets.category.find(item => item.label === 'ニュース');
    assert(category);
    await menuForm.locator('[name="reference_id"]').selectOption(String(category.id));
    assert.equal(await menuForm.locator('[name="label"]').inputValue(), category.label);
    await menuForm.locator('[data-form-setting="type"]').selectOption('custom');
    await menuForm.locator('[name="label"]').fill('フッター配置確認');
    await menuForm.locator('[name="url"]').fill('/posts');
    await menuForm.getByRole('button', {name: '追加', exact: true}).click();
    await links('header');
    assert.equal(await page.locator('#nx-design-panel > h2').textContent(), 'ヘッダー');
    assert((await group('header', 'links').locator('.nx-design-item-list').textContent()).includes('ヘッダー配置確認'));
    assert(!(await group('header', 'links').locator('.nx-design-item-list').textContent()).includes('フッター配置確認'));
    const linkPublication = await publish();
    for (const [id, saved] of Object.entries(initial.themes)) {
        assert.deepEqual(linkPublication.states[id].layout, saved.state.layout, id + ': editing links preserves every existing layout and widget setting');
        assert.deepEqual(linkPublication.states[id].contentLayouts, saved.state.contentLayouts);
    }
    assert(linkPublication.states[initial.active].menus.header.some(item => item.label === 'ヘッダー配置確認'));
    assert(linkPublication.states[initial.active].menus.footer.some(item => item.label === 'フッター配置確認'));
    for (const kind of ['header', 'footer']) {
        const previous = initial.themes[initial.active].state.menus[kind];
        assert.deepEqual(linkPublication.states[initial.active].menus[kind].slice(0, previous.length), previous);
    }
    await page.reload();
    await links('footer');
    assert((await group('footer', 'links').locator('.nx-design-item-list').textContent()).includes('フッター配置確認'));
    console.log('PASS: integrated header/footer link editing preserves all existing theme layouts, widgets and shared page layouts');
    await open('header');
    for (const section of ['structure', 'top', 'widgets']) {
        assert.equal(await page.locator('[data-chrome-details="header:' + section + '"]').count(), 1);
    }
    await links('header');
    await group('header', 'links').locator('.nx-design-item-list [data-action="edit"]').last().click();
    assert.equal(await menuForm.locator('[name="label"]').inputValue(), 'ヘッダー配置確認');
    await menuForm.locator('[name="url"]').fill('/editing-link');
    await page.getByRole('button', {name: '＋ 下に段を追加', exact: true}).click();
    assert.equal(await menuForm.locator('[name="url"]').inputValue(), '/editing-link',
        'a layout control redraw retains the unfinished link form');
    await menuForm.locator('[name="url"]').fill('/');
    await menuForm.getByRole('button', {name: '反映', exact: true}).click();
    await set('header', 'top', 'columns', 5);
    await set('header', 'bottom', 'columns', 2);
    await set('header', 'top', 'align', 'flex-start');
    await set('header', 'bottom', 'align', 'center');
    async function moveWidget(type, destination) {
        const source = Object.entries(initial.themes[initial.active].state.layout.widgets)
            .find(([area, list]) => area.startsWith('header') && list.some(item => item.type === type))[0];
        const widgets = await openGroup('header', 'widgets');
        await selectWidgetLocation(widgets, source);
        const row = widgets.locator('.nx-design-item').filter({has: page.locator('span.nx-design-note', {hasText: initial.widgets[type]})});
        await row.locator('[data-action="edit"]').click();
        const form = page.locator('[data-designer-form]');
        await selectWidgetLocation(form, destination, true);
        assert.equal(await form.locator('[name="area"]').evaluate(el => el === document.activeElement), true);
        await form.getByRole('button', {name: '反映', exact: true}).click();
    }
    await moveWidget('menu', 'header_bottom1');
    await moveWidget('search', 'header5');
    await addWidget('header', 'header_bottom2', 'ヘッダー下段2');
    await addWidget('header', 'header_after', 'ヘッダー直下の案内');
    const direction = page.locator('[data-region-field="direction"]');
    const regionAlign = page.locator('[data-region-field="align"]');
    assert.equal(await direction.inputValue(), 'column');
    assert.equal(await regionAlign.inputValue(), 'auto');
    await regionAlign.selectOption('flex-end');
    await frame.locator('[data-design-area="header_after"][data-region-direction="column"][data-region-align="flex-end"]').waitFor();
    await page.locator('#nx-design-stage[aria-busy="false"]').waitFor();
    assert.equal(await region(frame, 'header_after').evaluate(node => getComputedStyle(node).justifyItems), 'end');
    await direction.selectOption('row');
    assert.equal(await regionAlign.inputValue(), 'flex-end', 'shared alignment is retained when switching direction');
    assert.deepEqual(await regionAlign.locator('option').allTextContents(), ['左寄せ', '中央寄せ', '右寄せ', '両端揃え']);
    await regionAlign.selectOption('center');
    await frame.locator('[data-design-area="header_after"][data-region-direction="row"][data-region-align="center"]').waitFor();
    await page.locator('#nx-design-stage[aria-busy="false"]').waitFor();
    assert.equal(await region(frame, 'header_after').evaluate(node => getComputedStyle(node).justifyContent), 'center');
    await open('footer');
    const footerRow = row => page.locator('[data-action="footer-row"][data-row="' + row + '"]');
    const footerStructure = page.locator('[data-chrome-details="footer:structure"]');
    assert((await footerStructure.textContent()).includes('フッター本体'));
    const footerBody = frame.locator('.site-footer > [data-chrome-row="top"]');
    const originalBody = await footerBody.evaluate(node => [getComputedStyle(node).display, node.offsetHeight, node.textContent]);
    await footerRow('above').click();
    await frame.locator('.site-footer > [data-chrome-row="above"]').waitFor();
    assert.deepEqual(await footerBody.evaluate(node => [getComputedStyle(node).display, node.offsetHeight, node.textContent]), originalBody);
    await footerRow('below').click();
    await frame.locator('.site-footer > [data-chrome-row="bottom"]').waitFor();
    assert.deepEqual(await footerBody.evaluate(node => [getComputedStyle(node).display, node.offsetHeight, node.textContent]), originalBody);
    await set('footer', 'above', 'columns', 3);
    await set('footer', 'above', 'align', 'flex-start');
    await set('footer', 'top', 'columns', 4);
    await set('footer', 'bottom', 'columns', 5);
    await set('footer', 'top', 'align', 'flex-end');
    await set('footer', 'bottom', 'align', 'space-between');
    assert.equal(await control('footer', 'bottom', 'columns').locator('option').count(), 5);
    assert.equal(await control('footer', 'bottom', 'align').locator('option').count(), 4);
    await addWidget('footer', 'footer4', 'フッター本体4');
    await addWidget('footer', 'footer1', 'フッター上段3', 'footer_above3');
    await addWidget('footer', 'footer_bottom5', 'フッター下段5');
    await addWidget('footer', 'footer_before', 'フッター直前の案内');
    await addWidget('footer', 'footer_before', 'フッター直前のウィジェット配置確認用の見出し');
    assert.equal(await direction.inputValue(), 'column', 'footer retains its own default direction');
    await direction.selectOption('row');
    await regionAlign.selectOption('space-between');
    await direction.selectOption('column');
    assert.equal(await regionAlign.inputValue(), 'auto', 'row-only alignment falls back to the vertical default');
    await direction.selectOption('row');
    await regionAlign.selectOption('space-between');
    await frame.locator('[data-design-area="footer_before"][data-region-direction="row"][data-region-align="space-between"]').waitFor();
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
    assert.equal(await control('footer', 'above', 'columns').inputValue(), '3');
    assert.equal(await control('footer', 'bottom', 'columns').inputValue(), '5');
    const data = JSON.parse(await page.locator('#nx-design-data').textContent());
    assert(data.themes.default.state.menus.footer.some(item => item.label === 'フッター配置確認' && item.url === '/posts'));
    assert(data.themes.default.state.layout.widgets.header_after.some(item => item.title === 'ヘッダー直下の案内'));
    assert(data.themes.default.state.layout.widgets.footer_before.some(item => item.title === 'フッター直前の案内'));
    assert.deepEqual(data.themes.default.state.layout.regions, {
        header_after: {direction: 'row', align: 'center'}, footer_before: {direction: 'row', align: 'space-between'},
    });
    assert.deepEqual(data.themes.monoedge.state.layout.regions, {
        header_after: {direction: 'column', align: 'auto'}, footer_before: {direction: 'column', align: 'auto'},
    });
    await selectWidgetLocation(await openGroup('footer', 'widgets'), 'footer_before');
    assert.equal(await direction.inputValue(), 'row');
    assert.equal(await regionAlign.inputValue(), 'space-between');
    assert.equal(data.themes.default.state.layout.widgets.header5[0].type, 'search');
    const guest = await page.context().browser().newContext({viewport: {width: 1440, height: 1000}, javaScriptEnabled: false});
    const visitor = await guest.newPage();
    await guest.route(base + '/', async route => {
        const response = await route.fetch();
        await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
    });
    await visitor.goto(base + '/');
    const expected = [
        ['header', 'top', 5, 'flex-start'], ['header', 'bottom', 2, 'center'],
        ['footer', 'above', 3, 'flex-start'], ['footer', 'top', 4, 'flex-end'], ['footer', 'bottom', 5, 'space-between'],
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
    assert((await visitor.locator('[data-widget-area="footer_above3"]').textContent()).includes('フッター上段3'));
    assert.deepEqual(await visitor.locator('.site-footer > [data-chrome-row]').evaluateAll(nodes =>
        nodes.map(node => node.dataset.chromeRow)), ['above', 'top', 'bottom']);
    assert.equal(await visitor.locator('[data-design-area="footer_before"] + .site-footer').count(), 1);
    for (const [area, alignment] of [['header_after', 'center'], ['footer_before', 'space-between']]) {
        const geometry = await region(visitor, area).evaluate(node => {
            const style = getComputedStyle(node);
            const outer = node.getBoundingClientRect();
            const widgets = Array.from(node.children, child => child.getBoundingClientRect());
            return {
                display: style.display, justify: style.justifyContent,
                tops: widgets.map(box => box.top),
                leftGap: widgets[0].left - outer.left - parseFloat(style.paddingLeft),
                rightGap: outer.right - widgets.at(-1).right - parseFloat(style.paddingRight),
            };
        });
        assert.equal(geometry.display, 'flex');
        assert.equal(geometry.justify, alignment);
        assert(geometry.tops.length >= 2 && geometry.tops.every(y => Math.abs(y - geometry.tops[0]) < 1));
        assert(Math.abs(geometry.leftGap - geometry.rightGap) < 1);
        if (alignment === 'space-between') assert(Math.abs(geometry.leftGap) < 1);
    }
    await visitor.screenshot({path: path.join(output, 'chrome-public-desktop.png'), fullPage: true});
    await visitor.setViewportSize({width: 390, height: 844});
    assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    assert(await region(visitor, 'footer_before').evaluate(node => {
        const tops = Array.from(node.children, child => child.getBoundingClientRect().top);
        return tops.some(top => top > tops[0]);
    }), 'horizontal widgets wrap within the mobile viewport');
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
        state => { state.layout.footerAbove.columns = 6; },
        state => { state.layout.footerAbove.enabled = 'yes'; },
        state => { state.layout.footerRows.bottom.align = 'url(https://example.test/)'; },
        state => { state.layout.headerRows.positions.menu.row = 'other'; },
        state => { state.layout.regions.header_after.direction = 'diagonal'; },
        state => { state.layout.regions.footer_before = {direction: 'column', align: 'space-between'}; },
    ]) {
        const state = structuredClone(data.themes.default.state);
        mutate(state);
        const response = await page.request.post(base + '/admin/design/publish', {
            form: {_csrf_token: data.token, design: JSON.stringify(state), revision: state.revision},
        });
        assert.equal(response.status(), 422);
    }
    await open('footer');
    await footerRow('below').click();
    await footerRow('above').click();
    await publish();
    await page.reload();
    await open('footer');
    assert.match(await footerRow('below').textContent(), /下に段を追加/);
    assert.match(await footerRow('above').textContent(), /上に段を追加/);
    await footerRow('above').click();
    await footerRow('below').click();
    await waitRows([5, 2, 4, 5]);
    await frame.locator('[data-widget-area="footer_above3"]', {hasText: 'フッター上段3'}).waitFor();
    await publish();
    await page.locator('[data-setting="theme"]').selectOption('monoedge');
    await open('header');
    await page.getByRole('button', {name: '＋ 下に段を追加', exact: true}).click();
    await set('header', 'top', 'columns', 5);
    await set('header', 'bottom', 'columns', 1);
    await open('footer');
    await footerRow('below').click();
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
    console.log('PASS: outside widget regions switch between vertical and horizontal, align independently, persist and wrap on mobile');
};
