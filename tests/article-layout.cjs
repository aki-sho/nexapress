const assert = require('node:assert/strict');

module.exports = async (page, base) => {
    await page.setViewportSize({width: 1500, height: 1000});
    await page.goto(base + '/admin/pages/create');
    const form = page.locator('form[data-editor-kind]');
    const field = name => form.locator('[name="' + name + '"]');
    const editor = form.locator('.classic-editor');
    const mode = name => editor.locator('[data-editor-mode="' + name + '"]').click();
    const visual = editor.locator('.classic-editor-content');
    const editorFrame = editor.frameLocator('iframe');
    const firstCover = '.nx-article-cover:first-child';
    const csrf = await field('_csrf_token').inputValue();
    const png = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 160;
        canvas.height = 96;
        const context = canvas.getContext('2d');
        context.fillStyle = '#e2b771';
        context.fillRect(24, 0, 112, 96);
        return canvas.toDataURL().split(',')[1];
    });
    const media = await page.request.post(base + '/admin/media/upload-image', {multipart: {
        _csrf_token: csrf,
        media_file: {name: 'cover-cutout.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64')},
    }});
    assert.equal(media.status(), 201, await media.text());
    const image = (await media.json()).image.url;
    const cover = (body, attributes = '') =>
        '<div class="nx-article-cover" ' + attributes + '><div class="nx-article-cover-content">' + body + '</div></div>';
    const initial = cover('<h2>飛び出し画像のあるカバー</h2><figure class="nx-article-image"><img src="' + image + '" alt="透過画像"></figure>') +
        cover('<p>高さを変更する2つ目のカバー</p>') +
        cover('<p>読むためにスクロールする行</p>'.repeat(20), 'data-nx-cover-height="120"');

    await field('title').fill('カバーと記事枠の確認');
    await field('slug').fill('cover-layout-check');
    await mode('html');
    await field('content').fill(initial);
    await mode('visual');
    await visual.locator('img').click();
    await editor.locator('[data-cover-pop-direction]').selectOption('-1');
    await editor.locator('[data-cover-pop-pixels]').fill('48');
    await editor.locator('[data-cover-group]').click();
    await editor.locator('[data-cover-setting="width"]').selectOption('full');
    await editor.locator('[data-cover-setting="height"]').selectOption('fixed');
    await editor.locator('[data-cover-fixed-pixels]').fill('600');
    await visual.locator(firstCover + '[data-nx-cover-projecting]').waitFor();
    await visual.locator('.nx-article-cover').nth(1).click({position: {x: 10, y: 10}});
    await editor.locator('[data-cover-setting="height"]').selectOption('auto');

    const assertProjection = async (root, side, distance) => {
        const selected = root.locator(firstCover + '[data-nx-cover-projecting]');
        await selected.waitFor();
        let actual;
        for (let attempt = 0; attempt < 10; attempt++) {
            actual = await selected.evaluate((node, side) => {
                const frame = node.getBoundingClientRect();
                const style = node.ownerDocument.defaultView.getComputedStyle(node);
                const top = frame.top + parseFloat(style.borderTopWidth);
                const bottom = frame.bottom - parseFloat(style.borderBottomWidth);
                const foreground = node.querySelector('img').getBoundingClientRect();
                return {height: bottom - top, distance: side === 'top' ? top - foreground.top : foreground.bottom - bottom};
            }, side);
            if (Math.abs(actual.distance - distance) < 1 && Math.abs(actual.height - 600) < 1) break;
            await new Promise(resolve => setTimeout(resolve, 25));
        }
        assert(Math.abs(actual.height - 600) < 1, JSON.stringify(actual));
        assert(Math.abs(actual.distance - distance) < 1, JSON.stringify(actual));
    };
    await assertProjection(visual, 'top', 48);
    await mode('html');
    const html = await field('content').inputValue();
    for (const value of ['data-nx-cover-height="600"', 'data-nx-cover-height="auto"', 'data-nx-cover-pop="-48"']) {
        assert(html.includes(value));
    }
    assert(!html.includes('data-nx-cover-projecting') && !html.includes('--nx-cover-image-shift'));

    await mode('preview');
    const previewBody = editorFrame.locator('[data-nx-editor-field="body"]');
    await assertProjection(previewBody, 'top', 48);
    await previewBody.locator('img').click();
    await editor.locator('[data-cover-pop-direction]').selectOption('1');
    await editor.locator('[data-cover-pop-pixels]').fill('36');
    await previewBody.locator(firstCover + ' img[data-nx-cover-pop="36"]').waitFor();
    await assertProjection(previewBody, 'bottom', 36);
    await field('status').selectOption('published');
    await Promise.all([page.waitForURL(/\/admin\/pages\/edit\/\d+$/), form.getByRole('button', {name: '保存', exact: true}).click()]);
    const pageId = page.url().split('/').pop();
    await page.reload();
    assert.match(await field('content').inputValue(), /data-nx-cover-pop="36"/);

    await page.goto(base + '/admin/posts/create');
    await field('title').fill('カバー付き投稿の確認');
    await field('slug').fill('cover-layout-post-check');
    await mode('html');
    await field('content').fill(html);
    await mode('preview');
    await assertProjection(previewBody, 'top', 48);
    await field('status').selectOption('published');
    await Promise.all([
        page.waitForURL(base + '/admin/posts'),
        form.getByRole('button', {name: '投稿を保存', exact: true}).click(),
    ]);
    console.log('PASS: separate cover heights and image projection in visual, HTML, and theme preview');

    const articlePath = base + '/page/cover-layout-check';
    const guest = await page.context().browser().newContext();
    try {
        await guest.route(articlePath, async route => {
            const response = await route.fetch();
            await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
        });
        const visitor = await guest.newPage();
        await visitor.goto(articlePath);
        await assertProjection(visitor.locator('.post-content'), 'bottom', 36);
        assert(await visitor.locator('[data-nx-cover-height="auto"]').evaluate(node => node.offsetHeight < 240));
        assert(await visitor.locator('[data-nx-cover-height="120"] > .nx-article-cover-content').evaluate(node =>
            node.scrollHeight > node.clientHeight && getComputedStyle(node).overflowY === 'auto'));

        await page.goto(base + '/admin/design');
        await page.getByRole('button', {name: 'ページ毎のレイアウト', exact: true}).click();
        const scope = name => page.locator('[data-layout-scope="' + name + '"]');
        const design = page.frameLocator('#nx-design-frame');
        async function openPadding(name, targetName, target) {
            await scope(name).locator('summary').first().click();
            await scope(name).locator('[data-page-field="' + targetName + '"]').selectOption(target);
            const padding = scope(name).locator('[data-page-details="article-padding"]');
            await padding.waitFor();
            if (!await padding.evaluate(node => node.open)) {
                await padding.locator('summary').click();
            }
            return padding;
        }
        async function setPadding(padding, values) {
            for (const [side, amount] of Object.entries(values)) {
                await padding.locator('[data-article-padding="' + side + '"]').fill(String(amount));
            }
        }
        async function checkPadding(target, expected) {
            await target.waitFor();
            return target.evaluate(node => {
                const style = node.ownerDocument.defaultView.getComputedStyle(node);
                return [style.paddingTop, style.paddingRight, style.paddingBottom, style.paddingLeft];
            }).then(actual => assert.deepEqual(actual, expected.map(px => px + 'px')));
        }
        async function publish() {
            const [response] = await Promise.all([
                page.waitForResponse(base + '/admin/design/publish'),
                page.getByRole('button', {name: '公開', exact: true}).click(),
            ]);
            assert.equal(response.status(), 200, await response.text());
        }
        const common = await openPadding('all', 'previewTarget', 'page:' + pageId);
        await setPadding(common, {top: 28, right: 24, bottom: 36, left: 20});
        const item = await openPadding('page_item', 'page', pageId);
        await setPadding(item, {bottom: 0, left: 12});
        const post = await page.request.get(base + '/cover-layout-post-check');
        assert.equal(post.status(), 200, await post.text());
        const postPath = base + '/cover-layout-post-check';
        const postVisitor = await guest.newPage();
        await postVisitor.goto(postPath);
        await assertProjection(postVisitor.locator('.post-content'), 'top', 48);
        await publish();
        await postVisitor.reload();
        await checkPadding(postVisitor.locator('article.post-detail'), [28, 24, 36, 20]);
        await postVisitor.close();
        await visitor.reload();
        await checkPadding(visitor.locator('article.post-detail'), [28, 24, 0, 12]);

        await scope('page_item').locator('[data-page-field="enabled"]').check();
        await page.waitForFunction(() => {
            const article = document.querySelector('#nx-design-frame').contentDocument.querySelector('article.nx-page-article');
            return article?.ownerDocument.defaultView.getComputedStyle(article).paddingLeft === '12px';
        });
        await checkPadding(design.locator('article.nx-page-article'), [28, 24, 0, 12]);
        await publish();
        await visitor.setViewportSize({width: 390, height: 844});
        await visitor.reload();
        await checkPadding(visitor.locator('article.nx-page-article'), [28, 24, 0, 12]);
        await assertProjection(visitor.locator('.post-content'), 'bottom', 36);
        assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
    } finally {
        await guest.close();
    }
    console.log('PASS: individual article padding overrides common values per side in normal and section layouts');
};
