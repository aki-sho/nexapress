const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');

function mediaFolder(page, name) {
    return page.getByRole('navigation', {name: 'メディアのフォルダ'}).getByRole('link', {name, exact: true});
}

async function createMediaFolder(page, name) {
    await page.getByRole('button', {name: '新規フォルダ', exact: true}).click();
    const dialog = page.getByRole('dialog', {name: '新規フォルダ'});
    await dialog.getByLabel('フォルダ名').fill(name);
    await dialog.getByRole('button', {name: '作成', exact: true}).click();
    await page.getByRole('heading', {name, exact: true}).waitFor();
    return page.url();
}

async function checkInheritedLayouts(page, base, output) {
    const scope = id => page.locator('[data-layout-scope="' + id + '"]');
    const field = name => page.locator('[data-page-field="' + name + '"]');
    const color = page.locator('[data-page-details="colors"]');
    const layout = page.locator('[data-page-details="layout"]');
    async function openScope(id) {
        if (!(await scope(id).evaluate(details => details.open))) await scope(id).locator('> summary').click();
        await field('enabled').waitFor();
    }
    async function expand(details) {
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').click();
    }
    async function setColor(value) {
        await expand(color);
        await field('hex').fill(value);
        await field('hex').press('Tab');
    }
    async function selectDocument(kind, label) {
        await field(kind).selectOption({label});
        await field('enabled').waitFor();
    }
    async function waitPaint(rgb, width, columns) {
        await page.waitForFunction(([rgb, width, columns]) => {
            const frame = document.querySelector('#nx-design-frame');
            const section = frame.contentDocument.querySelector('[data-layout-section="0"]');
            if (!section) return false;
            const inner = section.querySelector('.nx-page-section-inner');
            return frame.contentWindow.getComputedStyle(section, '::before').backgroundColor === rgb &&
                Math.abs(inner.clientWidth / section.clientWidth - width) < .01 &&
                Number(section.dataset.columns) === columns;
        }, [rgb, width, columns]);
    }
    async function publish() {
        const [response] = await Promise.all([
            page.waitForResponse(base + '/admin/design/publish'),
            page.getByRole('button', {name: '公開', exact: true}).click(),
        ]);
        assert.equal(response.status(), 200, await response.text());
        await page.getByRole('button', {name: '公開', exact: true}).waitFor();
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
    }

    assert.equal(await page.locator('[data-layout-scope]').count(), 5);
    await openScope('all');
    const previewValue = await field('previewTarget').locator('option').filter({hasText: '固定ページ · 本文なし published'}).first().getAttribute('value');
    const snapshotUrl = base + '/admin/design/page-layout/' + previewValue.split(':')[1];
    let releaseSnapshot;
    const snapshotGate = new Promise(resolve => { releaseSnapshot = resolve; });
    await page.route(snapshotUrl, async route => {
        const response = await route.fetch();
        await snapshotGate;
        await route.fulfill({response});
    });
    await field('previewTarget').selectOption({label: '固定ページ · 本文なし published'});
    await field('enabled').check();
    await expand(color);
    await field('hex').fill('#abc');
    await Promise.all([
        page.waitForResponse(snapshotUrl),
        Promise.resolve().then(releaseSnapshot),
    ]);
    await page.waitForTimeout(200);
    assert.equal(await field('hex').inputValue(), '#abc');
    await field('hex').press('Tab');
    await page.unroute(snapshotUrl);
    await field('width').selectOption('full');
    await setColor('#CBB380');
    await expand(layout);
    await field('widthMode').selectOption('percent');
    await field('percent').fill('80');
    await field('percent').press('Tab');
    await field('columns').selectOption('2');
    await waitPaint('rgb(203, 179, 128)', .8, 2);

    await openScope('page');
    await setColor('#F6E6E6');
    await openScope('post');
    await field('previewTarget').selectOption({label: '投稿 · 既存投稿'});
    await setColor('#E5F0E7');
    await expand(layout);
    await field('percent').fill('65');
    await field('percent').press('Tab');
    await waitPaint('rgb(229, 240, 231)', .65, 2);

    await openScope('page_item');
    await selectDocument('page', '本文なし published');
    assert(await field('enabled').isChecked());
    await setColor('#112233');
    await waitPaint('rgb(17, 34, 51)', .8, 2);
    await openScope('post_item');
    await selectDocument('post', '既存投稿');
    await expand(layout);
    await field('columns').selectOption('1');
    await waitPaint('rgb(229, 240, 231)', .65, 1);
    assert.equal(await page.locator('[data-layout-scope][open]').count(), 1);
    assert(!(await (await page.request.get(base + '/legacy-post')).text()).includes('data-layout-section'));
    await page.locator('[data-mode="published"]').click();
    await page.waitForFunction(() => document.querySelector('#nx-design-frame').contentDocument.querySelector('.post-detail'));
    await page.locator('[data-mode="draft"]').click();
    await waitPaint('rgb(229, 240, 231)', .65, 1);
    await page.screenshot({path: path.join(output, 'inherited-post-layout.png')});
    await publish();

    const guest = await page.context().browser().newContext({javaScriptEnabled: false, viewport: {width: 1440, height: 1000}});
    for (const [pathName, rgb, width, columns] of [
        ['/page/browser-empty-published', 'rgb(17, 34, 51)', .8, 2],
        ['/legacy-post', 'rgb(229, 240, 231)', .65, 1],
    ]) {
        const publicPage = await guest.newPage();
        await guest.route(base + pathName, async route => {
            const response = await route.fetch();
            await route.fulfill({response, headers: {...response.headers(), 'content-security-policy': "default-src 'self'"}});
        });
        await publicPage.goto(base + pathName);
        const section = publicPage.locator('[data-layout-section="0"]');
        const actual = await section.evaluate(element => [
            getComputedStyle(element, '::before').backgroundColor,
            element.querySelector('.nx-page-section-inner').clientWidth / element.clientWidth,
            Number(element.dataset.columns),
        ]);
        assert.equal(actual[0], rgb);
        assert(Math.abs(actual[1] - width) < .01);
        assert.equal(actual[2], columns);
        await publicPage.setViewportSize({width: 390, height: 844});
        assert(await section.evaluate(element => {
            const inner = element.querySelector('.nx-page-section-inner');
            return getComputedStyle(inner).gridTemplateColumns.split(' ').length === 1 &&
                document.documentElement.scrollWidth <= innerWidth + 1;
        }));
        await publicPage.close();
    }
    await guest.close();

    await openScope('all');
    await setColor('#FFF0AD');
    await expand(layout);
    await field('percent').fill('70');
    await field('percent').press('Tab');
    await openScope('page_item');
    await waitPaint('rgb(17, 34, 51)', .7, 2);
    await expand(color);
    await page.locator('[data-page-inherit="color"]').check();
    await waitPaint('rgb(246, 230, 230)', .7, 2);
    await publish();
    await page.reload();
    await page.locator('[data-pane="page_layout"]').click();
    await openScope('post_item');
    await selectDocument('post', '既存投稿');
    await waitPaint('rgb(229, 240, 231)', .65, 1);
    await openScope('page_item');
    await selectDocument('page', '本文なし published');
    await waitPaint('rgb(246, 230, 230)', .7, 2);
    await expand(color);
    assert(await page.locator('[data-page-inherit="color"]').isChecked());
    assert.equal(await page.getByRole('button', {name: '公開', exact: true}).isDisabled(), true);
    await page.screenshot({path: path.join(output, 'inherited-page-layout.png')});
    console.log('PASS: five layout editors, inherited fields, publication, resets and mobile geometry');
}

