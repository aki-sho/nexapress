const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkDesign(page, base, output) {
    const request = page.request;
    async function headerAfterWidgets() {
        await page.locator('[data-pane="header"]').click();
        const details = page.locator('[data-chrome-details="header_after:widgets"]');
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').press('Enter');
        return details;
    }
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
    async function save(theme, mutate, overrides = []) {
        const data = await catalog();
        const state = structuredClone(data.themes[theme].state);
        mutate(state, data);
        state.content = [];
        for (const [kind, id, patch] of overrides) {
            const saved = await snapshot(kind, id);
            state.content.push({kind, id, revision: saved.revision, overrides: patch});
        }
        const response = await request.post(base + '/admin/design/publish', {
            form: {_csrf_token: data.token, design: JSON.stringify(state), revision: state.revision},
        });
        assert.equal(response.status(), 200, await response.text());
    }
    async function waitPreview() {
        await page.waitForFunction(() => document.querySelector('#nx-design-stage').getAttribute('aria-busy') === 'false' &&
            document.querySelector('#nx-design-frame').contentDocument.querySelector('.site-footer'));
    }
    const original = await catalog();
    const theme = original.active;
    const fixed = original.targets.page.find(item => item.label === '既存ページ');
    const post = original.targets.post.find(item => item.label === '既存投稿');
    assert(fixed && post);
    const savedPage = await snapshot('page', fixed.id);
    const savedPost = await snapshot('post', post.id);
    const created = await request.post(base + '/admin/pages/store', {form: {
        _csrf_token: original.token, title: 'デザイン検証・個別', slug: 'design-audit-local',
        content: '<p>個別の本文</p>', status: 'published',
    }});
    assert.equal(created.status(), 200);
    const local = (await catalog()).targets.page.find(item => item.label === 'デザイン検証・個別');
    assert(local);
    const section = {...original.pageLayout.sectionDefaults, color: '#2C233D', widthMode: 'percent', percent: 80,
        columns: 2, share: 50, gap: 10, groups: [[{type: 'page_title'}, {type: 'page_content'}],
            [{type: 'heading', text: '右カラム'}, {type: 'text', text: '横幅と背景色を確認します。'}]]};
    const widgets = [
        {type: 'text', title: 'サイドバー確認', text: 'サイドバーの本文', menu: 'footer'},
        {type: 'search', title: '記事を検索', text: '', menu: 'footer'},
        {type: 'page', title: '', reference_id: fixed.id, url: '', text: '', menu: 'footer'},
        {type: 'post', title: '', reference_id: post.id, url: '', text: '', menu: 'footer'},
        {type: 'custom', title: '任意リンク確認', reference_id: null, url: '/posts', text: '', menu: 'footer'},
    ];
    const failures = [];
    const check = (label, callback) => {
        try { callback(); console.log('PASS: ' + label); }
        catch (error) { failures.push(label + ': ' + error.message); console.log('FAIL: ' + label); }
    };
    const guest = await page.context().browser().newContext({viewport: {width: 1600, height: 1000}, javaScriptEnabled: false});
    await guest.route(base + '/**', async route => {
        if (route.request().resourceType() !== 'document') return route.continue();
        const response = await route.fetch();
        await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
    });
    const visitor = await guest.newPage();
    try {
        await save(theme, state => {
            state.layout.columns = 'right';
            state.layout.width = 'standard';
            state.layout.widgets.sidebar = widgets;
            state.contentLayouts = {all: {format: 2, enabled: true, sectionCount: 1, sections: [section]},
                page: {format: 2}, post: {format: 2}};
        }, [['page', fixed.id, {format: 2}], ['post', post.id, {format: 2}],
            ['page', local.id, {format: 2, enabled: false}]]);
        await page.goto(base + '/admin/design');
        await page.locator('[data-pane="columns"]').click();
        const target = page.locator('[data-setting="body-target"]');
        await target.selectOption('page:' + fixed.id);
        await waitPreview();
        const previewSidebar = await page.frameLocator('#nx-design-frame').locator('aside.nx-design-sidebar').count();
        check('section page sidebar exists in the design iframe', () => assert.equal(previewSidebar, 1));
        await target.selectOption('page:' + local.id);
        await waitPreview();
        const ordinary = page.frameLocator('#nx-design-frame');
        await ordinary.locator('article.post-detail').waitFor();
        const sectionCount = await ordinary.locator('.nx-page-section').count();
        check('switching to a page using body blocks shows the regular article', () => assert.equal(sectionCount, 0));
        await target.selectOption('page:' + fixed.id);
        await waitPreview();
        await visitor.goto(base + '/page/legacy');
        const publishedSidebar = await visitor.locator('aside.nx-design-sidebar').count();
        check('published section sidebar is rendered without JavaScript', () => assert.equal(publishedSidebar, 1));
        if (failures.length) throw new Error(failures.join('\n'));

        // Exercise actual controls, then confirm the result survives publication and a new request.
        await page.locator('[data-pane="columns"]').click();
        await page.locator('[data-setting="columns"]').selectOption('left');
        const sidebar = page.locator('[data-chrome-details="columns:widgets"]');
        await sidebar.locator('> summary').press('Enter');
        await sidebar.locator('[data-action="add"]').click();
        const form = page.locator('[data-designer-form]');
        await form.locator('[name="title"]').fill('移動するウィジェット');
        await form.locator('[name="text"]').fill('編集操作の確認');
        await form.getByRole('button', {name: '追加', exact: true}).click();
        await sidebar.locator('[data-action="edit"][data-index="5"]').click();
        await form.locator('[data-form-setting="type"]').selectOption('custom');
        await form.locator('[name="url"]').fill('/posts');
        await form.locator('[name="area"]').selectOption('footer_before');
        await form.getByRole('button', {name: '反映', exact: true}).click();
        assert.equal(await page.locator('[data-pane="footer"]').getAttribute('aria-pressed'), 'true');
        assert((await page.locator('[data-chrome-details="footer_before:widgets"][open]').textContent()).includes('移動するウィジェット'));
        const links = await headerAfterWidgets();
        const widgetCount = await links.locator('.nx-design-item').count();
        await links.locator('[data-action="add"]').click();
        await form.locator('[data-form-setting="type"]').selectOption('post');
        await form.locator('[name="reference_id"]').selectOption(String(post.id));
        assert.equal(await form.locator('[name="title"]').inputValue(), '既存投稿');
        await form.locator('[name="title"]').fill('投稿リンク確認');
        await form.getByRole('button', {name: '追加', exact: true}).click();
        await page.frameLocator('#nx-design-frame').locator('[data-design-area="header_after"] a', {hasText: '投稿リンク確認'}).waitFor();
        const [published] = await Promise.all([page.waitForResponse(base + '/admin/design/publish'),
            page.locator('[data-action="publish"]').click()]);
        assert.equal(published.status(), 200);
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
        await page.reload();
        let stored = await catalog();
        assert.equal(stored.themes[theme].state.layout.columns, 'left');
        assert.equal(stored.themes[theme].state.layout.widgets.sidebar.length, 5);
        assert(stored.themes[theme].state.layout.widgets.footer_before.some(item => item.title === '移動するウィジェット'));
        await visitor.goto(base + '/page/legacy');
        assert.equal(await visitor.locator('[data-design-area="header_after"] a', {hasText: '投稿リンク確認'}).count(), 1);
        assert.equal(await visitor.locator('[data-design-area="footer_before"] a').last().getAttribute('href'), '/posts');
        await headerAfterWidgets();
        await links.locator('[data-action="remove"][data-index="' + widgetCount + '"]').click();
        await page.locator('[data-pane="footer"]').click();
        const footerWidgets = page.locator('[data-chrome-details="footer_before:widgets"]');
        await footerWidgets.locator('> summary').press('Enter');
        const movedIndex = stored.themes[theme].state.layout.widgets.footer_before.findIndex(item => item.title === '移動するウィジェット');
        await footerWidgets.locator('[data-action="remove"][data-index="' + movedIndex + '"]').click();
        const [removed] = await Promise.all([page.waitForResponse(base + '/admin/design/publish'),
            page.locator('[data-action="publish"]').click()]);
        assert.equal(removed.status(), 200);
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
        await page.reload();
        stored = await catalog();
        assert.deepEqual(stored.themes[theme].state.menus, original.themes[theme].state.menus);
        assert.equal(stored.themes[theme].state.layout.widgets.header_after.length, widgetCount);
        assert(!stored.themes[theme].state.layout.widgets.footer_before.some(item => item.title === '移動するウィジェット'));
        console.log('PASS: widget addition/editing/type change/movement/deletion and direct post links persist after publishing');

        for (const id of ['default', 'monoedge']) {
            await request.post(base + '/admin/themes/update', {form: {_csrf_token: original.token, theme: id}});
            for (const side of ['right', 'left']) {
                for (const width of ['standard', 'wide', 'full']) {
                    await save(id, state => {
                        state.layout.columns = side;
                        state.layout.width = width;
                        state.layout.widgets.sidebar = widgets;
                    });
                    for (const url of ['/page/legacy', '/legacy-post']) {
                        await visitor.setViewportSize({width: 1600, height: 1000});
                        await visitor.goto(base + url);
                        const geometry = await visitor.evaluate(() => {
                            const grid = document.querySelector('.nx-design-main');
                            const main = grid.querySelector(':scope > main').getBoundingClientRect();
                            const aside = grid.querySelector(':scope > aside').getBoundingClientRect();
                            const section = grid.querySelector('[data-layout-section]').getBoundingClientRect();
                            const inner = grid.querySelector('.nx-page-section-inner').getBoundingClientRect();
                            return {width: grid.getBoundingClientRect().width, main: {x: main.x, right: main.right, y: main.y},
                                aside: {x: aside.x, right: aside.right, y: aside.y}, ratio: inner.width / section.width,
                                color: getComputedStyle(grid.querySelector('[data-layout-section]'), '::before').backgroundColor,
                                overflow: document.documentElement.scrollWidth > innerWidth + 1};
                        });
                        assert.equal(geometry.width, width === 'standard' ? 1080 : width === 'wide' ? 1440 : 1600);
                        assert.equal(geometry.overflow, false, JSON.stringify(geometry));
                        assert(Math.abs(geometry.main.y - geometry.aside.y) < 1);
                        assert(side === 'left' ? geometry.aside.right < geometry.main.x : geometry.main.right < geometry.aside.x);
                        assert(Math.abs(geometry.ratio - .8) < .01);
                        assert.equal(geometry.color, 'rgb(44, 35, 61)');
                        assert.equal(await visitor.locator('main').count(), 1);
                        assert((await visitor.locator('aside').textContent()).includes('サイドバーの本文'));
                        assert.equal(await visitor.locator('aside a').count(), 3);
                        if (width === 'wide' && url === '/page/legacy') {
                            await visitor.screenshot({path: path.join(output, id + '-' + side + '-sidebar.png'), fullPage: true});
                            for (const size of [390, 720, 721]) {
                                await visitor.setViewportSize({width: size, height: 844});
                                assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
                                if (size <= 720) assert(await visitor.evaluate(() =>
                                    document.querySelector('aside').getBoundingClientRect().top >= document.querySelector('main').getBoundingClientRect().bottom));
                            }
                            await visitor.setViewportSize({width: 390, height: 844});
                            await visitor.screenshot({path: path.join(output, id + '-' + side + '-sidebar-mobile.png'), fullPage: true});
                        }
                    }
                }
            }
            await save(id, state => { state.layout.columns = 'none'; });
            await visitor.goto(base + '/page/legacy');
            assert.equal(await visitor.locator('aside.nx-design-sidebar').count(), 0);
            assert.equal(await visitor.locator('.site-main.nx-section-page').count(), 1);
            await save(id, state => { state.layout.columns = 'right'; state.layout.widgets.sidebar = []; });
            await visitor.goto(base + '/page/legacy');
            assert.equal(await visitor.locator('aside.nx-design-sidebar').count(), 0);
            await page.goto(base + '/admin/design');
            await page.locator('[data-pane="columns"]').click();
            await target.selectOption('page:' + fixed.id);
            await waitPreview();
            assert.equal(await page.frameLocator('#nx-design-frame').locator('aside .nx-design-empty').count(), 1);
            await save(id, state => { state.layout.widgets.sidebar = widgets; });
            for (const url of ['/page/design-audit-local', '/posts']) {
                await visitor.goto(base + url);
                assert.equal(await visitor.locator('.nx-design-main > main .nx-page-section').count(), 0);
                assert.equal(await visitor.locator('aside.nx-design-sidebar').count(), 1);
            }
        }
        console.log('PASS: both themes, page/post sections, left/right/hidden/empty sidebars, three widths, responsive stacking and live preview');
    } catch (error) {
        console.log('Design UI:', await page.locator('#nx-design-error').textContent(),
            await page.locator('[data-preview-width]').allTextContents());
        await page.screenshot({path: path.join(output, 'design-audit-failure.png'), fullPage: true});
        throw error;
    } finally {
        await guest.close();
        for (const id of ['default', 'monoedge']) await save(id, state => {
            state.layout = original.themes[id].state.layout;
            state.contentLayouts = original.themes[id].state.contentLayouts;
        }, id === 'default' ? [['page', fixed.id, savedPage.overrides], ['post', post.id, savedPost.overrides]] : []);
        await request.post(base + '/admin/pages/delete/' + local.id, {form: {_csrf_token: original.token}});
        await request.post(base + '/admin/themes/update', {form: {_csrf_token: original.token, theme}});
    }
};
