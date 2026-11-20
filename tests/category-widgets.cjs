const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkCategoryWidgets(page, base, output) {
    async function catalog() {
        const response = await page.request.get(base + '/admin/design');
        assert.equal(response.status(), 200);
        return JSON.parse((await response.text()).match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
    }
    async function save(theme, mutate) {
        const data = await catalog();
        const state = structuredClone(data.themes[theme].state);
        mutate(state);
        const response = await page.request.post(base + '/admin/design/publish', {
            form: {_csrf_token: data.token, design: JSON.stringify(state), revision: state.revision},
        });
        assert.equal(response.status(), 200, await response.text());
    }
    async function publish() {
        const [response] = await Promise.all([page.waitForResponse(base + '/admin/design/publish'),
            page.locator('[data-action="publish"]').click()]);
        assert.equal(response.status(), 200, await response.text());
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
    }
    async function openWidget(area, index = 0) {
        const kind = area === 'sidebar' ? 'columns' : 'header';
        await page.locator('[data-pane="' + kind + '"]').click();
        const details = page.locator('[data-chrome-details="' + kind + ':widgets"]');
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').click();
        if (kind === 'header') await details.locator('[data-setting="area"]').selectOption(area);
        await details.locator('[data-action="edit"][data-index="' + index + '"]').click();
    }
    const initial = await catalog();
    const theme = initial.active;
    const created = [];
    for (const [name, slug] of [['クルマ', 'widget-cars'], ['旅行 & 日記', 'widget-travel'], ['未選択カテゴリ', 'widget-omitted']]) {
        const response = await page.request.post(base + '/admin/categories/inline', {
            form: {_csrf_token: initial.token, name, slug},
        });
        assert.equal(response.status(), 201, await response.text());
        created.push((await response.json()).category);
    }
    const [first, second, omitted] = created;
    const single = {type: 'category', title: '旧カテゴリ表示', reference_id: first.id, url: '', text: '', menu: 'footer'};
    const multiple = {...single, title: '選んだカテゴリ', reference_ids: [first.id, second.id]};
    const guest = await page.context().browser().newContext({viewport: {width: 1280, height: 900}, javaScriptEnabled: false});
    await guest.route(base + '/**', async route => {
        if (route.request().resourceType() !== 'document') return route.continue();
        const response = await route.fetch();
        await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
    });
    const visitor = await guest.newPage();
    const form = page.locator('[data-designer-form]');
    const selected = id => form.locator('input[name="reference_ids"][value="' + id + '"]');
    const apply = () => form.getByRole('button', {name: '反映', exact: true}).click();
    try {
        await save(theme, state => { state.layout.columns = 'right'; state.layout.widgets.sidebar = [single]; });
        await visitor.goto(base + '/posts');
        assert.equal(await visitor.locator('aside .nx-design-widget a').textContent(), '旧カテゴリ表示');
        await page.goto(base + '/admin/design');
        await openWidget('sidebar');
        assert.equal(await selected(first.id).isChecked(), true);
        assert.equal(await selected(second.id).isChecked(), false);
        assert.equal(await form.locator('[name="reference_id"]').count(), 0);
        await selected(second.id).check();
        assert.equal(await form.locator('[data-category-count]').textContent(), '2件');
        assert.equal(await form.locator('[data-category-title-label]').textContent(), '見出し（任意）');
        await form.locator('[name="title"]').fill('選んだカテゴリ');
        const selectionOrder = await form.locator('[name="reference_ids"]:checked').evaluateAll(inputs => inputs.map(input => Number(input.value)));
        await apply();
        await page.waitForFunction(() => {
            const group = document.querySelector('#nx-design-frame').contentDocument.querySelector('aside .nx-design-widget-categories');
            return group && group.querySelectorAll('li').length === 2 && group.textContent.includes('旅行 & 日記');
        });
        await openWidget('sidebar');
        await page.screenshot({path: path.join(output, 'widget-category-multiple-settings.png')});
        const hitbox = await selected(first.id).evaluate(input => ({width: input.getBoundingClientRect().width,
            label: input.closest('label').getBoundingClientRect().height}));
        assert.equal(hitbox.width, 22);
        assert(hitbox.label >= 34);
        await page.locator('[data-action="cancel"]').click();
        await publish();
        await page.reload();
        await openWidget('sidebar');
        assert.equal(await selected(first.id).isChecked(), true);
        assert.equal(await selected(second.id).isChecked(), true);
        assert.equal(await selected(omitted.id).isChecked(), false);
        await selected(first.id).uncheck();
        await selected(second.id).uncheck();
        await apply();
        assert((await page.locator('#nx-design-error').textContent()).includes('カテゴリは1〜100件'));
        assert.equal(await form.isVisible(), true);
        assert.equal(await form.locator('[name="title"]').inputValue(), '選んだカテゴリ');
        await selected(first.id).check();
        await selected(second.id).check();
        await form.locator('[name="area"]').selectOption('header_after');
        await apply();
        await publish();
        await page.reload();
        const moved = (await catalog()).themes[theme].state.layout;
        assert.equal(moved.widgets.sidebar.length, 0);
        const movedIndex = moved.widgets.header_after.findIndex(item => item.title === '選んだカテゴリ');
        assert(movedIndex >= 0);
        assert.deepEqual(moved.widgets.header_after[movedIndex].reference_ids, selectionOrder);
        await openWidget('header_after', movedIndex);
        await selected(second.id).uncheck();
        assert.equal(await form.locator('[data-category-title-label]').textContent(), '表示名（省略するとカテゴリ名）');
        await form.locator('[name="title"]').fill('クルマ表示名');
        await apply();
        await publish();
        await visitor.goto(base + '/posts');
        assert.equal(await visitor.getByRole('link', {name: 'クルマ表示名', exact: true}).count(), 1);

        await page.locator('[data-chrome-details="header:widgets"] [data-action="add"]').click();
        await form.locator('[data-form-setting="type"]').selectOption('category');
        assert.equal(await form.locator('[name="reference_ids"]:checked').count(), 0);
        await selected(first.id).check();
        await selected(second.id).focus();
        await selected(second.id).press('Space');
        await form.getByRole('button', {name: '追加', exact: true}).click();
        assert.equal(await page.getByText('カテゴリ（2件）', {exact: true}).count(), 1);
        await publish();
        await visitor.goto(base + '/posts');
        assert.equal(await visitor.locator('[data-design-area="header_after"] .nx-design-widget-categories li').count(), 2);
        assert.equal(await visitor.locator('[data-design-area="header_after"] .nx-design-widget-categories h2').count(), 0);
        await page.locator('[data-pane="header"]').click();
        const headerLinks = page.locator('[data-chrome-details="header:links"]');
        if (!(await headerLinks.evaluate(element => element.open))) await headerLinks.locator('> summary').click();
        await headerLinks.locator('[data-action="add"]').click();
        await form.locator('[data-form-setting="type"]').selectOption('category');
        assert.equal(await form.locator('[name="reference_id"]').count(), 1);
        assert.equal(await form.locator('[name="reference_ids"]').count(), 0);
        await page.locator('[data-action="cancel"]').click();

        for (const id of ['default', 'monoedge']) {
            await save(id, state => {
                state.layout.columns = 'right'; state.layout.widgets.sidebar = [multiple];
                state.layout.widgets.footer_before = [{type: 'categories', title: '全カテゴリ確認', text: '', menu: 'footer'}];
            });
            await page.request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme: id}});
            await visitor.goto(base + '/posts');
            const group = visitor.locator('aside .nx-design-widget-categories');
            assert.deepEqual(await group.locator('li a').allTextContents(), [first.name, second.name]);
            assert(!(await group.textContent()).includes(omitted.name));
            assert((await visitor.locator('[data-design-area="footer_before"]').textContent()).includes(omitted.name));
            await visitor.setViewportSize({width: 390, height: 844});
            assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            await visitor.screenshot({path: path.join(output, id + '-category-widget-mobile.png'), fullPage: true});
        }
        const current = await catalog();
        const state = current.themes[theme].state;
        for (const ids of [[], 'invalid', [first.id, first.id], [String(first.id)], [0], {a: first.id}, Array.from({length: 101}, (_, i) => i + 1)]) {
            const invalid = structuredClone(state);
            invalid.layout.widgets.sidebar[0].reference_ids = ids;
            for (const endpoint of ['preview', 'publish']) {
                const response = await page.request.post(base + '/admin/design/' + endpoint, {form: {
                    _csrf_token: current.token, design: JSON.stringify(invalid), revision: state.revision,
                }});
                assert.equal(response.status(), 422, await response.text());
            }
        }
        assert.deepEqual((await catalog()).themes[theme].state.layout.widgets.sidebar[0].reference_ids, [first.id, second.id]);
        await save('monoedge', state => { state.layout.widgets.sidebar = [
            {...multiple, reference_ids: [first.id, 99999998]},
            {...multiple, title: '削除済みのみ', reference_ids: [99999998, 99999999]},
        ]; });
        await visitor.goto(base + '/posts');
        assert.deepEqual(await visitor.locator('aside .nx-design-widget-categories li a').allTextContents(), [first.name]);
        assert(!(await visitor.locator('aside').textContent()).includes('削除済みのみ'));
        console.log('PASS: multi-category widget selection, legacy label compatibility, preview, publish/reload, movement, empty-selection protection, both themes/mobile and invalid/deleted category handling');
    } finally {
        await guest.close();
        for (const id of ['default', 'monoedge']) await save(id, state => { state.layout = initial.themes[id].state.layout; });
        await page.request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme}});
    }
};