async function checkCommonLayoutFeedback(page, base, output) {
    const field = name => page.locator('[data-page-field="' + name + '"]');
    const status = page.locator('[data-page-preview-status]');
    const conflict = page.locator('[data-page-preview-conflict]');
    async function openScope(id) {
        const details = page.locator('[data-layout-scope="' + id + '"]');
        if (!(await details.evaluate(element => element.open))) await details.locator('> summary').click();
        await field('enabled').waitFor();
    }
    async function waitCard() {
        await page.waitForFunction(() => {
            const document = window.document.querySelector('#nx-design-frame').contentDocument;
            return document.querySelector('.post-detail') && !document.querySelector('[data-layout-section]');
        });
    }
    async function waitGold() {
        await page.waitForFunction(() => {
            const frame = document.querySelector('#nx-design-frame');
            const section = frame.contentDocument.querySelector('[data-layout-section="0"]');
            return section && frame.contentWindow.getComputedStyle(section, '::before').backgroundColor === 'rgb(203, 179, 128)';
        });
    }
    async function publish() {
        const [response] = await Promise.all([
            page.waitForResponse(base + '/admin/design/publish'),
            page.getByRole('button', {name: '公開', exact: true}).click(),
        ]);
        assert.equal(response.status(), 200, await response.text());
        await page.waitForFunction(() => document.querySelector('[data-action="publish"]').disabled);
    }
    await openScope('page_item');
    await field('enabled').uncheck();
    await waitCard();
    await publish();
    await openScope('all');
    await field('previewTarget').selectOption({label: '固定ページ · 本文なし published'});
    const target = await field('previewTarget').inputValue();
    const colors = page.locator('[data-page-details="colors"]');
    if (!(await colors.evaluate(details => details.open))) await colors.locator('> summary').click();
    await field('hex').fill('#CBB380');
    await field('hex').press('Tab');
    await conflict.waitFor();
    assert.equal(await conflict.innerText(), '編集中の「本文なし published」は、このページには適用されていません。\n確認先：固定ページのレイアウト');
    assert.equal(await status.locator('dl').count(), 0);
    await waitCard();
    await page.screenshot({path: path.join(output, 'shared-layout-blocked.png')});
    await status.getByRole('button', {name: '個別設定を解除', exact: true}).click();
    assert((await conflict.innerText()).includes('確認先：固定のみの共通レイアウト'));
    await status.getByRole('button', {name: '固定のみの共通レイアウトを開く', exact: true}).click();
    await page.locator('[data-page-inherit="color"]').check();
    await waitGold();
    await status.getByRole('button', {name: '元の個別指定に戻す', exact: true}).click();
    await waitCard();
    await status.getByRole('button', {name: '個別設定を解除', exact: true}).click();
    await waitGold();
    assert.equal(await conflict.count(), 0);
    await publish();
    assert.equal(await status.getByRole('button', {name: '元の個別指定に戻す', exact: true}).count(), 0);
    const snapshot = await (await page.request.get(base + '/admin/design/page-layout/' + target.split(':')[1])).json();
    assert.deepEqual(snapshot.overrides, {format: 2});
    const publicPage = await page.context().newPage();
    await publicPage.goto(base + '/page/browser-empty-published');
    assert.equal(await publicPage.locator('[data-layout-section="0"]').evaluate(section =>
        getComputedStyle(section, '::before').backgroundColor), 'rgb(203, 179, 128)');
    await publicPage.close();
    await page.reload();
    await page.locator('[data-pane="page_layout"]').click();
    await openScope('all');
    await field('previewTarget').selectOption(target);
    await waitGold();
    await status.getByRole('button', {name: 'このページの個別設定を開く', exact: true}).click();
    assert.equal(await page.locator('[data-layout-scope="page_item"]').evaluate(details => details.open), true);
    await page.locator('[data-page-action="section-remove"]').click();
    await waitCard();
    await openScope('all');
    await conflict.waitFor();
    assert((await conflict.innerText()).includes('確認先：固定ページのレイアウト'));
    await status.getByRole('button', {name: '個別設定を解除', exact: true}).click();
    await waitGold();

    async function reloadIndividual() {
        await page.reload();
        await page.locator('[data-pane="page_layout"]').click();
        await openScope('page_item');
        await field('page').selectOption(target.split(':')[1]);
        await field('enabled').waitFor();
    }
    await openScope('page_item');
    await field('width').selectOption('wide');
    await page.locator('[data-page-action="section-remove"]').click();
    await waitCard();
    assert.equal(await page.locator('[data-page-reset="enabled"]').count(), 1);
    await field('enabled').uncheck();
    await publish();
    await reloadIndividual();
    assert.equal(await field('enabled').isChecked(), false);
    let deletion = await (await page.request.get(base + '/admin/design/page-layout/' + target.split(':')[1])).json();
    assert.equal(deletion.overrides.sectionCount, 0);
    assert.equal(deletion.overrides.enabled, false);
    await page.locator('[data-page-reset="enabled"]').click();
    await waitGold();
    assert.equal(await field('width').inputValue(), 'wide');
    await publish();
    await reloadIndividual();
    await waitGold();
    deletion = await (await page.request.get(base + '/admin/design/page-layout/' + target.split(':')[1])).json();
    assert.deepEqual(deletion.overrides, {format: 2, width: 'wide'});
    await status.getByRole('button', {name: '個別設定を解除', exact: true}).click();
    assert.equal(await status.getByText('共通設定を使用中', {exact: true}).count(), 1);
    await waitGold();
    await publish();
    await reloadIndividual();
    await waitGold();
    deletion = await (await page.request.get(base + '/admin/design/page-layout/' + target.split(':')[1])).json();
    assert.deepEqual(deletion.overrides, {format: 2});
    assert.equal(await status.getByText('共通設定を使用中', {exact: true}).count(), 1);
    assert.equal(await status.locator('[data-page-action="use-common"]').count(), 0);
    console.log('PASS: one reset clears disabled/empty overrides, retains body width, and inheritance persists after publish/reload');
    await page.screenshot({path: path.join(output, 'shared-layout-inherited.png')});
    console.log('PASS: blocked shared styles show their source and restore inheritance, undo and published colors');
}

