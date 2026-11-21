const assert = require('assert/strict');
const path = require('path');
const selectWidgetLocation = require('./widget-location.cjs');

module.exports = async function checkHeaderWidgets(page, base, output) {
    const endpoint = base + '/admin/design/publish';
    async function catalog() {
        const response = await page.request.get(base + '/admin/design');
        assert.equal(response.status(), 200);
        return JSON.parse((await response.text()).match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
    }
    async function save(theme, state) {
        const data = await catalog();
        const response = await page.request.post(endpoint, {form: {_csrf_token: data.token,
            revision: data.themes[theme].state.revision, design: JSON.stringify(state)}});
        assert.equal(response.status(), 200, await response.text());
    }
    const initial = await catalog();
    await page.goto(base + '/admin/settings/general');
    const general = await page.locator('.general-settings-form').evaluate(form => Object.fromEntries(new FormData(form)));
    // Earlier integration checks may have trashed the selected front page.
    // Keep its existing article-list fallback when saving this test's identity.
    if (general.homepage_mode === 'page' && !Number(general.homepage_page_id)) general.homepage_mode = 'posts';
    const siteName = 'サイト名 <b data-site-name>確認</b> & "引用"';
    const icon = '/public/assets/images/extension-default.svg';
    await page.request.post(base + '/admin/settings/general/update', {form: {...general, site_title: siteName, site_icon: icon}});
    await page.reload();
    assert.equal(await page.locator('[name="site_title"]').inputValue(), siteName);
    assert.equal(await page.locator('[name="site_icon"]').inputValue(), icon);
    const form = page.locator('[data-designer-form]');
    const preview = page.frameLocator('#nx-design-frame');
    const settled = () => page.locator('#nx-design-stage[aria-busy="false"]').waitFor();
    async function widgets(area, index) {
        const pane = area.startsWith('header') ? 'header' : 'footer';
        await page.locator('[data-pane="' + pane + '"]').click();
        const group = page.locator('[data-chrome-details="' + pane + ':widgets"]');
        if (!(await group.evaluate(node => node.open))) await group.locator('summary').press('Enter');
        await selectWidgetLocation(group, area);
        if (index === undefined) await group.locator('[data-action="add"]').click();
        else await group.locator('[data-action="edit"]').nth(index).click();
        return group;
    }
    async function apply() {
        await form.locator('button[type="submit"]').click();
        await settled();
    }
    async function publish() {
        const [response] = await Promise.all([page.waitForResponse(endpoint), page.locator('[data-action="publish"]').click()]);
        assert.equal(response.status(), 200, await response.text());
        await page.locator('[data-action="publish"]:disabled').waitFor();
        return response.json();
    }
    const guest = await page.context().browser().newContext({viewport: {width: 1440, height: 1000}, javaScriptEnabled: false});
    try {
        const visitor = await guest.newPage();
        for (const theme of ['default', 'monoedge']) {
            const state = structuredClone(initial.themes[theme].state);
            state.layout.headerRows = null;
            state.layout.header.placement = 'row';
            state.layout.headerAbove = false;
            state.layout.headerBelow = false;
            for (const area of initial.headerWidgetAreas) state.layout.widgets[area] = [];
            const widget = type => ({type, title: '', text: '', menu: 'header'});
            state.layout.widgets.header1 = [widget('site_icon'), widget('site_name')];
            state.layout.widgets.header2 = [widget('menu')];
            state.layout.widgets.header3 = [widget('search')];
            state.layout.widgets.footer1 = [];
            await save(theme, state);
            await page.request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme}});
            await page.goto(base + '/admin/design');
            await settled();
            assert.equal(await page.locator('[data-chrome-details="header:content"], [data-header], [data-chrome-part]').count(), 0);
            await widgets('header1', 0);
            assert.equal(await form.locator('[name="type"]').inputValue(), 'site_icon');
            assert.equal(await form.locator('[name="title"]').count(), 0);
            assert((await form.locator('[name="type"] option').evaluateAll(nodes => nodes.map(node => node.value))).includes('site_name'));
            assert((await form.locator('[name="area"] option').evaluateAll(nodes => nodes.map(node => node.value)))
                .every(area => initial.headerWidgetAreas.includes(area)));
            await page.screenshot({path: path.join(output, theme + '-header-widget-types.png')});
            await form.locator('[data-action="cancel"]').click();
            await widgets('header1', 1);
            assert.equal(await form.locator('[name="type"]').inputValue(), 'site_name');
            await selectWidgetLocation(form, 'header2', true);
            await apply();
            await widgets('header3', 0);
            const headerLocations = form.locator('[data-widget-row="form"] option');
            assert((await headerLocations.allTextContents()).includes('ヘッダー本体'));
            assert((await headerLocations.allTextContents()).includes('ヘッダー直下'));
            assert(!(await headerLocations.allTextContents()).some(label => label.includes('フッター')));
            await form.locator('[data-action="cancel"]').click();
            await widgets('footer1');
            const footerTypes = await form.locator('[name="type"] option').evaluateAll(nodes => nodes.map(node => node.value));
            assert(footerTypes.includes('search') && !footerTypes.includes('site_icon') && !footerTypes.includes('site_name'));
            const footerLocations = form.locator('[data-widget-row="form"] option');
            assert((await footerLocations.allTextContents()).includes('フッター本体'));
            assert((await footerLocations.allTextContents()).includes('フッター直前'));
            assert(!(await footerLocations.allTextContents()).some(label => label.includes('ヘッダー')));
            await form.locator('[name="type"]').selectOption('search');
            const heading = '検索 <img data-widget-attack src=x> & "確認"';
            await form.locator('[name="title"]').fill(heading);
            await apply();
            await publish();
            await page.reload();
            await settled();
            const saved = (await catalog()).themes[theme].state.layout;
            assert.equal(saved.headerWidgets, true);
            assert(saved.widgets.header2.some(item => item.type === 'site_name'));
            assert(saved.widgets.footer1.some(item => item.type === 'search'));
            await visitor.goto(base + '/');
            assert.equal(await visitor.locator('[data-widget-area="header1"] .site-icon').getAttribute('src'), icon);
            assert.equal(await visitor.locator('[data-widget-area="header2"] .site-title').textContent(), siteName);
            assert.equal(await visitor.locator('[data-site-name], [data-widget-attack]').count(), 0);
            assert.equal(await visitor.locator('.site-footer .site-title, .site-footer .site-icon').count(), 0);
            assert.equal(await visitor.locator('.site-footer .nx-design-widget-search h2').textContent(), heading);
            assert.equal(await visitor.locator('.site-footer .nx-design-widget-search .public-search').count(), 1);
            const query = '検索 & "確認"';
            const searchUrl = new URL(await visitor.locator('.site-header .public-search').getAttribute('action'), base);
            searchUrl.searchParams.set('q', query);
            await visitor.goto(searchUrl.href);
            assert.equal(await visitor.locator('.site-header [name="q"]').inputValue(), query);
            await visitor.screenshot({path: path.join(output, theme + '-header-footer-public.png'), fullPage: true});
            await visitor.setViewportSize({width: 390, height: 844});
            assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await visitor.screenshot({path: path.join(output, theme + '-header-footer-mobile.png'), fullPage: true});
            await visitor.setViewportSize({width: 1440, height: 1000});
            await page.locator('[data-pane="header"]').click();
            const group = page.locator('[data-chrome-details="header:widgets"]');
            if (!(await group.evaluate(node => node.open))) await group.locator('summary').press('Enter');
            await selectWidgetLocation(group, 'header2');
            await group.locator('[data-action="remove"]').nth(1).click();
            await publish();
            await page.reload();
            await settled();
            assert.equal(await preview.locator('.site-title').count(), 0, 'deleted site name remains absent after reload');
            assert.equal(await preview.locator('.site-icon').count(), 1, 'site icon remains independent');
            const legacy = structuredClone((await catalog()).themes[theme].state);
            delete legacy.layout.headerWidgets;
            legacy.layout.headerRows = null;
            legacy.layout.header.placement = 'stack';
            for (const area of initial.headerWidgetAreas) legacy.layout.widgets[area] = [];
            for (const flag of ['logo', 'name', 'menu', 'search']) legacy.layout.header[flag] = true;
            await save(theme, legacy);
            await page.reload();
            await settled();
            const boxes = await preview.locator('[data-widget-area="header1"]').evaluate(node =>
                [...node.children].map(child => {
                    const box = child.getBoundingClientRect();
                    return {top: box.top, bottom: box.bottom, height: box.height};
                }));
            assert.equal(boxes.length, 3);
            assert(boxes.every((box, index) => index === 0 || box.top >= boxes[index - 1].bottom));
            assert(boxes[2].height < 100, 'native stacked search keeps its normal height');
            console.log('PASS: ' + theme + ' unified widget types, header-only site identity, footer search, movement, deletion, escaping and mobile layout');
        }
        const data = await catalog();
        for (const mutate of [
            state => { state.layout.widgets.footer1 = [{type: 'site_icon'}]; },
            state => { state.layout.widgets.footer_bottom1 = [{type: 'site_name'}]; },
            state => { state.layout.columns = 'right'; state.layout.widgets.sidebar = [{type: 'site_name'}]; },
            state => { state.layout.widgets.header_after = [{type: 'site_icon'}]; },
            state => { state.layout.headerWidgets = 'true'; },
            state => { state.layout.widgets.header1 = [{type: 'site_name', title: ['invalid']}]; },
        ]) {
            const state = structuredClone(data.themes[data.active].state);
            mutate(state);
            const response = await page.request.post(endpoint, {form: {_csrf_token: data.token,
                revision: state.revision, design: JSON.stringify(state)}});
            assert.equal(response.status(), 422, await response.text());
        }
        assert.equal((await catalog()).themes[data.active].state.revision, data.themes[data.active].state.revision);
        console.log('PASS: server rejects forbidden placements and malformed widget data without altering published settings');
    } finally {
        await guest.close();
        for (const [theme, entry] of Object.entries(initial.themes)) await save(theme, entry.state);
        await page.request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme: initial.active}});
        await page.request.post(base + '/admin/settings/general/update', {form: general});
        await page.goto(base + '/admin/design');
    }
};
