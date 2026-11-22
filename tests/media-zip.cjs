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

function uncompressedPng(png) {
    return execFileSync('python', ['-X', 'utf8', '-c', `
import struct, sys, zlib
data = sys.stdin.buffer.read()
length, kind = struct.unpack_from(">I4s", data, 8)
assert kind == b"IHDR"
header = data[:20 + length]
offset = len(header)
pixels = bytearray()
while offset < len(data):
    length, kind = struct.unpack_from(">I4s", data, offset)
    if kind == b"IDAT":
        pixels.extend(data[offset + 8:offset + 8 + length])
    offset += length + 12
pixels = zlib.compress(zlib.decompress(pixels), 0)
chunk = b"IDAT" + pixels
sys.stdout.buffer.write(header + struct.pack(">I", len(pixels)) + chunk + struct.pack(">I", zlib.crc32(chunk)) + data[-12:])
`], {input: png});
}

function orientedJpeg(jpg, comment) {
    const tiff = Buffer.alloc(26);
    tiff.write('II');
    tiff.writeUInt16LE(42, 2);
    tiff.writeUInt32LE(8, 4);
    tiff.writeUInt16LE(1, 8);
    tiff.writeUInt16LE(0x0112, 10);
    tiff.writeUInt16LE(3, 12);
    tiff.writeUInt32LE(1, 14);
    tiff.writeUInt16LE(6, 18);
    const exif = Buffer.concat([Buffer.from('Exif\0\0'), tiff]);
    const segment = (marker, bytes) => {
        const size = Buffer.alloc(2);
        size.writeUInt16BE(bytes.length + 2);
        return Buffer.concat([Buffer.from([0xff, marker]), size, bytes]);
    };
    return Buffer.concat([jpg.subarray(0, 2), segment(0xfe, comment), segment(0xe1, exif), jpg.subarray(2)]);
}

