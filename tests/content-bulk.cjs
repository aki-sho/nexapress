const assert = require('node:assert/strict');

const row = (page, title) => page.locator('tbody tr').filter({hasText: title});
const box = (page, title) => row(page, title).locator('input[name="selected_ids[]"]');
const form = page => page.locator('[data-content-bulk]');
const csrf = page => form(page).locator('input[name="_csrf_token"]').inputValue();

async function create(page, base, target, title, slug) {
    await page.goto(base + '/admin/' + target + '/create');
    const token = await page.locator('input[name="_csrf_token"]').first().inputValue();
    const response = await page.request.post(base + '/admin/' + target + '/store', {
        form: {_csrf_token: token, title, slug, content: '<p>一括操作の本文</p>', status: 'draft'},
        maxRedirects: 0,
    });
    assert.equal(response.status(), 302, (await response.text()).slice(0, 300));
}

async function apply(page, action, message) {
    await form(page).getByRole('combobox', {name: '一括操作'}).selectOption(action);
    await form(page).getByRole('button', {name: '適用', exact: true}).click();
    await page.getByText(message, {exact: true}).waitFor();
}

async function rejected(page, url, token, ids, expected, complete = true) {
    const data = new URLSearchParams({_csrf_token: token, bulk_action: 'published'});
    for (const id of ids) data.append('selected_ids[]', id);
    if (complete) data.append('selection_complete', '1');
    const response = await page.request.post(url, {
        data: data.toString(), headers: {'content-type': 'application/x-www-form-urlencoded'},
    });
    assert.equal(response.status(), token ? 200 : 419);
    assert((await response.text()).includes(expected), 'Expected a validation or permission notice');
}