async function checkMediaEditor(page, base, output) {
    await page.goto(base + '/admin/media');
    const folderUrl = await createMediaFolder(page, '画像の検証');
    const png = await page.evaluate(() => {
        const source = document.createElement('canvas');
        source.width = 1600;
        source.height = 800;
        const ctx = source.getContext('2d');
        ctx.fillStyle = '#ee2118';
        ctx.fillRect(0, 0, 800, 800);
        ctx.fillStyle = '#1b399d';
        ctx.fillRect(800, 0, 800, 800);
        return source.toDataURL().split(',')[1];
    });
    const name = 'media-editor-fixture';
    const upload = page.locator('.media-upload-form');
    await upload.locator('input[type="file"]').setInputFiles({
        name: name + '.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64'),
    });
    await upload.getByRole('button', {name: 'アップロード', exact: true}).click();
    const card = page.locator('.media-card').filter({has: page.getByText(name, {exact: true})});
    const originalUrl = await card.locator('img').getAttribute('src');
    assert.equal(page.url(), folderUrl);
    await card.locator('.media-thumb-button').click();
    const open = page.locator('.media-modal[open] [data-open-image-editor]');
    const saveUrl = await open.getAttribute('data-save-url');
    await open.click();

    const dialog = page.locator('[data-media-editor]');
    const size = (width, height) => width + ' × ' + height + ' px';
    const waitSize = (width, height) => page.waitForFunction(expected =>
        document.querySelector('[data-editor-output]')?.textContent.startsWith(expected), size(width, height));
    const readSize = async () => (await dialog.locator('[data-editor-output]').textContent()).match(/^\d+\s×\s\d+/)[0]
        .split(' × ').map(Number);
    async function drag(handle, dx, dy, shift = false) {
        const box = await dialog.locator('[data-resize-handle="' + handle + '"]').boundingBox();
        const x = box.x + box.width / 2, y = box.y + box.height / 2;
        if (shift) await page.keyboard.down('Shift');
        await page.mouse.move(x, y);
        await page.mouse.down();
        await page.mouse.move(x + dx, y + dy, {steps: 5});
        await page.mouse.up();
        if (shift) await page.keyboard.up('Shift');
    }
    await waitSize(1600, 800);
    for (const direction of [-1, 1]) {
        await drag('se', direction * 45, direction * 8, true);
        const [width, height] = await readSize();
        assert(direction * (width - 1600) > 0 && Math.abs(width - 2 * height) <= 1);
        await page.keyboard.press('Control+z');
        await waitSize(1600, 800);
    }
    await drag('se', -66, -10);
    const [freeWidth, freeHeight] = await readSize();
    assert(freeWidth < 1600 && freeHeight < 800 && Math.abs(freeWidth - 2 * freeHeight) > 10);
    await page.keyboard.press('Control+z');
    await waitSize(1600, 800);
    await drag('e', -36, 9, true);
    const [edgeWidth, edgeHeight] = await readSize();
    assert(edgeWidth < 1600 && edgeHeight === 800);
    await page.keyboard.press('Control+z');
    await waitSize(1600, 800);
    await dialog.locator('[data-editor-preset="800"]').click();
    await waitSize(800, 400);
    await page.keyboard.press('Control+z');
    await waitSize(1600, 800);

    await dialog.locator('[data-editor-mode="crop"]').click();
    const ratio = dialog.locator('[data-editor-ratio]');
    await ratio.selectOption('1');
    await waitSize(800, 800);
    await drag('se', -35, -12);
    const [cropWidth, cropHeight] = await readSize();
    assert(cropWidth < 800 && cropWidth === cropHeight);
    await page.keyboard.press('Control+z');
    await waitSize(800, 800);

    const frame = dialog.locator('[data-editor-frame]');
    const cropBox = await frame.boundingBox();
    const cropCenterX = cropBox.x + cropBox.width / 2;
    const cropCenterY = cropBox.y + cropBox.height / 2;
    const cropLeft = await frame.evaluate(element => parseFloat(element.style.left));
    await page.mouse.move(cropCenterX, cropCenterY);
    await page.mouse.down();
    await page.mouse.move(cropCenterX + 30, cropCenterY, {steps: 5});
    await page.mouse.up();
    await page.waitForFunction(left => parseFloat(document.querySelector('[data-editor-frame]').style.left) > left, cropLeft);
    await page.keyboard.press('Control+z');
    await page.waitForFunction(left => parseFloat(document.querySelector('[data-editor-frame]').style.left) === left, cropLeft);
    await page.keyboard.press('Control+z');
    await waitSize(1600, 800);
    await ratio.selectOption('16:9');
    await waitSize(1422, 800);
    await dialog.locator('[data-editor-turn="1"]').click();
    await waitSize(800, 1422);
    await dialog.locator('[data-editor-mode="resize"]').click();
    const input = dialog.locator('[data-editor-width]');
    await input.fill('400');
    await input.press('Tab');
    await waitSize(400, 711);
    await dialog.locator('[data-editor-quality]').selectOption('light');
    await page.screenshot({path: path.join(output, 'media-editor.png')});
    await page.setViewportSize({width: 390, height: 844});
    assert(await dialog.evaluate(editor => editor.scrollWidth <= editor.clientWidth + 1 &&
        editor.querySelector('[data-editor-stage]').getBoundingClientRect().right <= innerWidth));
    await page.setViewportSize({width: 1440, height: 1000});

    const save = new URL(saveUrl, base).href;
    assert.equal((await page.request.post(save, {form: {}})).status(), 419);
    assert.equal((await page.request.post(save, {
        multipart: {
            _csrf_token: await dialog.getAttribute('data-csrf'),
            media_file: {name: 'invalid.webp', mimeType: 'image/webp', buffer: Buffer.from('not an image')},
        },
    })).status(), 422);
    const [response] = await Promise.all([
        page.waitForResponse(r => r.url() === save && r.request().method() === 'POST'),
        dialog.locator('[data-editor-save]').click(),
    ]);
    assert.equal(response.status(), 201);
    const saved = await response.json();
    assert.deepEqual([saved.width, saved.height, saved.format], [400, 711, 'WEBP']);
    assert(saved.bytes > 0);
    await dialog.getByText('別の画像として保存しました。', {exact: true}).waitFor();
    await dialog.getByRole('button', {name: '一覧に戻る', exact: true}).click();
    const newCard = page.locator('.media-card').filter({has: page.getByText(name + '（編集済み）', {exact: true})});
    assert.equal(await card.locator('img').getAttribute('src'), originalUrl);
    const editedUrl = await newCard.locator('img').getAttribute('src');
    assert.equal(page.url(), folderUrl);
    const actualImage = await newCard.locator('img').evaluate(async image => {
        await image.decode();
        const sample = document.createElement('canvas');
        sample.width = image.naturalWidth;
        sample.height = image.naturalHeight;
        const ctx = sample.getContext('2d');
        ctx.drawImage(image, 0, 0);
        return [sample.width, sample.height, Array.from(ctx.getImageData(10, 10, 1, 1).data),
            Array.from(ctx.getImageData(10, sample.height - 10, 1, 1).data)];
    });
    const [width, height, top, bottom] = actualImage;
    assert(width === 400 && height === 711 && top[0] > top[2] && bottom[2] > bottom[0]);
    if (process.env.NEXAPRESS_TEST_CLASSIC_EDITOR) {
        await page.goto(base + '/admin/pages/create');
        await page.getByRole('button', {name: 'メディア追加', exact: true}).click();
        await page.locator('.classic-editor-media-item').filter({hasText: name + '（編集済み）'}).waitFor();
        await page.locator('.classic-editor-media-dialog').getByRole('button', {name: '閉じる', exact: true}).click();
    }
    console.log('PASS: media editor resizes, undoes gestures, crops, rotates and saves a WebP copy');
    return {folderUrl, name, originalUrl, editedUrl};
}

