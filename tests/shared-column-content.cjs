const assert = require('node:assert/strict');

function verifyTextBox(text, fraction, placement) {
    return text.evaluate((element, {fraction, placement}) => {
        const container = element.parentElement;
        const style = getComputedStyle(container);
        const outer = container.getBoundingClientRect();
        const left = outer.left + parseFloat(style.borderLeftWidth) + parseFloat(style.paddingLeft);
        const right = outer.right - parseFloat(style.borderRightWidth) - parseFloat(style.paddingRight);
        const box = element.getBoundingClientRect();
        const width = right - left;
        const expected = placement === 'center' ? (width - box.width) / 2 : width - box.width;
        return Math.abs(box.width - width * fraction) < 2 && Math.abs(box.left - left - expected) < 2;
    }, {fraction, placement});
}

function columnGeometry(group) {
    return group.evaluate(node => {
        const parts = [...node.children];
        const box = element => {
            const {left, right, top, bottom, width} = element.getBoundingClientRect();
            return {left, right, top, bottom, width};
        };
        const from = area => parts.find(part => part.dataset.nxColumnArea === area);
        return {
            group: box(node),
            columns: parts.filter(part => part.matches('.nx-article-column')).map(box),
            above: from('above') ? box(from('above')) : null,
            below: from('below') ? box(from('below')) : null,
        };
    });
}

function verifySharedBounds(geometry) {
    const {columns, above, below} = geometry;
    for (const area of [above, below].filter(Boolean)) {
        assert(Math.abs(area.left - columns[0].left) < 1 && Math.abs(area.right - columns.at(-1).right) < 1,
            JSON.stringify(geometry));
    }
    if (above) assert(above.bottom <= columns[0].top + 1);
    if (below) assert(below.top >= columns.at(-1).bottom - 1);
}

const groups = count => '<div class="nx-article-columns nx-article-columns--' + count + '">' +
    Array.from({length: count}, (_, index) =>
        '<div class="nx-article-column"><p>商品' + (index + 1) + '</p></div>').join('') + '</div>';

