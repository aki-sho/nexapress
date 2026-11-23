const assert = require('assert/strict');
const path = require('path');

module.exports = async (page, base, output) => {
    const browser = page.context().browser();
    const editor = page.locator('.classic-editor');
    const frame = editor.frameLocator('iframe');
    const body = frame.locator('[data-nx-editor-field="body"][contenteditable="true"]');
    const previewTitle = frame.locator('[data-nx-editor-field="title"][contenteditable]');
    const mode = name => editor.locator('[data-editor-mode="' + name + '"]').click();
    const field = name => page.locator('form[data-editor-kind] [name="' + name + '"]');
    async function openBlock(kind) {
        if (await editor.locator('[data-block-menu]').isHidden()) await editor.locator('[data-block-toggle]').click();
        await editor.locator('[data-insert-block="' + kind + '"]').click();
    }
    async function openPreview() {
        await mode('preview');
        await body.waitFor();
        assert.equal(await editor.locator('iframe').getAttribute('sandbox'), 'allow-same-origin');
        assert(await frame.locator('.site-header').isVisible());
        assert.equal(await frame.locator('.site-footer').count(), 1);
    }

    await page.goto(base + '/admin/pages/create');
    assert.equal((await page.request.post(base + '/admin/pages/editor-preview', {form: {title: '確認'}})).status(), 419);
    await field('slug').fill('editor-preview-page');
    await openPreview();
    await previewTitle.fill('プレビューから作成した固定ページ');
    await body.fill('枠内で編集した本文');
    assert.equal(await field('title').inputValue(), 'プレビューから作成した固定ページ');
    assert.match(await field('content').inputValue(), /枠内で編集した本文/);
    await openBlock('quote');
    await page.keyboard.insertText('並び替え確認');
    await openBlock('paragraph');
    await page.keyboard.insertText('削除する段落');
    await editor.getByRole('button', {name: 'ブロックを上へ移動'}).click();
    assert.equal(await body.locator(':scope > p').last().textContent(), '削除する段落');
    await editor.locator('[data-block-remove]').click();
    assert(!(await body.textContent()).includes('削除する段落'));
    for (const count of [2, 3, 4]) {
        const exitColumn = editor.locator('[data-block-group]');
        if (await exitColumn.isVisible()) await exitColumn.click();
        await openBlock('columns');
        await editor.locator('[data-block-count="' + count + '"]').click();
        const columns = body.locator('.nx-article-columns--' + count + ' > .nx-article-column');
        assert.equal(await columns.count(), count);
        for (let index = 0; index < count; index++) {
            await columns.nth(index).fill(count + '列の' + (index + 1) + '番目');
        }
    }
    const columns = body.locator('.nx-article-columns--4 > .nx-article-column');
    await columns.nth(1).click();
    await openBlock('heading');
    await page.keyboard.insertText('2列目の見出し');
    await editor.getByRole('combobox', {name: '見出しのレベル'}).selectOption('h4');
    assert.equal(await columns.nth(1).locator('h4').textContent(), '2列目の見出し');
    await openBlock('image');
    const dialog = page.locator('.classic-editor-media-dialog');
    const name = 'editor-block-image.png';
    const png = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 180;
        canvas.getContext('2d').fillRect(0, 0, 320, 180);
        return canvas.toDataURL().split(',')[1];
    });
    await dialog.locator('[data-media-file]').setInputFiles({name, mimeType: 'image/png', buffer: Buffer.from(png, 'base64')});
    const [upload] = await Promise.all([
        page.waitForResponse(base + '/admin/media/upload-image'),
        dialog.getByRole('button', {name: '画像を登録'}).click(),
    ]);
    assert.equal(upload.status(), 201, await upload.text());
    await columns.nth(1).locator('figure img').waitFor();
    await editor.locator('[data-block-alt]').fill('2列目の画像');
    assert.equal(await columns.nth(1).locator('img').getAttribute('alt'), '2列目の画像');
    await mode('html');
    const source = await field('content').inputValue();
    assert.match(source, /class="nx-article-columns nx-article-columns--4"/);
    assert.match(source, /<h4>2列目の見出し<\/h4>/);
    assert.match(source, /alt="2列目の画像"/);
    assert(!source.includes('contenteditable') && !source.includes('data-nx-block-active'));
    await field('content').fill(source + '<blockquote>HTMLで追記した引用</blockquote>');
    await mode('visual');
    const visual = editor.locator('.classic-editor-content');
    assert.equal(await visual.locator('blockquote').last().textContent(), 'HTMLで追記した引用');
    await visual.locator('.nx-article-columns--4 > .nx-article-column').last().fill('ビジュアルで直した4列目');
    await openPreview();
    assert.match(await columns.last().textContent(), /ビジュアルで直した4列目/);
    await editor.locator('[data-preview-device="mobile"]').click();
    const boxes = await columns.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().x));
    assert(boxes.every(x => Math.abs(x - boxes[0]) < 1));
    await editor.locator('[data-preview-device="desktop"]').click();
    await page.screenshot({path: path.join(output, 'inline-editor-preview.png')});
    await field('status').selectOption('published');
    assert.equal((await page.request.get(base + '/page/editor-preview-page')).status(), 404);
    await Promise.all([
        page.waitForURL(/\/admin\/pages\/edit\/\d+$/),
        page.locator('form[data-editor-kind]').locator('button[type="submit"]:not([data-editor-save])').click(),
    ]);
    await openPreview();
    assert.equal(await previewTitle.textContent(), 'プレビューから作成した固定ページ');
    assert.equal(await body.locator('figure img[alt="2列目の画像"]').count(), 1);

    await page.goto(base + '/admin/posts/create');
    await field('slug').fill('editor-preview-post');
    await openPreview();
    await previewTitle.fill('プレビューから作成した投稿');
    await body.fill('投稿本文をその場で編集');
    await field('status').selectOption('published');
    await Promise.all([
        page.waitForURL(base + '/admin/posts'),
        page.getByRole('button', {name: '投稿を保存', exact: true}).click(),
    ]);

    const guest = await browser.newContext({viewport: {width: 1440, height: 900}, javaScriptEnabled: false});
    try {
        const visitor = await guest.newPage();
        await visitor.goto(base + '/page/editor-preview-page');
        const publishedColumns = visitor.locator('.nx-article-columns--4 > .nx-article-column');
        assert.equal(await publishedColumns.count(), 4);
        assert.equal(await visitor.locator('img[alt="2列目の画像"]').evaluate(image => image.naturalWidth), 320);
        const desktop = await publishedColumns.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().x));
        assert(desktop.every((x, index) => index === 0 || x > desktop[index - 1]));
        await visitor.setViewportSize({width: 390, height: 844});
        const mobile = await publishedColumns.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().x));
        assert(mobile.every(x => Math.abs(x - mobile[0]) < 1));
        const post = await guest.request.get(base + '/editor-preview-post');
        assert.equal(post.status(), 200);
        assert.match(await post.text(), /投稿本文をその場で編集/);
    } finally {
        await guest.close();
    }
    console.log('PASS: direct theme preview editing for pages and posts, shared HTML, headings, 2–4 columns, image upload and published responsive layout');
};
