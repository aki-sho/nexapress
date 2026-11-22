const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');

function archive(entries) {
    return execFileSync('python', ['-X', 'utf8', '-c', `
import base64, io, json, sys, zipfile
data = io.BytesIO()
with zipfile.ZipFile(data, 'w', zipfile.ZIP_DEFLATED) as archive:
    for name, contents, mode in json.load(sys.stdin):
        entry = zipfile.ZipInfo(name)
        entry.create_system = 3
        entry.external_attr = mode << 16
        archive.writestr(entry, base64.b64decode(contents), compress_type=zipfile.ZIP_DEFLATED)
sys.stdout.buffer.write(data.getvalue())
`], {input: JSON.stringify(entries.map(([name, content, mode = 0o100644]) => [name, content.toString('base64'), mode]))});
}

module.exports = async (page, base) => {
    await page.goto(base + '/admin/media');
    const token = await page.locator('.media-upload-form input[name="_csrf_token"]').first().inputValue();
    const board = await page.request.post(base + '/admin/media/folders/create', {form: {
        _csrf_token: token, name: 'ZIP取込の確認',
    }});
    assert(board.ok(), await board.text());
    const folder = new URL(board.url()).searchParams.get('folder');
    assert(folder);
    await page.goto(board.url());
    const zipForm = page.locator('.media-zip-import');
    await zipForm.locator('summary').click();
    if (await zipForm.locator('input[type="file"]').count() === 0) {
        assert.match(await zipForm.textContent(), /ZipArchive、Fileinfo、GD/);
        return;
    }
    const options = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 4;
        canvas.height = 3;
        const context = canvas.getContext('2d');
        context.fillStyle = '#b65b27';
        context.fillRect(1, 0, 3, 3);
        return ['image/png', 'image/jpeg', 'image/webp'].map(type => canvas.toDataURL(type).split(',')[1]);
    });
    const [png, jpg, webp] = options.map(data => Buffer.from(data, 'base64'));
    const metadata = Buffer.from('nexapress test metadata');
    const jpeg = Buffer.concat([jpg.subarray(0, 2), Buffer.from([0xff, 0xfe, 0, metadata.length + 2]),
        metadata, jpg.subarray(2)]);
    const gif = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');
    const valid = archive([
        ['写真/', Buffer.alloc(0), 0o040755],
        ['写真/透過.png', png],
        ['写真/メタデータ.jpeg', jpeg],
        ['見本.gif', gif],
        ['素材/背景/背景.webp', webp],
        ['説明/説明書.txt', Buffer.from('example note')],
        ['説明/見本.svg', Buffer.from('example unsupported file')],
    ]);

    await zipForm.locator('input[type="file"]').setInputFiles({name: 'images.zip', mimeType: 'application/zip', buffer: valid});
    await zipForm.getByRole('button', {name: 'ZIPを取り込む'}).click();
    await page.getByRole('status').filter({hasText: '4件の画像をZIPから登録しました。2件の対応外ファイルを除外しました。'}).waitFor();
    assert.equal(new URL(page.url()).searchParams.get('folder'), null);
    const folders = page.getByRole('navigation', {name: 'メディアのフォルダ'});
    assert.equal(await folders.getByRole('link', {name: '説明', exact: true}).count(), 0);
    const photoUrl = new URL(await folders.getByRole('link', {name: '写真', exact: true}).getAttribute('href'), base).href;
    const backgroundUrl = new URL(await folders.getByRole('link', {name: '素材/背景', exact: true}).getAttribute('href'), base).href;
    const cards = page.locator('[data-media-card]');
    const uploads = [];
    for (const [url, names] of [[board.url(), ['見本']], [backgroundUrl, ['背景']], [photoUrl, ['透過', 'メタデータ']]]) {
        await page.goto(url);
        const labels = await cards.evaluateAll(items => items.map(item => item.dataset.mediaTitle));
        assert.deepEqual(labels.sort(), names.sort());
        uploads.push(...await cards.locator('img').evaluateAll(images => images.map(image => image.src)));
    }
    for (const image of uploads) {
        assert.match(new URL(image).pathname, /\/uploads\/media\/zip_[0-9a-f]{32}\.(?:jpg|png|gif|webp)$/);
        const result = await page.request.get(image);
        assert(result.ok());
        assert(!(await result.body()).includes(metadata));
    }
    const transparent = cards.filter({hasText: '透過'}).locator('img');
    assert(await transparent.evaluate(async image => {
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = image.naturalWidth;
        canvas.height = image.naturalHeight;
        const context = canvas.getContext('2d');
        context.drawImage(image, 0, 0);
        return context.getImageData(0, 0, 1, 1).data[3] === 0;
    }));

    const endpoint = base + '/admin/media/import-zip';
    const form = (data, csrf = token) => ({multipart: {
        _csrf_token: csrf, view_folder: folder,
        media_zip: {name: 'images.zip', mimeType: 'application/zip', buffer: data},
    }});
    assert.equal((await page.request.post(endpoint, form(valid, 'invalid-token'))).status(), 419);
    const guest = await page.context().browser().newContext();
    try {
        const response = await guest.request.post(endpoint, form(valid));
        assert(new URL(response.url()).pathname.endsWith('/admin/login'));
    } finally {
        await guest.close();
    }
    const repeated = await page.request.post(endpoint, form(archive([['写真/追加.png', png]])));
    assert(repeated.ok());
    await page.goto(photoUrl);
    assert.equal(await folders.getByRole('link', {name: '写真', exact: true}).count(), 1);
    assert.equal(await cards.count(), 3);
    assert.equal(await cards.filter({hasText: '追加'}).count(), 1);

    const cases = [
        [archive([['文章/説明書.txt', Buffer.from('example note')]]), '1件の対応外ファイルを除外しました。'],
        [archive([['作成失敗/valid.png', png], ['作成失敗/wrong.jpg', png]]), '画像形式が一致しない'],
        [archive([['valid.png', png], ['link.txt', Buffer.from('unresolved'), 0o120777]]), 'リンクや特殊ファイル'],
        [archive(Array.from({length: 51}, (_, index) => [index + '.png', png])), '画像数または展開後の容量'],
        [archive(Array.from({length: 101}, (_, index) => [index + '.png', png])), '項目数または目録の容量'],
        [Buffer.from('This is a plain text file'), 'ZIPを読み込めません'],
    ];
    for (const [data, message] of cases) {
        const result = await page.request.post(endpoint, form(data));
        assert(result.ok());
        const html = await result.text();
        assert(html.includes(message));
        assert.equal((html.match(/data-media-card data-media-title=/g) ?? []).length, 1);
        assert(!html.includes('作成失敗'));
    }
    console.log('PASS: ZIP categories, existing folders, skipped files, static images, metadata, permissions, and atomic rejection');
};