async function checkMediaFolders(page, base, output, {folderUrl, name, originalUrl, editedUrl}) {
    const editName = name + '（編集済み）';
    const card = title => page.locator('.media-card').filter({has: page.getByText(title, {exact: true})});
    const all = page.getByRole('checkbox', {name: 'すべて選択'});
    const expectNotice = message => page.getByText(message, {exact: true}).waitFor();
    const assertFileStatus = async (url, code) =>
        assert.equal((await page.request.get(new URL(url, base).href)).status(), code);

    async function confirmAction(button, texts, accept = true) {
        await Promise.all([
            page.waitForEvent('dialog').then(async dialog => {
                const message = dialog.message();
                if (accept) await dialog.accept();
                else await dialog.dismiss();
                for (const text of texts) assert(message.includes(text), message);
            }),
            button.click(),
        ]);
    }

    await page.goto(folderUrl);
    assert.equal(await page.locator('.media-card').count(), 2);
    const checkedFolder = await createMediaFolder(page, '移動先 & 確認');
    await page.goto(folderUrl);
    assert.equal((await page.request.post(base + '/admin/media/move', {form: {}})).status(), 419);
    await all.check();
    await expectNotice('2件選択');
    await page.getByRole('combobox', {name: '移動先のフォルダ'}).selectOption({label: '移動先 & 確認'});
    await page.screenshot({path: path.join(output, 'media-library.png')});
    await page.getByRole('button', {name: '移動', exact: true}).click();
    await expectNotice('2件のメディアを移動しました。');
    assert.equal(await page.locator('.media-card').count(), 0);

    await page.goto(checkedFolder);
    assert.equal(await card(name).locator('img').getAttribute('src'), originalUrl);
    assert.equal(await card(editName).locator('img').getAttribute('src'), editedUrl);
    await all.check();
    await card(name).locator('.media-thumb-button').dragTo(mediaFolder(page, '画像の検証'));
    await expectNotice('2件のメディアを移動しました。');
    assert.equal(await page.locator('.media-card').count(), 0);

    await page.goto(folderUrl);
    await card(editName).getByRole('checkbox').check();
    assert.equal(await all.evaluate(input => input.indeterminate), true);
    await card(name).locator('.media-thumb').dragTo(mediaFolder(page, '未分類'));
    await expectNotice('1件のメディアを移動しました。');
    assert.equal(await page.locator('.media-card').count(), 1);
    assert.equal(await card(editName).locator('img').getAttribute('src'), editedUrl);

    await confirmAction(page.getByRole('button', {name: 'フォルダを削除'}), ['画像の検証', '未分類']);
    await expectNotice('フォルダを削除しました。中のメディアは「未分類」に戻りました。');
    assert.equal(await mediaFolder(page, '画像の検証').count(), 0);
    assert.equal(await card(name).locator('img').getAttribute('src'), originalUrl);
    assert.equal(await card(editName).locator('img').getAttribute('src'), editedUrl);
    await assertFileStatus(originalUrl, 200);
    await assertFileStatus(editedUrl, 200);
    await assertFileStatus('/public/uploads/media/legacy-media.txt', 200);

    const note = 'media-library-note';
    const upload = page.locator('.media-upload-form');
    await upload.locator('input[type="file"]').setInputFiles({
        name: note + '.txt', mimeType: 'text/plain', buffer: Buffer.from('disposable test media'),
    });
    await upload.getByRole('button', {name: 'アップロード', exact: true}).click();
    const deleteNote = card(note).getByRole('button', {name: note + 'を削除'});
    await confirmAction(deleteNote, [note], false);
    assert.equal(await card(note).count(), 1);
    await confirmAction(deleteNote, [note]);
    await expectNotice('1件のメディアを削除しました。');
    assert.equal(await card(note).count(), 0);

    await card(name).getByRole('checkbox').check();
    await card(editName).getByRole('checkbox').check();
    await confirmAction(page.getByRole('button', {name: '選択した項目を削除'}), ['2件', name, editName]);
    await expectNotice('2件のメディアを削除しました。');
    assert.equal(await page.locator('.media-card').count(), 1);
    await card('既存メディア').waitFor();
    await assertFileStatus(originalUrl, 404);
    await assertFileStatus(editedUrl, 404);
    console.log('PASS: media folders, checked and drag moves, stable URLs, folder and media deletion');
}

