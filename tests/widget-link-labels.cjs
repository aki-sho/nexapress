const assert = require('assert/strict');
const path = require('path');
const selectWidgetLocation = require('./widget-location.cjs');

module.exports = async function checkWidgetLabels(page, base, output) {
    async function catalog() {
        const response = await page.request.get(base + '/admin/design');
        assert.equal(response.status(), 200);
        return JSON.parse((await response.text()).match(/id="nx-design-data">(.*?)<\/script>/s)[1]);
    }
    async function save(theme, mutate) {
        const data = await catalog();
        const state = structuredClone(data.themes[theme].state);
        mutate(state);
        const response = await page.request.post(base + '/admin/design/publish', {form: {
            _csrf_token: data.token, revision: state.revision, design: JSON.stringify(state),
        }});
        assert.equal(response.status(), 200, await response.text());
    }
    const initial = await catalog();
    const longTitle = '長いリンク先の名前 & "引用" ' + 'あ'.repeat(100);
    const draftTitle = '下書きのリンク先 & "確認"';
    for (const [title, slug, status] of [[longTitle, 'widget-label-long', 'published'], [draftTitle, 'widget-label-draft', 'draft']]) {
        const response = await page.request.post(base + '/admin/pages/store', {form: {
            _csrf_token: initial.token, title, slug, status, content: '<p>表示名の検証</p>',
        }});
        assert.equal(response.status(), 200, await response.text());
    }
    const choices = (await catalog()).targets;
    const longPage = choices.page.find(item => item.name === longTitle);
    const draftPage = choices.page.find(item => item.name === draftTitle);
    const fixed = choices.page.find(item => item.name === '既存ページ');
    const post = choices.post.find(item => item.name === '既存投稿');
    const anotherPost = choices.post.find(item => item.id !== post.id && !item.label.endsWith('（非公開）'));
    assert(longPage && draftPage && fixed && post && anotherPost);
    const customTitle = '自分で入力した <b data-widget-title>表示名</b> & "引用"';
    const form = page.locator('[data-designer-form]');
    const title = form.locator('[name="title"]');
    const settled = () => page.waitForFunction(() => document.querySelector('#nx-design-stage').getAttribute('aria-busy') === 'false'
        && document.querySelector('#nx-design-frame').contentDocument.querySelector('.site-footer'));
    async function openWidget(area, index) {
        const pane = area === 'sidebar' ? 'columns' : area.startsWith('header') ? 'header' : 'footer';
        await page.locator('[data-pane="' + pane + '"]').click();
        const group = page.locator('[data-chrome-details="' + pane + ':widgets"]');
        if (!(await group.evaluate(element => element.open))) await group.locator('summary').press('Enter');
        await selectWidgetLocation(group, area);
        if (index === undefined) await group.locator('[data-action="add"]').click();
        else await group.locator('[data-action="edit"]').nth(index).click();
    }
    async function publish() {
        const [response] = await Promise.all([page.waitForResponse(base + '/admin/design/publish'),
            page.locator('[data-action="publish"]').click()]);
        assert.equal(response.status(), 200, await response.text());
        const result = await response.json();
        await page.locator('[data-action="publish"]:disabled').waitFor();
        return result;
    }
    const guest = await page.context().browser().newContext({viewport: {width: 1280, height: 900}, javaScriptEnabled: false});
    try {
        const visitor = await guest.newPage();
        for (const theme of ['default', 'monoedge']) {
            await save(theme, state => {
                state.layout.columns = 'right';
                state.layout.widgets.header_after = [{type: 'page', title: '', reference_id: longPage.id, url: '', text: '', menu: 'footer'}];
                state.layout.widgets.sidebar = [{type: 'post', title: '既存の独自表示名', reference_id: post.id, url: '', text: '', menu: 'footer'}];
                state.layout.widgets.footer_before = [];
            });
            await page.request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme}});
            await page.goto(base + '/admin/design');
            await settled();
            await openWidget('header_after', 0);
            assert.equal(await title.inputValue(), longTitle, 'older blank labels are shown with their destination name');
            await form.locator('[data-action="cancel"]').click();
            assert.equal((await catalog()).themes[theme].state.layout.widgets.header_after[0].title, '', 'cancel preserves the saved widget');
            await openWidget('header_after');
            await form.locator('[data-form-setting="type"]').selectOption('page');
            await form.locator('[name="reference_id"]').selectOption(String(fixed.id));
            assert.equal(await title.inputValue(), fixed.name);
            await form.locator('[name="reference_id"]').selectOption(String(longPage.id));
            assert.equal(await title.inputValue(), longTitle, 'long names and HTML characters are copied intact');
            assert.equal(await title.getAttribute('maxlength'), '255');
            await form.getByRole('button', {name: '追加', exact: true}).click();
            await settled();

            await openWidget('sidebar', 0);
            assert.equal(await title.inputValue(), '既存の独自表示名');
            await form.locator('[name="reference_id"]').selectOption(String(anotherPost.id));
            assert.equal(await title.inputValue(), anotherPost.name);
            await title.fill(customTitle);
            await page.locator('[data-setting="columns"]').selectOption('left');
            assert.equal(await title.inputValue(), customTitle, 'redrawing the editor preserves manual text');
            await form.getByRole('button', {name: '反映', exact: true}).click();
            await settled();

            await openWidget('footer_before');
            await form.locator('[data-form-setting="type"]').selectOption('page');
            await form.locator('[name="reference_id"]').selectOption(String(draftPage.id));
            assert.equal(await title.inputValue(), draftTitle, 'administrative status is excluded from the generated label');
            await form.locator('[name="reference_id"]').selectOption(String(fixed.id));
            assert.equal(await title.inputValue(), fixed.name);
            await form.getByRole('button', {name: '追加', exact: true}).click();
            await settled();
            const saved = await publish();
            assert.equal(saved.states[theme].layout.widgets.header_after[1].title, longTitle);
            assert.equal(saved.states[theme].layout.widgets.sidebar[0].title, customTitle);
            assert.equal(saved.states[theme].layout.widgets.footer_before[0].title, fixed.name);
            await page.reload();
            await settled();
            await openWidget('header_after', 1);
            assert.equal(await title.inputValue(), longTitle);
            await page.screenshot({path: path.join(output, 'widget-link-label-' + theme + '.png')});
            await form.locator('[data-action="cancel"]').click();

            await visitor.setViewportSize({width: 1280, height: 900});
            await visitor.goto(base + '/posts');
            assert((await visitor.locator('[data-design-area="header_after"] .nx-design-widget a').allTextContents()).includes(longTitle));
            assert.equal(await visitor.locator('aside .nx-design-widget a').textContent(), customTitle);
            assert.equal(await visitor.locator('[data-design-area="footer_before"] .nx-design-widget a').textContent(), fixed.name);
            assert.equal(await visitor.locator('[data-widget-title]').count(), 0, 'labels are rendered as escaped text');
            await visitor.setViewportSize({width: 390, height: 844});
            assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            const current = await catalog();
            const invalid = structuredClone(current.themes[theme].state);
            invalid.layout.widgets.header_after[1].title = 'あ'.repeat(256);
            const rejected = await page.request.post(base + '/admin/design/publish', {form: {
                _csrf_token: current.token, revision: invalid.revision, design: JSON.stringify(invalid),
            }});
            assert.equal(rejected.status(), 422);
            assert.equal((await catalog()).themes[theme].state.layout.widgets.header_after[1].title, longTitle);
            console.log('PASS: ' + theme + ' widget label autofill, existing/custom names, target changes, full titles, publish/reload and escaped public/mobile rendering');
        }
    } finally {
        await guest.close();
        for (const theme of ['default', 'monoedge']) await save(theme, state => {state.layout = initial.themes[theme].state.layout;});
        await page.request.post(base + '/admin/themes/update', {form: {_csrf_token: initial.token, theme: initial.active}});
    }
};