module.exports = async function checkSharedColumnContent(page, base) {
    await page.goto(base + '/admin/pages/create');
    const editor = page.locator('.classic-editor');
    const visual = editor.locator('.classic-editor-content');
    const source = editor.locator('textarea#content');
    const mode = name => editor.locator('[data-editor-mode="' + name + '"]').click();
    const field = name => editor.locator('[data-layout-field="' + name + '"]');
    const number = async (locator, value) => {
        await locator.fill(String(value));
        await locator.press('Tab');
    };
    const selectGroup = async (block, locator = ':scope > .nx-article-column:first-of-type p') => {
        await block.locator(locator).first().click();
        await editor.locator('[data-block-group]').click();
    };
    const shared = (block, side) => block.locator(':scope > [data-nx-column-area="' + side + '"]');
    const createArea = side => editor.locator('[data-layout-column-area="' + side + '"]').click();
    const verifySingleOutside = async body => {
        assert(await verifyTextBox(body.locator('h2', {hasText: '独立した見出し'}), .65, 'center'));
        assert(await verifyTextBox(body.locator('p', {hasText: '独立した段落'}), .80, 'end'));
    };

    await mode('html');
    await source.fill('<h2>独立した見出し</h2><p>独立した段落</p>' + groups(2) + groups(3) + groups(4));
    await mode('visual');
    await visual.locator('h2', {hasText: '独立した見出し'}).click();
    await number(field('width'), 65);
    await field('placement').selectOption('center');
    await visual.locator('h2', {hasText: '独立した見出し'}).click();
    await editor.locator('select[data-command-select]').selectOption('justifyRight');
    assert.equal(await visual.locator('h2', {hasText: '独立した見出し'}).evaluate(node => getComputedStyle(node).textAlign), 'right');

    await visual.locator('p', {hasText: '独立した段落'}).click();
    await number(field('width'), 80);
    await field('placement').selectOption('flex-end');
    await verifySingleOutside(visual);

    const two = visual.locator('.nx-article-columns--2');
    await selectGroup(two);
    await number(field('width'), 90);
    await number(editor.locator('[data-column-width-index="0"]'), 45);
    await number(editor.locator('[data-column-width-index="1"]'), 35);
    await field('placement').selectOption('space-around');
    await createArea('above');
    await page.keyboard.press('/');
    await editor.locator('[data-insert-block="h2"]').click();
    await page.keyboard.insertText('製品ラインアップ');
    assert.equal(await shared(two, 'above').locator('h2').textContent(), '製品ラインアップ');

    await selectGroup(two, '[data-nx-column-area="above"] h2');
    await createArea('below');
    await page.keyboard.insertText('すべての製品について');
    assert.equal(await shared(two, 'below').locator('p').textContent(), 'すべての製品について');
    await page.keyboard.press('Enter');
    await page.keyboard.press('/');
    await editor.locator('[data-insert-block="image"]').click();
    await page.locator('dialog[open] [data-media-file]').setInputFiles({
        name: 'shared-column-image.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jDbkAAAAASUVORK5CYII=', 'base64'),
    });
    await page.locator('dialog[open] [data-media-submit]').click();
    await shared(two, 'below').locator('img').waitFor();
    verifySharedBounds(await columnGeometry(two));

    const three = visual.locator('.nx-article-columns--3');
    await selectGroup(three);
    await field('placement').selectOption('space-evenly');
    await number(field('width'), 80);
    await createArea('below');
    await page.keyboard.insertText('3列の共通案内');
    verifySharedBounds(await columnGeometry(three));

    const form = page.locator('form[data-editor-kind="page"]');
    await form.locator('[name="title"]').fill('共通欄と見出しの配置');
    await form.locator('[name="slug"]').fill('shared-column-layout-test');
    await mode('preview');
    const frame = editor.frameLocator('iframe');
    const preview = frame.locator('[data-nx-editor-field="body"]');
    const previewTwo = preview.locator('.nx-article-columns--2');
    await shared(previewTwo, 'above').getByRole('heading', {name: '製品ラインアップ'}).waitFor();
    verifySharedBounds(await columnGeometry(previewTwo));
    await verifySingleOutside(preview);
    const four = preview.locator('.nx-article-columns--4');
    await selectGroup(four);
    await field('placement').selectOption('center');
    await number(field('width'), 72);
    await createArea('above');
    await page.keyboard.insertText('4列の共通案内');
    assert.equal(await shared(four, 'above').locator('p').textContent(), '4列の共通案内');
    verifySharedBounds(await columnGeometry(four));

    await mode('html');
    const html = await source.inputValue();
    assert.match(html, /<h2[^>]+data-nx-layout-width="65"[^>]+data-nx-placement="center"/);
    assert.match(html, /data-nx-column-area="above"[^>]*>\s*<h2>製品ラインアップ<\/h2>/);
    assert.doesNotMatch(html, /data-nx-column-track|data-nx-editor-gap|contenteditable|上の共通欄/);
    await mode('visual');
    assert.equal(await visual.locator('[data-nx-column-area="below"] img').count(), 1);
    await form.locator('[name="status"]').selectOption('published');
    await form.locator('button[type="submit"]:not([data-editor-save])').click();
    await page.waitForURL(/\/admin\/pages\/edit\/\d+$/);
    assert.equal(await visual.locator('[data-nx-column-area="above"]').count(), 2);

    const visitor = await page.context().browser().newPage({viewport: {width: 1440, height: 960}, javaScriptEnabled: false});
    try {
        await visitor.goto(base + '/page/shared-column-layout-test');
        const article = visitor.locator('.post-content');
        await verifySingleOutside(article);
        const published = [2, 3, 4].map(count => article.locator('.nx-article-columns--' + count));
        for (const group of published) verifySharedBounds(await columnGeometry(group));
        const desktop = await columnGeometry(published[0]);
        assert(Math.abs(desktop.columns[0].width / desktop.columns[1].width - 45 / 35) < .01);
        assert.equal(await article.locator('[data-nx-column-area="below"] img').count(), 1);
        await visitor.setViewportSize({width: 390, height: 844});
        for (const group of published) {
            const geometry = await columnGeometry(group);
            verifySharedBounds(geometry);
            assert(geometry.columns.every(column => Math.abs(column.left - geometry.columns[0].left) < 1));
            assert(geometry.columns.at(-1).top >= geometry.columns[0].bottom);
        }
        assert(await visitor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    } finally {
        await visitor.close();
    }

    await page.goto(base + '/admin/posts/create');
    const post = page.locator('form[data-editor-kind="post"]');
    await post.locator('[name="title"]').fill('投稿の共通欄');
    await post.locator('[name="slug"]').fill('post-shared-columns-test');
    await mode('html');
    await source.fill('<p>投稿の本文</p>' + groups(2));
    await mode('visual');
    await visual.getByText('投稿の本文', {exact: true}).click();
    await number(field('width'), 70);
    await field('placement').selectOption('flex-end');
    await selectGroup(visual.locator('.nx-article-columns--2'));
    await createArea('above');
    await page.keyboard.insertText('投稿の共通案内');
    await post.locator('[name="status"]').selectOption('published');
    await post.getByRole('button', {name: '投稿を保存', exact: true}).click();
    await page.waitForURL(base + '/admin/posts');
    await page.goto(base + '/post-shared-columns-test');
    const publishedPost = page.locator('.post-content');
    assert(await verifyTextBox(publishedPost.getByText('投稿の本文', {exact: true}), .70, 'end'));
    assert.equal(await publishedPost.locator('[data-nx-column-area="above"]').textContent(), '投稿の共通案内');
    verifySharedBounds(await columnGeometry(publishedPost.locator('.nx-article-columns--2')));
    console.log('PASS shared areas and independent text placement through visual, HTML, preview and published mobile/desktop output on pages and posts');
};
