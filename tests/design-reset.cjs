const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkDesignReset(page, base, output) {
    const request = page.request;
    const endpoint = base + '/admin/design/publish';
    const reset = page.locator('[data-action="reset"]');
    const publish = page.locator('[data-action="publish"]');
    async function catalog() {
        const response = await request.get(base + '/admin/design');
        assert.equal(response.status(), 200);
        return JSON.parse((await response.text()).match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
    }
    async function snapshot(kind, id) {
        const response = await request.get(base + '/admin/design/' + kind + '-layout/' + id);
        assert.equal(response.status(), 200);
        return response.json();
    }
    async function save(data, theme, design) {
        const response = await request.post(endpoint, {form: {
            _csrf_token: data.token, revision: data.themes[theme].state.revision, design: JSON.stringify(design),
        }});
        assert.equal(response.status(), 200, await response.text());
        return response.json();
    }
    async function waitPreview(text, sections) {
        await page.waitForFunction(({text, sections}) => {
            const stage = document.querySelector('#nx-design-stage');
            const doc = document.querySelector('#nx-design-frame').contentDocument;
            return stage.getAttribute('aria-busy') === 'false' && doc.body.textContent.includes(text) &&
                Boolean(doc.querySelector('.nx-page-section')) === sections;
        }, {text, sections});
    }

    const initial = await catalog();
    const fixed = initial.targets.page.find(item => item.label === '既存ページ');
    const post = initial.targets.post.find(item => item.label === '既存投稿');
    assert(fixed && post);
    const section = {...initial.pageLayout.sectionDefaults,
        groups: [[{type: 'page_title'}, {type: 'page_content'}], []]};
    for (const theme of ['default', 'monoedge']) {
        const data = await catalog();
        const state = data.themes[theme].state;
        state.layout.width = 'full';
        state.layout.columns = 'right';
        state.layout.headerRows = {...data.chromeDefaults.header, split: true};
        state.layout.headerAbove = true;
        state.layout.headerWidth = 'wide';
        state.layout.footer = 3;
        state.layout.footerWidth = 'standard';
        state.layout.widgets.sidebar = [{type: 'text', title: '初期化対象ウィジェット', text: 'サイドバー', menu: 'footer'}];
        state.layout.widgets.header_above = [{type: 'text', title: '初期化対象の上段', text: '上段', menu: 'footer'}];
        state.background = {mode: 'color', color: 'rgba(40, 80, 120, 0.5)', media_id: null};
        state.menus = {
            header: [{id: null, type: 'custom', label: '初期化対象リンク', reference_id: null, url: '/posts'}],
            footer: [{id: null, type: 'custom', label: 'フッターリンク', reference_id: null, url: '/'}],
        };
        state.contentLayouts = {all: {format: 2, enabled: true, sectionCount: 1, sections: [section]},
            page: {format: 2, width: 'wide'}, post: {format: 2, width: 'full'}};
        state.content = [];
        for (const [kind, id] of [['page', fixed.id], ['post', post.id]]) {
            const record = await snapshot(kind, id);
            state.content.push({kind, id, revision: record.revision, overrides: {format: 2, width: 'full'}});
        }
        await save(data, theme, state);
    }
    await request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme: 'monoedge'}});
    const before = await catalog();
    assert.equal(before.active, 'monoedge');
    await page.goto(base + '/admin/design');
    await page.locator('[data-pane="columns"]').click();
    const width = page.locator('[data-setting="width"]');
    await width.selectOption('wide');
    await reset.click();
    const target = page.locator('[data-setting="reset-target"]');
    await target.selectOption('page:' + fixed.id);
    await waitPreview('既存の内容', false);
    const preview = page.frameLocator('#nx-design-frame');
    assert.equal(await preview.locator('aside.nx-design-sidebar').count(), 0);
    assert.equal(await preview.getByText('初期化対象リンク').count(), 0);
    assert.equal(await preview.locator('link[href*="nexapress/assets/site-background"]').count(), 0);
    assert.equal(await preview.locator('.site-header [data-widget-area="header_above"]').count(), 0);
    assert.equal(await preview.locator('.site-main').getAttribute('data-content-width'), 'standard');
    assert(await preview.locator('.site-footer').textContent().then(text => text.includes('Powered by MonoEdge')));
    await page.locator('[data-mode="published"]').click();
    await waitPreview('初期化対象リンク', true);
    assert.equal(await publish.isDisabled(), true);
    assert.equal((await catalog()).resetRevision, before.resetRevision);
    await reset.click();
    assert.equal(await width.inputValue(), 'wide', 'cancel retains the unfinished edits');

    const edited = before.themes[before.active].state;
    const postLayout = await snapshot('post', post.id);
    const next = await save(before, before.active, {...edited,
        content: [{kind: 'post', id: post.id, revision: postLayout.revision, overrides: {format: 2, width: 'wide'}}],
    });
    assert.equal(next.states[before.active].revision, edited.revision, 'only the individual layout changed');
    const stale = await request.post(endpoint, {form: {_csrf_token: before.token, reset: '1', revision: before.resetRevision}});
    assert.equal(stale.status(), 409);
    assert.equal((await snapshot('post', post.id)).overrides.width, 'wide', 'a stale reset preserves newer changes');
    const invalid = await request.post(endpoint, {form: {_csrf_token: 'invalid', reset: '1', revision: next.resetRevision}});
    assert.equal(invalid.status(), 419);

    await page.reload();
    await reset.click();
    await page.locator('[data-setting="theme"]').selectOption('default');
    await target.selectOption('post:' + post.id);
    await waitPreview('既存投稿の本文', false);
    assert((await preview.locator('.site-footer').textContent()).includes('All rights reserved'));
    await page.screenshot({path: path.join(output, 'design-reset-preview.png')});
    const desktop = page.viewportSize();
    await page.setViewportSize({width: 600, height: 900});
    await page.locator('[data-device="mobile"]').click();
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    assert.equal(await page.locator('#nx-design-frame').evaluate(frame => frame.contentWindow.innerWidth), 390);
    await page.screenshot({path: path.join(output, 'design-reset-preview-mobile.png')});
    await page.setViewportSize(desktop);
    page.once('dialog', dialog => dialog.dismiss());
    await publish.click();
    assert.equal((await catalog()).resetRevision, next.resetRevision);

    page.once('dialog', dialog => dialog.accept());
    const [response] = await Promise.all([page.waitForResponse(endpoint), page.waitForEvent('domcontentloaded'), publish.click()]);
    assert.equal(response.status(), 200);
    const after = await catalog();
    assert.equal(after.active, before.active);
    for (const {state} of Object.values(after.themes)) {
        assert.equal(state.layout.width, 'standard');
        assert.equal(state.layout.columns, 'none');
        assert.equal(state.layout.headerRows, null);
        assert.equal(state.layout.headerAbove, false);
        assert.equal(state.layout.headerWidth, 'theme');
        assert.equal(state.layout.footerWidth, 'theme');
        assert.equal(state.layout.footer, 1);
        assert.equal(state.background.mode, 'theme');
        assert.deepEqual(state.layout.widgets.sidebar, []);
        assert.equal(state.layout.widgets.footer1[0].type, 'copyright');
        assert.deepEqual(state.menus, {header: [], footer: []});
        assert(Object.values(state.contentLayouts).every(layout => layout.format === 2 && Object.keys(layout).length === 1));
    }
    assert.deepEqual((await snapshot('page', fixed.id)).overrides, {format: 2});
    assert.deepEqual((await snapshot('post', post.id)).overrides, {format: 2});

    const guest = await page.context().browser().newContext({javaScriptEnabled: false});
    try {
        for (const [url, text] of [['/page/legacy', '既存の内容'], ['/legacy-post', '既存投稿の本文']]) {
            const content = await guest.request.get(base + url);
            assert.equal(content.status(), 200);
            const html = await content.text();
            assert(html.includes(text));
            assert(!html.includes('nx-page-section') && !html.includes('初期化対象リンク') && !html.includes('初期化対象ウィジェット'));
        }
        const media = await guest.request.get(base + '/public/uploads/media/legacy-media.txt');
        assert.equal(await media.text(), 'previously uploaded media');
    } finally {
        await guest.close();
    }
    console.log('PASS: reset preview, draft cancellation, concurrency and confirmation, all themes and individual layouts reset with published content preserved');
};