(async () => {
    const base = process.env.NEXAPRESS_TEST_URL;
    const output = process.env.NEXAPRESS_TEST_OUTPUT;
    const executablePath = process.env.NEXAPRESS_TEST_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const browser = await chromium.launch({ executablePath, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(base + '/admin/login');
        await page.locator('[name="username"]').fill('admin');
        await page.locator('[name="password"]').fill('Test-Password-2026');
        await Promise.all([page.waitForURL(base + '/admin'), page.locator('button[type="submit"]').click()]);
        const media = await checkMediaEditor(page, base, output);
        await checkMediaFolders(page, base, output, media);
        const acceptDialogs = dialog => dialog.accept();
        page.on('dialog', acceptDialogs);
        for (const kind of ['page', 'post']) {
            const route = kind === 'page' ? '/admin/pages/create' : '/admin/posts/create';
            await page.goto(base + route);
            const form = page.locator('form[data-editor-kind]');
            await form.locator('[name="title"]').fill('ブラウザ確認 ' + kind);
            await form.locator('[name="slug"]').fill('browser-' + kind);
            const visual = form.locator('.classic-editor-content');
            if (await visual.count()) await visual.fill('復元対象の本文 ' + kind);
            else await form.locator('[name="content"]').fill('復元対象の本文 ' + kind);
            await page.waitForFunction(() => document.querySelector('.editor-draft-status').textContent.includes('自動保存しました'));
            assert(await page.evaluate(() => Object.keys(localStorage).some(key => key.startsWith('nx-editor:'))));
            await page.goto(base + '/admin');
            await page.goto(base + route);
            await page.getByRole('button', { name: '入力を復元', exact: true }).click();
            assert.equal(await form.locator('[name="title"]').inputValue(), 'ブラウザ確認 ' + kind);
            assert((await form.locator('[name="content"]').inputValue()).includes('復元対象の本文 ' + kind));
            if (await visual.count()) assert((await visual.textContent()).includes('復元対象の本文 ' + kind));
            const draftKey = await form.locator('[name="_editor_draft_key"]').inputValue();
            await page.screenshot({ path: path.join(output, kind + '-editor.png'), fullPage: true });
            await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/create')), form.locator('button[type="submit"]').last().click()]);
            assert.equal(await page.evaluate(key => localStorage.getItem(key), draftKey), null);
            if (kind === 'page') {
                assert((await form.locator('[name="content"]').inputValue()).includes('復元対象の本文 ' + kind));
            }
            console.log('PASS: browser ' + kind + ' draft recovery and successful-save cleanup');
        }
        for (const status of ['draft', 'published']) {
            await page.goto(base + '/admin/pages/create');
            const form = page.locator('form[data-editor-kind="page"]');
            if (process.env.NEXAPRESS_TEST_CLASSIC_EDITOR) {
                await form.locator('.classic-editor-content').waitFor({ timeout: 8000 });
            }
            if (process.env.NEXAPRESS_TEST_MULTI_TOOL_FIXTURE) {
                await page.getByText('本文を検索・置換', { exact: true }).waitFor({ timeout: 8000 });
            }
            await form.locator('[name="title"]').fill('本文なし ' + status);
            await form.locator('[name="slug"]').fill('browser-empty-' + status);
            await form.locator('[name="status"]').selectOption(status);
            assert.equal(await form.locator('[name="content"]').inputValue(), '');
            await Promise.all([
                page.waitForURL(url => /\/admin\/pages\/edit\/\d+$/.test(url.pathname), { timeout: 8000 }),
                form.getByRole('button', {name: '保存', exact: true}).click(),
            ]);
            assert.equal(await form.locator('[name="status"]').inputValue(), status);
            assert.equal(await form.locator('[name="content"]').inputValue(), '');
            assert(await page.getByText('固定ページを保存しました。', {exact: true}).isVisible());
        }
        console.log('PASS: browser saves empty fixed pages as draft and published');
        await page.goto(base + '/admin/pages/create');
        let failedForm = page.locator('form[data-editor-kind]');
        await failedForm.locator('[name="title"]').fill('保存エラー後も保護');
        await failedForm.locator('[name="slug"]').fill('legacy');
        const failedVisual = failedForm.locator('.classic-editor-content');
        if (await failedVisual.count()) await failedVisual.fill('重複スラッグでも本文を保護');
        else await failedForm.locator('[name="content"]').fill('重複スラッグでも本文を保護');
        await Promise.all([page.waitForURL(base + '/admin/pages/store'), failedForm.locator('button[type="submit"]').last().click()]);
        assert.equal(await failedForm.getAttribute('data-editor-unsaved'), '1');
        const failedKey = await failedForm.locator('[name="_editor_draft_key"]').inputValue();
        assert((await page.evaluate(key => localStorage.getItem(key), failedKey)).includes('重複スラッグでも本文を保護'));
        await page.goto(base + '/admin');
        await page.goto(base + '/admin/pages/create');
        await page.getByRole('button', { name: '入力を復元', exact: true }).click();
        assert.equal(await failedForm.locator('[name="title"]').inputValue(), '保存エラー後も保護');
        await page.evaluate(key => localStorage.removeItem(key), failedKey);
        console.log('PASS: browser retains unsaved input after server validation failure');
        await page.goto(base + '/admin/pages/create');
        await page.locator('[name="title"]').fill('未保存の警告');
        let warnings = 0;
        const onDialog = dialog => { if (dialog.type() === 'beforeunload') warnings++; };
        page.on('dialog', onDialog);
        await page.goto(base + '/admin/navigation');
        page.off('dialog', onDialog);
        assert(warnings > 0, 'beforeunload warning should be shown after editing');
        const newItem = page.locator('.navigation-editor').last();
        await newItem.locator('[name="type"]').selectOption('custom');
        assert(await newItem.locator('[name="url"]').isVisible());
        assert.equal(await newItem.locator('select[name="reference_id"]:enabled').count(), 0);
        await newItem.locator('[name="label"]').fill('ブラウザメニュー');
        await newItem.locator('[name="url"]').fill('/posts');
        await Promise.all([page.waitForURL(base + '/admin/navigation'), newItem.getByRole('button', { name: '追加', exact: true }).click()]);
        await page.screenshot({ path: path.join(output, 'navigation.png'), fullPage: true });
        await page.goto(base + '/admin/profile');
        assert.equal(await page.locator('input[name="role"]').count(), 0);
        await page.screenshot({ path: path.join(output, 'profile.png'), fullPage: true });
        await page.goto(base + '/posts');
        assert(await page.getByRole('link', { name: 'ブラウザメニュー', exact: true }).isVisible());
        for (const theme of ['default', 'monoedge']) {
            await page.request.post(base + '/admin/themes/update', { form: { theme } });
            await page.setViewportSize({ width: 1440, height: 1000 });
            await page.goto(base + '/posts');
            await page.screenshot({ path: path.join(output, theme + '-desktop.png'), fullPage: true });
            await page.setViewportSize({ width: 390, height: 844 });
            await page.screenshot({ path: path.join(output, theme + '-mobile.png'), fullPage: true });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), theme + ' should fit a mobile viewport');
        }
        await page.setViewportSize({ width: 1960, height: 920 });
        await page.goto(base + '/admin/design');
        const preview = page.locator('#nx-design-frame');
        await page.waitForFunction(() => document.querySelector('#nx-design-stage').getAttribute('aria-busy') === 'false' &&
            document.querySelector('#nx-design-frame').contentDocument.querySelector('.site-footer'));
        await preview.evaluate(frame => {
            const document = frame.contentDocument;
            const cards = document.querySelectorAll('.post-card');
            if (!cards.length) throw new Error('Expected a post archive in the site preview');
            Array.from(cards).slice(1).forEach(card => card.remove());
            document.querySelector('.public-pagination')?.remove();
        });
        for (const device of ['desktop', 'mobile']) {
            await page.locator(`[data-device="${device}"]`).click();
            assert(await preview.evaluate(frame => {
                const stage = frame.closest('#nx-design-stage');
                stage.scrollTop = stage.scrollHeight;
                return stage.scrollTop === 0 && getComputedStyle(frame).pointerEvents === 'auto';
            }), 'The site preview should have one interactive scrolling surface');
            await preview.hover({ position: { x: 100, y: 100 } });
            await page.mouse.wheel(0, 4000);
            await page.waitForFunction(() => {
                const document = window.document.querySelector('#nx-design-frame').contentDocument;
                const scroll = document.scrollingElement;
                const footer = document.querySelector('.site-footer').getBoundingClientRect();
                return scroll.scrollTop > 0 && scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight <= 1 &&
                    footer.top >= -1 && footer.bottom <= scroll.clientHeight + 1;
            });
        }
        console.log('PASS: desktop and mobile preview scroll one short page all the way to the footer');
        await page.locator('[data-pane="page_layout"]').click();
        await page.locator('[data-layout-scope="page_item"] > summary').click();
        await page.locator('[data-page-field="page"]').selectOption({label: '既存ページ'});
        await page.locator('[data-page-field="enabled"]').check();
        const pagePanel = page.locator('#nx-design-panel');
        const sectionPicker = page.getByRole('combobox', {name: '編集するセクション'});
        const colorSettings = page.locator('[data-page-details="colors"]');
        const layoutSettings = page.locator('[data-page-details="layout"]');
        assert.equal(await pagePanel.getByRole('heading', {name: '選択中のセクション', exact: true}).count(), 0);
        assert.equal(await pagePanel.getByRole('button', {name: /^(上へ|下へ)$/}).count(), 0);
        assert.equal(await sectionPicker.inputValue(), '0');
        assert(await sectionPicker.evaluate(picker => {
            const title = picker.previousElementSibling.getBoundingClientRect();
            const select = picker.getBoundingClientRect();
            return title.width > 0 && select.left >= title.right && select.left - title.right <= 10 &&
                Math.abs(select.top + select.height / 2 - title.top - title.height / 2) < 2;
        }), 'the selected section is immediately to the right of the section heading');
        assert(await colorSettings.evaluate(details =>
            details.previousElementSibling.querySelector('[data-page-field="background"]') !== null),
            'background color directly follows background width');
        assert.equal(await colorSettings.evaluate(details => details.open), false);
        assert.equal(await layoutSettings.evaluate(details => details.open), false);
        assert.equal(await colorSettings.locator('details').count(), 0);
        await page.screenshot({path: path.join(output, 'page-layout-collapsed.png')});
        await colorSettings.locator('summary').click();
        const renderedSection = page.frameLocator('#nx-design-frame').locator('[data-layout-section="0"]');
        const hex = page.locator('[data-page-field="hex"]');
        assert(await hex.isVisible());
        assert.equal(await colorSettings.locator('[data-page-color]:visible').count(), 15);
        await page.locator('[data-page-color="#101827"]').click();
        assert.equal(await hex.inputValue(), '#101827');
        await page.waitForFunction(() => {
            const frame = document.querySelector('#nx-design-frame');
            const section = frame.contentDocument.querySelector('[data-layout-section]');
            return section && frame.contentWindow.getComputedStyle(section, '::before').backgroundColor === 'rgb(16, 24, 39)';
        });
        await hex.fill('#abc');
        await hex.press('Tab');
        assert.equal(await hex.inputValue(), '#AABBCC');
        await colorSettings.locator('summary').click();
        assert.equal(await hex.isVisible(), false);
        await layoutSettings.locator('summary').click();
        await layoutSettings.locator('[data-page-field="widthMode"]').selectOption('percent');
        await layoutSettings.locator('[data-page-field="percent"]').fill('80');
        await layoutSettings.locator('[data-page-field="percent"]').press('Tab');
        await layoutSettings.locator('[data-page-field="columns"]').selectOption('2');
        assert.equal(await layoutSettings.evaluate(details => details.open), true);
        assert.equal(await colorSettings.evaluate(details => details.open), false);
        await pagePanel.getByRole('button', {name: '＋ セクションを追加', exact: true}).click();
        assert.equal(await sectionPicker.inputValue(), '1');
        await sectionPicker.selectOption('0');
        assert.equal(await page.locator('[data-page-field="percent"]').inputValue(), '80');
        assert.equal(await page.locator('[data-page-field="columns"]').inputValue(), '2');
        await sectionPicker.selectOption('1');
        await pagePanel.getByRole('button', {name: 'セクションを削除', exact: true}).click();
        assert.equal(await sectionPicker.inputValue(), '0');
        assert.equal(await sectionPicker.locator('option').count(), 1);
        await colorSettings.locator('summary').focus();
        await page.keyboard.press('Space');
        assert(await hex.isVisible());
        assert.equal(await hex.inputValue(), '#AABBCC');
        await page.locator('[data-page-field="background"]').selectOption('none');
        assert.equal(await colorSettings.count(), 0);
        await page.locator('[data-page-field="background"]').selectOption('full');
        assert.equal(await colorSettings.evaluate(details => details.open), true);
        await colorSettings.locator('summary').click();
        assert.equal(await colorSettings.evaluate(details => details.open), false);
        console.log('PASS: section selection, color and layout disclosures retain settings through redraws');
        await page.locator('[data-page-details="elements"] > summary').click();
        await page.locator('[data-device="desktop"]').click();
        await page.waitForFunction(() => {
            const frame = document.querySelector('#nx-design-frame');
            const section = frame.contentDocument.querySelector('[data-layout-section][data-columns="2"]');
            return section && frame.contentWindow.getComputedStyle(section, '::before').backgroundColor === 'rgb(170, 187, 204)';
        });
        const geometry = await renderedSection.evaluate(section => {
            const grid = section.querySelector('.nx-page-section-inner');
            return [grid.clientWidth / section.clientWidth, grid.firstElementChild.clientWidth / grid.clientWidth];
        });
        assert(geometry.every((value, i) => Math.abs(value - [.8, .45][i]) < .01), '80% section and 45% column preview widths');
        await page.locator('[data-page-field="elementType"]').scrollIntoViewIfNeeded();
        await page.waitForFunction(() => {
            const box = document.querySelector('.nx-design-preview').getBoundingClientRect();
            return scrollY > 0 && box.top >= 0 && box.bottom <= innerHeight;
        });
        await page.screenshot({ path: path.join(output, 'page-layout.png') });
        console.log('PASS: color presets and HEX entry update the section, and the preview stays visible while scrolling the editor');

        const [published] = await Promise.all([
            page.waitForResponse(base + '/admin/design/publish'),
            page.getByRole('button', {name: '公開', exact: true}).click(),
        ]);
        assert(published.ok());
        const guestContext = await browser.newContext({ viewport: { width: 1440, height: 1000 }, javaScriptEnabled: false });
        for (const [visitor, signedIn] of [[context, true], [guestContext, false]]) {
            const publicUrl = base + '/page/legacy';
            await visitor.route(publicUrl, async route => {
                const response = await route.fetch();
                await route.fulfill({
                    response,
                    headers: {...response.headers(), 'content-security-policy': "default-src 'self'"},
                });
            });
            const publicPage = await visitor.newPage();
            await publicPage.goto(publicUrl);
            const section = publicPage.locator('[data-layout-section="0"]');
            const [color, width, column] = await section.evaluate(element => {
                const grid = element.querySelector('.nx-page-section-inner');
                return [getComputedStyle(element, '::before').backgroundColor,
                    grid.clientWidth / element.clientWidth, grid.firstElementChild.clientWidth / grid.clientWidth];
            });
            assert.equal(color, 'rgb(170, 187, 204)');
            assert(Math.abs(width - .8) < .01 && Math.abs(column - .45) < .01);
            assert.equal(await publicPage.locator('.admin-preview-header').count(), signedIn ? 1 : 0);
            await publicPage.screenshot({path: path.join(output, signedIn ? 'strict-csp-signed-in.png' : 'strict-csp-guest.png')});
            await publicPage.close();
            await visitor.unroute(publicUrl);
        }
        await guestContext.close();
        console.log('PASS: the published colors and column widths are visible with strict Content Security Policy');
        await checkInheritedLayouts(page, base, output);
        await checkCommonLayoutFeedback(page, base, output);
        await require('./design-spacing-overlay.cjs')(page, base, output);
        await require('./design-spacing.cjs')(page, base, output);
        await require('./design-appearance.cjs')(page, base, output);
        await require('./chrome-layouts.cjs')(page, base, output);
        await require('./post-categories.cjs')(page, base, output);
        await require('./design-audit.cjs')(page, base, output);
        await require('./widget-link-labels.cjs')(page, base, output);
        await require('./category-widgets.cjs')(page, base, output);
        if (process.env.NEXAPRESS_TEST_CLASSIC_EDITOR) await require('./editor-preview.cjs')(page, base, output);
        page.off('dialog', acceptDialogs);
        await require('./design-reset.cjs')(page, base, output);
        assert.deepEqual(errors, []);
        console.log('PASS: browser warning, menu editing, profile, desktop/mobile layout, and no JavaScript exceptions');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
