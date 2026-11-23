const assert = require('assert/strict');
const path = require('path');
const selectWidgetLocation = require('./widget-location.cjs');

module.exports = async function checkChromeLayouts(page, base, output) {
    const control = (kind, row, field) => page.locator(
        '[data-chrome="' + kind + '"][data-chrome-row="' + row + '"][data-chrome-field="' + field + '"]');
    const group = (kind, key) => page.locator('[data-chrome-details="' + kind + ':' + key + '"]');
    const frame = page.frameLocator('#nx-design-frame');
    const region = (surface, area) => surface.locator('[data-design-area="' + area + '"]');
    async function open(kind) { await page.locator('[data-pane="' + kind.split('_')[0] + '"]').click(); }
    async function openGroup(kind, key) {
        const details = group(kind, key);
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').press('Enter');
        return details;
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
    for (const [kind, scope, label] of [['header', 'header_after', 'ヘッダー直下'], ['footer', 'footer_before', 'フッター直前']]) {
        await open(kind);
        const main = await openGroup(kind, 'widgets');
        assert.equal(await group(kind, 'links').count(), 0);
        assert.equal(await main.locator('[data-widget-row="list"] option[value="' + scope + '"]').count(), 0);
        const separate = page.locator('[data-chrome-details="' + kind + ':widgets"] + [data-region-settings="' + scope + '"]');
        assert.equal(await separate.locator('h3').textContent(), label);
        for (const key of ['spacing', 'structure', 'top', 'widgets']) {
            assert.equal(await separate.locator('[data-chrome-details="' + scope + ':' + key + '"]').count(), 1);
        }
    }
    await open('header');
    for (const section of ['structure', 'top', 'widgets']) {
        assert.equal(await page.locator('[data-chrome-details="header:' + section + '"]').count(), 1);
    }
    await headerWidgets.locator('[data-action="add"]').click();
    const draftWidget = headerWidgets.locator('[data-designer-form]');
    await draftWidget.locator('[name="title"]').fill('編集中のウィジェット');
    await page.locator('[data-action="header-row"][data-row="below"]').click();
    assert.equal(await draftWidget.locator('[name="title"]').inputValue(), '編集中のウィジェット',
        'a layout control redraw retains the unfinished widget form');
    await draftWidget.locator('[data-action="cancel"]').click();
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
    await addWidget('header_after', 'header_after', 'ヘッダー直下の案内');
    const regionalField = (scope, row, field) => group(scope, row).locator('[data-region-field="' + field + '"]');
    await openGroup('header_after', 'top');
    const headerDirection = regionalField('header_after', 'top', 'direction');
    const headerAlign = regionalField('header_after', 'top', 'align');
    assert.equal(await headerDirection.inputValue(), 'column');
    const placementOptions = ['先頭寄せ', '中央寄せ', '末尾寄せ', '両端揃え', '両側に配分', 'すべて等間隔'];
    assert.equal(await headerAlign.inputValue(), 'flex-start');
    const headerWidth = regionalField('header_after', 'top', 'width');
    assert.deepEqual(await headerWidth.locator('option').allTextContents(), ['自動', '幅いっぱい', '中身に合わせる']);
    assert.deepEqual(await headerAlign.locator('option').allTextContents(), placementOptions);
    await headerWidth.selectOption('fit');
    await regionalField('header_after', 'top', 'cross').selectOption('flex-end');
    await frame.locator('[data-design-area="header_after"][data-region-direction="column"][data-region-align="flex-end"]').waitFor();
    await page.locator('#nx-design-stage[aria-busy="false"]').waitFor();
    assert(await region(frame, 'header_after').evaluate(node => {
        const box = node.getBoundingClientRect();
        const widget = node.firstElementChild.getBoundingClientRect();
        return Math.abs(box.right - parseFloat(getComputedStyle(node).paddingRight) - widget.right) < 1;
    }), 'a content-width widget is placed at the end of the cross axis');
    await addWidget('header_after', 'header_after', 'ヘッダー直下の連絡先');
    await openGroup('header_after', 'top');
    await headerWidth.selectOption('auto');
    await regionalField('header_after', 'top', 'cross').selectOption(
        initial.themes.default.state.layout.regions.header_after.cross);
    await headerAlign.selectOption('space-around');
    await headerDirection.selectOption('row');
    assert.equal(await headerAlign.inputValue(), 'space-around', 'shared placement is retained when switching direction');
    assert.deepEqual(await headerAlign.locator('option').allTextContents(), placementOptions);
    await headerAlign.selectOption('center');
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
    assert.deepEqual(await control('footer', 'bottom', 'align').locator('option').allTextContents(), placementOptions);
    await addWidget('footer', 'footer4', 'フッター本体4');
    await addWidget('footer', 'footer1', 'フッター上段3', 'footer_above3');
    await addWidget('footer', 'footer_bottom5', 'フッター下段5');
    await addWidget('footer_before', 'footer_before', 'フッター直前の案内');
    await addWidget('footer_before', 'footer_before', 'フッター直前のウィジェット配置確認用の見出し');
    await openGroup('footer_before', 'top');
    const direction = regionalField('footer_before', 'top', 'direction');
    const regionAlign = regionalField('footer_before', 'top', 'align');
    assert.equal(await direction.inputValue(), 'column', 'footer retains its own default direction');
    await direction.selectOption('row');
    await regionAlign.selectOption('space-between');
    await direction.selectOption('column');
    assert.equal(await regionAlign.inputValue(), 'space-between', 'placement applies along the selected direction');
    assert.deepEqual(await regionAlign.locator('option').allTextContents(), placementOptions);
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
    assert.deepEqual(data.themes.default.state.menus, initial.themes.default.state.menus, 'legacy links survive publication');
    assert(data.themes.default.state.layout.widgets.header_after.some(item => item.title === 'ヘッダー直下の案内'));
    assert(data.themes.default.state.layout.widgets.footer_before.some(item => item.title === 'フッター直前の案内'));
    const initialRegions = initial.themes.default.state.layout.regions;
    assert.deepEqual(data.themes.default.state.layout.regions, {
        header_after: {...initialRegions.header_after, direction: 'row', align: 'center'},
        footer_before: {...initialRegions.footer_before, direction: 'row', align: 'space-between'},
    });
    assert.deepEqual(data.themes.monoedge.state.layout.regions, initial.themes.monoedge.state.layout.regions);
    await openGroup('footer_before', 'top');
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
        assert(geometry.tops.length >= 2 && geometry.tops.every(y => Math.abs(y - geometry.tops[0]) < 1),
            area + ' ' + JSON.stringify(geometry));
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
        state => { state.layout.regions.footer_before.width = 'wide'; },
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
    const structure = await openGroup('footer_before', 'structure');
    const upper = structure.locator('[data-action="region-row"][data-row="above"]');
    await upper.click();
    await structure.locator('[data-action="region-row"][data-row="below"]').click();
    await openGroup('footer_before', 'above');
    await regionalField('footer_before', 'above', 'columns').selectOption('3');
    await openGroup('footer_before', 'top');
    await regionalField('footer_before', 'top', 'columns').selectOption('2');
    await addWidget('footer_before', 'footer_before_above3', '独立した上段3列目');
    await addWidget('footer_before', 'footer_before_top2', '独立した本体2列目');
    await addWidget('footer_before', 'footer_before_bottom1', '独立した下段');
    const outsideSpacing = await openGroup('footer_before', 'spacing');
    await outsideSpacing.locator('[data-spacing-field="paddingTop"]').fill('35');
    await outsideSpacing.locator('[data-spacing-target="footer_before"]').click();
    await page.waitForFunction(() => {
        const overlay = document.querySelector('#nx-design-spacing-overlay');
        const element = document.querySelector('#nx-design-frame').contentDocument.querySelector('[data-design-area="footer_before"]');
        return !overlay.hidden && overlay.dataset.spacingArea === 'footer_before' &&
            element?.ownerDocument.defaultView.getComputedStyle(element).paddingTop === '35px';
    });
    await page.screenshot({path: path.join(output, 'independent-region-settings.png')});
    await upper.click();
    await frame.locator('[data-widget-area="footer_before_above3"]').waitFor({state: 'detached'});
    await upper.click();
    await frame.locator('[data-widget-area="footer_before_above3"]', {hasText: '独立した上段3列目'}).waitFor();
    const separated = await publish();
    const layout = separated.states.default.layout;
    assert.equal(layout.regions.footer_before.columns, 2);
    assert.equal(layout.regions.footer_before.above.columns, 3);
    assert.equal(layout.regions.footer_before.bottom.enabled, true);
    assert.equal(layout.spacing.footer_before.paddingTop, 35);
    assert.equal(layout.spacing.footer.paddingTop, data.themes.default.state.layout.spacing.footer.paddingTop);
    assert.deepEqual(separated.states.default.menus, initial.themes.default.state.menus);
    const publishedPage = await page.context().browser().newPage({viewport: {width: 1440, height: 920}, javaScriptEnabled: false});
    try {
        await publishedPage.goto(base + '/');
        const columns = region(publishedPage, 'footer_before').locator('[data-region-row="top"] > [data-widget-area]');
        const geometry = async () => columns.evaluateAll(slots =>
            slots.map(slot => [slot.getBoundingClientRect().x, slot.getBoundingClientRect().y]));
        const desktop = await geometry();
        assert.equal(desktop.length, 2);
        assert(desktop[1][0] > desktop[0][0] && desktop[1][1] === desktop[0][1]);
        await publishedPage.setViewportSize({width: 390, height: 844});
        const mobile = await geometry();
        assert(mobile[1][0] === mobile[0][0] && mobile[1][1] > mobile[0][1]);
    } finally {
        await publishedPage.close();
    }
    console.log('PASS: upper/lower header/footer columns (1–5), six Flex alignments, content slots, strict CSP, responsive layout, validation, retained rows and independent themes');
    console.log('PASS: independent outside region editors, retained links, per-column widgets, spacing, responsive rows and persistence');

    await open('columns');
    const body = frame.locator('.nx-design-main[data-nx-layout-width="80"][data-nx-placement="space-evenly"]');
    const bodyPlacement = page.locator('[data-setting="body-align"]');
    const bodyWidth = page.locator('[data-setting="body-width"]');
    assert.deepEqual(await bodyPlacement.locator('option').allTextContents(), placementOptions);
    await page.locator('[data-setting="columns"]').selectOption('right');
    await bodyWidth.fill('80');
    await bodyWidth.press('Tab');
    await bodyPlacement.selectOption('space-evenly');
    await body.waitFor();
    const bodyGeometry = await body.evaluate(node => {
        const style = getComputedStyle(node);
        const width = node.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        const tracks = style.gridTemplateColumns.split(' ').map(parseFloat);
        const start = node.firstElementChild.getBoundingClientRect().left - node.getBoundingClientRect().left - parseFloat(style.paddingLeft);
        return {width, tracks, start, alignment: style.justifyContent};
    });
    assert.equal(bodyGeometry.alignment, 'space-evenly');
    assert.equal(bodyGeometry.tracks.length, 2);
    const bodyTrackWidth = bodyGeometry.tracks.reduce((sum, width) => sum + width, 0);
    assert(Math.abs(bodyTrackWidth + 24 - bodyGeometry.width * .8) < 2, JSON.stringify(bodyGeometry));
    assert(Math.abs(bodyGeometry.start - bodyGeometry.width * .2 / 3) < 2, JSON.stringify(bodyGeometry));

    await page.locator('[data-pane="page_layout"]').click();
    const editor = page.locator('[data-layout-scope="page_item"]');
    await editor.locator('> summary').click();
    const sectionField = name => editor.locator('[data-page-field="' + name + '"]');
    await sectionField('page').selectOption({label: '既存ページ'});
    await sectionField('enabled').check();
    const sectionDetails = editor.locator('[data-page-details="layout"]');
    if (!(await sectionDetails.evaluate(node => node.open))) await sectionDetails.locator('summary').click();
    assert.deepEqual(await sectionField('align').locator('option').allTextContents(), placementOptions);
    await sectionField('columns').selectOption('2');
    await sectionField('columnsWidth').fill('70');
    await sectionField('columnsWidth').press('Tab');
    await sectionField('gap').fill('10');
    await sectionField('gap').press('Tab');
    await sectionField('align').selectOption('space-around');
    const section = frame.locator('[data-layout-section="0"][data-nx-layout-width="70"][data-nx-placement="space-around"]');
    await section.waitFor();
    const verifySection = async (section, stacked) => {
        const geometry = await section.locator('.nx-page-section-inner').evaluate(node => {
            const style = getComputedStyle(node);
            const width = node.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const tracks = style.gridTemplateColumns.split(' ').map(parseFloat);
            const start = node.firstElementChild.getBoundingClientRect().left - node.getBoundingClientRect().left - parseFloat(style.paddingLeft);
            return {width, tracks, start, alignment: style.justifyContent};
        });
        assert.equal(geometry.alignment, 'space-around');
        assert.equal(geometry.tracks.length, stacked ? 1 : 2);
        if (!stacked) {
            const trackWidth = geometry.tracks.reduce((sum, width) => sum + width, 0);
            assert(Math.abs(trackWidth - geometry.width * .6) < 2, JSON.stringify(geometry));
            assert(Math.abs(geometry.start - geometry.width * .3 / 4) < 2, JSON.stringify(geometry));
        }
    };
    await verifySection(section, false);
    await publish();
    const publicSection = await page.context().browser().newPage({viewport: {width: 1440, height: 900}});
    try {
        await publicSection.goto(base + '/page/legacy');
        const section = publicSection.locator('[data-layout-section="0"][data-nx-layout-width="70"]');
        await verifySection(section, false);
        await publicSection.setViewportSize({width: 390, height: 844});
        await verifySection(section, true);
    } finally {
        await publicSection.close();
    }
    console.log('PASS: body columns and page sections use the same six placement options and independent widths in preview and on the published page');
};