module.exports = async function checkContentBulk(page, base) {
    const visitor = await page.context().browser().newContext();
    const checkPublic = async (urls, expected) => {
        for (const [index, path] of urls.entries()) {
            const response = await visitor.request.get(base + path);
            assert.equal(response.status(), expected[index], path);
        }
    };
    try {
        await page.goto(base + '/admin/categories');
        await page.getByRole('columnheader', {name: '公開ページ', exact: true}).waitFor();
        const category = row(page, 'ニュース').locator('a.admin-public-link');
        assert.equal(await category.textContent(), '/category/news');
        const [archive] = await Promise.all([page.waitForEvent('popup'), category.click()]);
        try {
            await archive.waitForURL(base + '/category/news');
            await archive.getByRole('heading', {name: 'ニュース', exact: true}).waitFor();
        } finally {
            await archive.close();
        }
        await page.goto(base + '/admin/pages');
        const token = await csrf(page);
        const usePageUrl = async pageUrlType => {
            const response = await page.request.post(base + '/admin/settings/url/update', {
                form: {_csrf_token: token, site_url_mode: 'root', post_url_type: 'slug', page_url_type: pageUrlType},
                maxRedirects: 0,
            });
            assert.equal(response.status(), 302);
        };
        await usePageUrl('slug');
        try {
            await page.reload();
            assert.equal(await row(page, '既存ページ').locator('a.admin-public-link').getAttribute('href'), '/legacy');
            await checkPublic(['/legacy'], [200]);
        } finally {
            await usePageUrl('page_slug');
        }

        for (const target of ['pages', 'posts']) {
            const isPage = target === 'pages';
            const title = letter => '一括検証 ' + target + ' ' + letter;
            const letters = ['A', 'B', 'C'];
            const slug = letter => 'bulk-' + target + '-' + letter.toLowerCase();
            const urls = letters.map(letter => (isPage ? '/page/' : '/') + slug(letter));
            for (const letter of letters) await create(page, base, target, title(letter), slug(letter));

            const keyword = encodeURIComponent('一括検証 posts');
            const listing = base + '/admin/' + target + (isPage ? '' : '?keyword=' + keyword);
            const bulkUrl = base + '/admin/' + target + '/bulk';
            await page.goto(listing);
            await checkPublic(urls, [404, 404, 404]);
            const select = letter => box(page, title(letter)).check();
            const all = page.getByRole('checkbox', {name: 'この一覧をすべて選択'});
            const counter = page.locator('[data-content-bulk-count]');
            const count = await page.locator('input[name="selected_ids[]"]').count();
            await all.check();
            assert.equal(await page.locator('input[name="selected_ids[]"]:checked').count(), count);
            assert.equal(await counter.textContent(), count + '件選択');
            await all.uncheck();
            assert.equal(await counter.textContent(), '0件選択');
            await select('A');
            await select('B');
            assert.equal(await counter.textContent(), '2件選択');
            assert.equal(await all.evaluate(input => input.indeterminate), true);

            const a = await box(page, title('A')).inputValue();
            const token = await csrf(page);
            await rejected(page, bulkUrl, '', [a], '不正なリクエストです。');
            await rejected(page, bulkUrl, token, [a], '選択内容を最後まで受信できませんでした', false);
            await rejected(page, bulkUrl, token, [a, '2147483647'], '選択した項目の状態が変わりました');
            await checkPublic(urls, [404, 404, 404]);

            await apply(page, 'published', '2件を公開しました。');
            await checkPublic(urls, [200, 200, 404]);
            if (isPage) {
                await page.getByRole('columnheader', {name: '公開ページ', exact: true}).waitFor();
                await row(page, title('C')).getByText('公開後に表示', {exact: true}).waitFor();
                assert.equal(await row(page, title('C')).locator('a.admin-public-link').count(), 0);
                const publicPage = row(page, title('A')).locator('a.admin-public-link');
                assert.equal(await publicPage.textContent(), urls[0]);
                const [published] = await Promise.all([page.waitForEvent('popup'), publicPage.click()]);
                try {
                    await published.waitForURL(base + urls[0]);
                    await published.getByRole('heading', {name: title('A'), exact: true}).waitFor();
                } finally {
                    await published.close();
                }
            }
            for (const letter of ['A', 'B']) {
                await row(page, title(letter)).getByRole('cell', {name: '公開', exact: true}).waitFor();
            }
            if (!isPage) assert.equal(new URL(page.url()).searchParams.get('keyword'), '一括検証 posts');

            await select('A');
            await apply(page, 'published', '選択した項目はすでに指定した状態です。');
            if (isPage) {
                const revisions = await page.request.get(base + '/admin/pages/revisions/' + a);
                assert.equal(revisions.status(), 200);
                const html = await revisions.text();
                assert.equal((html.match(/class="revision-content"/g) || []).length, 1, 'only the previous state is recorded');
                assert.match(html, /bulk-pages-a ／ 下書き/);
            }

            await select('A');
            await select('B');
            await apply(page, 'draft', '2件を下書きにしました。');
            await checkPublic(urls, [404, 404, 404]);

            await select('A');
            await apply(page, 'published', '1件を公開しました。');
            await select('A');
            await select('B');
            const dialog = page.waitForEvent('dialog');
            await apply(page, 'trash', '2件をゴミ箱へ移動しました。');
            assert.match((await dialog).message(), /2件/);
            assert.equal(await row(page, title('A')).count(), 0);
            assert.equal(await row(page, title('B')).count(), 0);
            assert.equal(await row(page, title('C')).count(), 1);
            await checkPublic(urls, [404, 404, 404]);

            await page.goto(base + '/admin/' + target + (isPage ? '?trash=1' : '?view=trash&keyword=' + keyword));
            assert.deepEqual(await form(page).locator('select option').evaluateAll(options => options.map(option => option.value)), ['', 'restore']);
            await select('A');
            await select('B');
            await apply(page, 'restore', isPage ? '2件を下書きとして復元しました。' : '2件を復元しました。');
            await checkPublic(urls, [isPage ? 404 : 200, 404, 404]);

            await page.goto(listing);
            await row(page, title('C')).getByRole('button', {name: '公開する', exact: true}).click();
            await checkPublic(urls, [isPage ? 404 : 200, 404, 200]);
        }

        const author = await page.context().browser().newPage({viewport: {width: 1450, height: 960}});
        try {
            await author.goto(base + '/admin/login');
            await author.locator('[name="username"]').fill('author');
            await author.locator('[name="password"]').fill('Test-Password-2026');
            await Promise.all([author.waitForURL(base + '/admin'), author.locator('button[type="submit"]').click()]);
            await create(author, base, 'posts', '一括検証 posts 自分', 'bulk-posts-own');
            const listing = base + '/admin/posts?keyword=' + encodeURIComponent('一括検証 posts');
            await author.goto(listing);
            assert.equal(await box(author, '一括検証 posts A').count(), 0);
            const own = await box(author, '一括検証 posts 自分').inputValue();
            const otherEdit = await row(author, '一括検証 posts A').getByRole('link', {name: '編集', exact: true}).getAttribute('href');
            const other = otherEdit.split('/').at(-1);
            await rejected(author, base + '/admin/posts/bulk', await csrf(author), [own, other],
                '選択した投稿を操作する権限がありません。');
            await checkPublic(['/bulk-posts-own', '/bulk-posts-a'], [404, 200]);
            await box(author, '一括検証 posts 自分').check();
            await apply(author, 'published', '1件を公開しました。');
            await checkPublic(['/bulk-posts-own'], [200]);
            assert.equal((await author.request.get(base + '/admin/pages')).status(), 403);
        } finally {
            await author.close();
        }
        console.log('PASS public page links from both lists, draft visibility, bulk changes, permissions and page revisions');
    } finally {
        await visitor.close();
    }
};