function webpWithMetadata(webp, metadata) {
    const header = Buffer.from(webp);
    assert.equal(header.toString('ascii', 12, 16), 'VP8X');
    header[20] |= 4;
    const chunk = Buffer.alloc(8 + metadata.length + (metadata.length % 2));
    chunk.write('XMP ');
    chunk.writeUInt32LE(metadata.length, 4);
    metadata.copy(chunk, 8);
    header.writeUInt32LE(header.length + chunk.length - 8, 4);
    return Buffer.concat([header, chunk]);
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
    assert.match(await zipForm.textContent(), /画像\s*200\s*枚まで/);
    const options = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 4;
        canvas.height = 3;
        const context = canvas.getContext('2d');
        context.fillStyle = '#b65b27';
        context.fillRect(1, 0, 3, 3);
        context.fillStyle = 'rgba(55,123,236,0.64)';
        context.fillRect(0, 1, 2, 1);
        return ['image/png', 'image/jpeg', 'image/webp'].map(type => canvas.toDataURL(type).split(',')[1]);
    });
    const [png, jpg, webp] = options.map(data => Buffer.from(data, 'base64'));
    const metadata = Buffer.from('nexapress test metadata');
    const jpeg = orientedJpeg(jpg, metadata);
    const annotatedWebp = webpWithMetadata(webp, metadata);
    const gif = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');
    const expandedPng = uncompressedPng(png);
    const valid = archive([
        ['写真/', Buffer.alloc(0), 0o040755],
        ['写真/透過.png', expandedPng],
        ['写真/メタデータ.jpeg', jpeg],
        ['見本.gif', gif],
        ['素材/背景/背景.webp', webp],
        ['素材/背景/メタデータ付き.webp', annotatedWebp],
        ['説明/説明書.txt', Buffer.from('example note')],
        ['説明/見本.svg', Buffer.from('example unsupported file')],
    ]);

    await zipForm.locator('input[type="file"]').setInputFiles({name: 'images.zip', mimeType: 'application/zip', buffer: valid});
    await zipForm.getByRole('button', {name: 'ZIPを取り込む'}).click();
    await page.getByRole('status').filter({hasText: '5件の画像をZIPから登録しました。2件の対応外ファイルを除外しました。'}).waitFor();
    await page.getByRole('status').filter({hasText: '3件の画像を画質を保ったまま軽量化しました。'}).waitFor();
    assert.equal(new URL(page.url()).searchParams.get('folder'), null);
    const folders = page.getByRole('navigation', {name: 'メディアのフォルダ'});
    assert.equal(await folders.getByRole('link', {name: '説明', exact: true}).count(), 0);
    const photoUrl = new URL(await folders.getByRole('link', {name: '写真', exact: true}).getAttribute('href'), base).href;
    const backgroundUrl = new URL(await folders.getByRole('link', {name: '素材/背景', exact: true}).getAttribute('href'), base).href;
    const cards = page.locator('[data-media-card]');
    const uploads = new Map();
    for (const [url, names] of [[board.url(), ['見本']], [backgroundUrl, ['背景', 'メタデータ付き']], [photoUrl, ['透過', 'メタデータ']]]) {
        await page.goto(url);
        const labels = await cards.evaluateAll(items => items.map(item => item.dataset.mediaTitle));
        assert.deepEqual(labels.sort(), names.sort());
        for (const name of names) {
            uploads.set(name, await page.locator('[data-media-card][data-media-title="' + name + '"] img').getAttribute('src'));
        }
    }
    const examples = [
        ['見本', gif], ['背景', webp], ['メタデータ付き', annotatedWebp], ['透過', expandedPng], ['メタデータ', jpeg],
    ];
    for (const [name, original] of examples) {
        const image = new URL(uploads.get(name), base).href;
        assert.match(new URL(image).pathname, /\/uploads\/media\/zip_[0-9a-f]{32}\.(?:jpg|png|gif|webp)$/);
        const result = await page.request.get(image);
        assert(result.ok());
        const registered = await result.body();
        assert(!registered.includes(metadata));
        assert(registered.length <= original.length);
        if (['透過', 'メタデータ', 'メタデータ付き'].includes(name)) assert(registered.length < original.length);
        if (name === '背景') assert.deepEqual(registered, original);
        const decoded = await page.evaluate(async files => {
            return Promise.all(files.map(async file => {
                const bytes = Uint8Array.from(atob(file), char => char.charCodeAt(0));
                const image = await createImageBitmap(new Blob([bytes]));
                const canvas = new OffscreenCanvas(image.width, image.height);
                const context = canvas.getContext('2d');
                context.drawImage(image, 0, 0);
                return [image.width, image.height, ...context.getImageData(0, 0, image.width, image.height).data];
            }));
        }, [original.toString('base64'), registered.toString('base64')]);
        assert.deepEqual(decoded[0], decoded[1], name);
        if (name === 'メタデータ') assert.deepEqual(decoded[1].slice(0, 2), [3, 4]);
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

    const atLimit = archive([
        ['上限確認/', Buffer.alloc(0), 0o040755],
        ...Array.from({length: 200}, (_, index) => ['上限確認/' + index + '.png', png]),
        ['上限確認/説明.txt', Buffer.from('example note')],
    ]);
    const accepted = await page.request.post(endpoint, form(atLimit));
    assert(accepted.ok());
    assert((await accepted.text()).includes('200件の画像をZIPから登録しました。1件の対応外ファイルを除外しました。'));
    await page.goto(accepted.url());
    const limitFolder = await folders.getByRole('link', {name: '上限確認', exact: true}).getAttribute('href');
    assert(limitFolder);
    await page.goto(new URL(limitFolder, base).href, {waitUntil: 'domcontentloaded'});
    assert.equal(await cards.count(), 200);

    const cases = [
        [archive([['文章/説明書.txt', Buffer.from('example note')]]), '1件の対応外ファイルを除外しました。'],
        [archive([['作成失敗/valid.png', png], ['作成失敗/wrong.jpg', png]]), '画像形式が一致しない'],
        [archive([['valid.png', png], ['link.txt', Buffer.from('unresolved'), 0o120777]]), 'リンクや特殊ファイル'],
        [archive(Array.from({length: 201}, (_, index) => ['枚数超過/' + index + '.png', png])), '画像は200枚まで'],
        [archive(Array.from({length: 401}, (_, index) => [index + '.txt', Buffer.from('note')])), '項目数または目録の容量'],
        [Buffer.from('This is a plain text file'), 'ZIPを読み込めません'],
    ];
    for (const [data, message] of cases) {
        const result = await page.request.post(endpoint, form(data));
        assert(result.ok());
        const html = await result.text();
        assert(html.includes(message));
        assert.equal((html.match(/data-media-card data-media-title=/g) ?? []).length, 1);
        assert(!html.includes('作成失敗'));
        assert(!html.includes('枚数超過'));
    }
    console.log('PASS: ZIP lossless PNG, JPEG and WebP, image orientation, transparency, exact-size fallback, GIF, folders and atomic rejection');
};
