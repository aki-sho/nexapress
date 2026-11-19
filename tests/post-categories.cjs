const assert = require('assert/strict');
const path = require('path');

module.exports = async function checkPostCategories(page, base, output) {
    const endpoint = base + '/admin/categories/inline';
    await page.goto(base + '/admin/posts/create');
    const form = page.locator('form[data-editor-kind="post"]');
    const select = form.locator('[name="category_id"]');
    const panel = form.locator('[data-post-category-create]');
    const name = panel.locator('[data-new-category-name]');
    const add = panel.locator('[data-create-category]');
    const message = panel.locator('[data-category-message]');
    const title = 'カテゴリ追加の入力保持テスト';
    await form.locator('[name="title"]').fill(title);
    await form.locator('[name="slug"]').fill('inline-category-post');
    const visual = form.locator('.classic-editor-content');
    if (await visual.count()) await visual.fill('カテゴリを追加しても残る本文');
    else await form.locator('[name="content"]').fill('カテゴリを追加しても残る本文');
    const body = await form.locator('[name="content"]').inputValue();
    const token = await form.locator('[name="_csrf_token"]').inputValue();
    const create = values => page.request.post(endpoint, {form: {_csrf_token: token, ...values}});
    assert.equal((await create({_csrf_token: 'bad', name: '拒否されるカテゴリ'})).status(), 419);
    for (const values of [{name: ''}, {name: 'あ'.repeat(101)}, {'name[]': 'array'}]) {
        assert.equal((await create(values)).status(), 422);
    }
    const collision1 = await (await create({name: 'Inline category'})).json();
    const collision2 = await (await create({name: 'Inline-category'})).json();
    assert.notEqual(collision1.category.slug, collision2.category.slug);
    assert.notEqual(collision1.category.id, collision2.category.id);
    await panel.locator('> summary').click();
    await name.fill('記事を書きながら追加');
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route(endpoint, async route => { await gate; await route.continue(); });
    const pending = page.waitForResponse(endpoint);
    await add.click();
    const blockedPostSave = await form.evaluate(form => {
        if (!form.checkValidity()) return false;
        let submitted;
        document.addEventListener('submit', event => { submitted = event; }, {capture: true, once: true});
        form.requestSubmit();
        return submitted?.defaultPrevented === true;
    });
    release();
    assert.equal(blockedPostSave, true);
    const response = await pending;
    await page.unroute(endpoint);
    assert.equal(response.status(), 201);
    const category = (await response.json()).category;
    await page.waitForFunction(id => document.querySelector('#category_id').value === String(id), category.id);
    assert.equal(await form.locator('[name="title"]').inputValue(), title);
    assert.equal(await form.locator('[name="content"]').inputValue(), body);
    assert.equal(page.url(), base + '/admin/posts/create');
    await page.waitForFunction(id => {
        const field = document.querySelector('[name="_editor_draft_key"]');
        const saved = field && JSON.parse(localStorage.getItem(field.value));
        return saved?.data.category_id === String(id);
    }, category.id);
    await name.fill('記事を書きながら追加');
    const [duplicate] = await Promise.all([page.waitForResponse(endpoint), name.press('Enter')]);
    assert.equal(duplicate.status(), 200);
    assert.equal((await duplicate.json()).category.id, category.id);
    await page.waitForFunction(() => document.querySelector('[data-category-message]').textContent.includes('既存'));
    assert.equal(await select.locator('option[value="' + category.id + '"]').count(), 1);
    await page.route(endpoint, route => route.fulfill({
        status: 503, contentType: 'application/json', body: JSON.stringify({error: '通信エラーの確認'}),
    }));
    await name.fill('再試行するカテゴリ');
    await add.click();
    await page.waitForFunction(() => document.querySelector('[data-category-message]').textContent.includes('通信エラー'));
    assert.equal(await name.inputValue(), '再試行するカテゴリ');
    assert.equal(await select.inputValue(), String(category.id));
    assert.equal(await form.locator('[name="content"]').inputValue(), body);
    assert.equal(await add.isEnabled(), true);
    await page.unroute(endpoint);
    const [retry] = await Promise.all([page.waitForResponse(endpoint), add.click()]);
    assert.equal(retry.status(), 201);
    const retryCategory = (await retry.json()).category;
    await page.waitForFunction(id => document.querySelector('#category_id').value === String(id), retryCategory.id);
    await select.locator('..').screenshot({path: path.join(output, 'post-inline-category.png')});
    assert.equal(await message.getAttribute('role'), 'status');
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/create')),
        form.locator('button[type="submit"]').last().click(),
    ]);
    const row = page.getByRole('row').filter({hasText: title});
    await row.locator('a[href*="/admin/posts/edit/"]').first().click();
    assert.equal(await select.inputValue(), String(retryCategory.id));
    assert((await form.locator('[name="content"]').inputValue()).includes('カテゴリを追加しても残る本文'));
    await panel.locator('> summary').click();
    await name.fill('編集画面でも追加');
    const [edited] = await Promise.all([page.waitForResponse(endpoint), add.click()]);
    assert.equal(edited.status(), 201);
    const editedCategory = (await edited.json()).category;
    await page.waitForFunction(id => document.querySelector('#category_id').value === String(id), editedCategory.id);
    await Promise.all([
        page.waitForURL(url => !url.pathname.includes('/edit/')),
        form.locator('button[type="submit"]').last().click(),
    ]);
    await page.getByRole('row').filter({hasText: title}).locator('a[href*="/admin/posts/edit/"]').first().click();
    assert.equal(await select.inputValue(), String(editedCategory.id));
    for (const [username, allowed] of [['editor', true], ['author', false]]) {
        const context = await page.context().browser().newContext();
        const user = await context.newPage();
        await user.goto(base + '/admin/login');
        await user.locator('[name="username"]').fill(username);
        await user.locator('[name="password"]').fill('Test-Password-2026');
        await Promise.all([user.waitForURL(base + '/admin'), user.locator('button[type="submit"]').click()]);
        await user.goto(base + '/admin/posts/create');
        assert.equal(await user.locator('[data-post-category-create]').count(), allowed ? 1 : 0);
        const csrf = await user.locator('form[data-editor-kind="post"] [name="_csrf_token"]').inputValue();
        const result = await user.request.post(endpoint, {form: {_csrf_token: csrf, name: '権限確認 ' + username}});
        assert.equal(result.status(), allowed ? 201 : 403);
        await context.close();
    }
    const guest = await page.context().browser().newContext();
    const rejected = await guest.request.post(endpoint, {form: {name: '未ログインの追加'}});
    assert(rejected.url().endsWith('/admin/login'));
    await guest.close();
    console.log('PASS: inline categories on new/edit posts, selected and persisted IDs, editor content and autosave, duplicate/collision handling, retry, CSRF and permissions');
};
