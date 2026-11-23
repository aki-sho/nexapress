const assert = require('node:assert/strict');

const placements = ['先頭寄せ', '中央寄せ', '末尾寄せ', '両端揃え', '両側に配分', 'すべて等間隔'];
module.exports = async function verifyArticlePlacement(page, base) {
    await page.goto(base + '/admin/pages/create');
    const editor = page.locator('.classic-editor');
    const visual = editor.locator('.classic-editor-content');
    const source = editor.locator('textarea#content');
    const mode = name => editor.locator('[data-editor-mode="' + name + '"]').click();
    const field = name => editor.locator('[data-layout-field="' + name + '"]');
    const setNumber = async (input, value) => {
        await input.fill(String(value));
        await input.press('Tab');
    };
    const insert = async (paragraph, kind) => {
        await paragraph.click();
        await page.keyboard.press('/');
        await editor.locator('[data-insert-block="' + kind + '"]').click();
    };
    const initial = '<h2>配置のテスト</h2>' +
        '<div class="nx-article-cover"><div class="nx-article-cover-content">' +
        '<div class="nx-article-columns nx-article-columns--2"><div class="nx-article-column"><p>左の説明</p><p><br></p></div>' +
        '<div class="nx-article-column"><p>右の説明</p></div></div></div></div><h2>次の見出し</h2>';
    await mode('html');
    await source.fill(initial);
    await mode('visual');

    const heading = visual.getByRole('heading', {name: '配置のテスト'});
    await heading.click();
    await page.keyboard.press('End');
    assert(await heading.evaluate(node => node.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'Enter', bubbles: true, cancelable: true, isComposing: true,
    }))), 'the editor leaves Enter during IME composition available to the browser');
    assert.equal(await visual.locator('h2 + p').count(), 0);
    await page.keyboard.press('Enter');
    await page.keyboard.insertText('見出しの後');
    assert.equal(await visual.locator('h2 + p').first().textContent(), '見出しの後');
    const gap = visual.locator(':scope > .nx-article-cover + [data-nx-editor-gap]').first();
    await gap.click();
    await page.keyboard.insertText('ブロックの間');
    assert.equal(await visual.locator('.nx-article-cover + p').textContent(), 'ブロックの間');

    const outer = visual.locator('.nx-article-cover-content > .nx-article-columns');
    const outerLeft = outer.locator(':scope > .nx-article-column').first();
    await outerLeft.locator('p').first().click();
    await editor.locator('[data-block-group]').click();
    assert.deepEqual(await field('placement').locator('option').allTextContents(), placements);
    await setNumber(editor.locator('[data-column-width-index="0"]'), 45);
    await setNumber(editor.locator('[data-column-width-index="1"]'), 45);
    await field('placement').selectOption('space-around');
    await setNumber(field('gap'), 0);
    assert.equal(await outer.evaluate(node => getComputedStyle(node).justifyContent), 'space-around');
    const parts = await outer.evaluate(node => [...node.children].map(child => child.getBoundingClientRect().width / node.clientWidth));
    assert(parts.every(part => Math.abs(part - .45) < .02), JSON.stringify(parts));

    await insert(outerLeft.locator('p').last(), 'columns-2');
    const inner = outerLeft.locator(':scope > .nx-article-columns');
    const innerColumns = inner.locator(':scope > .nx-article-column');
    assert.equal(await innerColumns.count(), 2);
    await editor.locator('[data-block-group]').click();
    await field('placement').selectOption('space-between');
    assert.equal(await inner.getAttribute('data-nx-placement'), 'space-between');
    assert.equal(await outer.getAttribute('data-nx-placement'), 'space-around');

    await insert(innerColumns.first().locator('p').last(), 'button');
    const leftAnchor = innerColumns.first().locator('a.nx-article-button');
    const label = leftAnchor.locator('[data-nx-button-label]');
    await page.keyboard.insertText('製品一覧を見る');
    await page.keyboard.press('End');
    await page.keyboard.insertText(' ↗');
    assert.equal(await label.textContent(), '製品一覧を見る ↗');
    assert.equal(await leftAnchor.evaluate(node => node.parentElement.textContent), '製品一覧を見る ↗');
    await field('href').fill('/posts');
    await field('href').press('Tab');
    await field('buttonKind').selectOption('outline');
    await field('buttonIcon').selectOption('external');
    await field('buttonColor').fill('#1122aa');
    await setNumber(field('buttonRadius'), 12);
    await field('buttonEdge').selectOption('bottom');
    await field('placement').selectOption('flex-end');
    await setNumber(field('gap'), 8);
    await editor.locator('[data-layout-add-button]').click();
    await page.keyboard.insertText('別の案内');
    const anchors = innerColumns.first().locator('a.nx-article-button');
    assert.equal(await anchors.count(), 2);
    assert.equal(await anchors.nth(1).textContent(), '別の案内');
    assert.equal(await anchors.nth(0).getAttribute('href'), '/posts');
    assert.equal(await anchors.nth(0).getAttribute('data-nx-button-kind'), 'outline');
    assert.equal(await anchors.nth(0).evaluate(node => getComputedStyle(node).borderRadius), '12px');
    await insert(innerColumns.nth(1).locator('p').last(), 'button');
    await page.keyboard.insertText('右側のリンク');

    const form = page.locator('form[data-editor-kind="page"]');
    await form.locator('[name="title"]').fill('記事の配置');
    await form.locator('[name="slug"]').fill('article-placement-test');
    await mode('preview');
    const frame = editor.frameLocator('iframe');
    const preview = frame.locator('[data-nx-editor-field="body"]');
    await preview.locator('.nx-article-columns[data-nx-placement="space-around"]').waitFor();
    const cover = preview.locator('.nx-article-cover');
    await cover.locator('p', {hasText: '右の説明'}).click();
    await editor.locator('[data-cover-group]').click();
    const coverPosition = editor.locator('[data-cover-setting="placement"]');
    assert.deepEqual(await coverPosition.locator('option').allTextContents(), placements);
    await coverPosition.selectOption('space-evenly');
    assert.equal(await cover.getAttribute('data-nx-placement'), 'space-evenly');
    const title = frame.locator('[data-nx-editor-field="title"]');
    await title.press('End');
    await title.press('Enter');
    await page.keyboard.insertText('本文の先頭');
    assert.equal(await preview.locator(':scope > p:first-child').textContent(), '本文の先頭');

    await mode('html');
    const html = await source.inputValue();
    assert.match(html, /data-nx-column-width="45"/);
    assert.match(html, /data-nx-placement="space-around"/);
    assert.match(html, /data-nx-placement="space-between"/);
    assert.match(html, /data-nx-placement="space-evenly"/);
    assert.match(html, /data-nx-button-color="#1122aa"/);
    assert.match(html, /data-nx-buttons-edge="bottom"/);
    assert.doesNotMatch(html, /data-nx-editor-gap|contenteditable=|data-nx-button-label/);
    await form.locator('[name="status"]').selectOption('published');
    await form.getByRole('button', {name: '保存', exact: true}).click();
    await page.waitForURL(/\/admin\/pages\/edit\/\d+$/);

    const published = await page.context().browser().newPage({viewport: {width: 1440, height: 900}});
    try {
        await published.goto(base + '/page/article-placement-test');
        const publicOuter = published.locator('.nx-article-cover-content > .nx-article-columns');
        await publicOuter.waitFor();
        assert.equal(await publicOuter.evaluate(node => getComputedStyle(node).justifyContent), 'space-around');
        assert.equal(await publicOuter.locator('a').first().evaluate(node => getComputedStyle(node).color), 'rgb(17, 34, 170)');
        assert.equal(await published.locator('[data-nx-editor-gap]').count(), 0);
        await published.setViewportSize({width: 390, height: 844});
        assert.equal(await publicOuter.evaluate(node => getComputedStyle(node).flexDirection), 'column');
        assert.equal(await publicOuter.locator('.nx-article-columns').evaluate(node => getComputedStyle(node).flexDirection), 'column');
        assert(await published.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    } finally {
        await published.close();
    }
    await page.goto(base + '/admin/posts/create');
    const post = page.locator('form[data-editor-kind="post"]');
    await post.locator('[name="title"]').fill('投稿のボタン配置');
    await post.locator('[name="slug"]').fill('post-placement-test');
    await visual.click();
    await page.keyboard.press('/');
    await editor.locator('[data-insert-block="button"]').click();
    await page.keyboard.insertText('投稿のリンク');
    await field('href').fill('/page/legacy');
    await field('href').press('Tab');
    await field('buttonKind').selectOption('text');
    await post.locator('[name="status"]').selectOption('published');
    await post.getByRole('button', {name: '投稿を保存', exact: true}).click();
    await page.waitForURL(base + '/admin/posts');
    const publishedPost = await page.goto(base + '/post-placement-test');
    assert.equal(publishedPost.status(), 200);
    const postLink = page.locator('.post-content a.nx-article-button');
    assert.equal(await postLink.getAttribute('data-nx-button-kind'), 'text');
    assert.equal(await postLink.getAttribute('href'), '/page/legacy');
    assert.equal(await postLink.textContent(), '投稿のリンク');

    console.log('PASS article widths, shared placement, nested columns, inline button labels and styles, insertion, Enter, HTML round trip, mobile layout and publication for pages and posts');
};
