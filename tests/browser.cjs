const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');

(async () => {
    const base = process.env.NEXAPRESS_TEST_URL;
    const output = process.env.NEXAPRESS_TEST_OUTPUT;
    const executablePath = process.env.NEXAPRESS_TEST_CHROME || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    const browser = await chromium.launch({ executablePath, headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    try {
        await page.goto(base + '/admin/login');
        await page.locator('[name="username"]').fill('admin');
        await page.locator('[name="password"]').fill('Test-Password-2026');
        await Promise.all([page.waitForURL(base + '/admin'), page.locator('button[type="submit"]').click()]);
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
            console.log('PASS: browser ' + kind + ' draft recovery and successful-save cleanup');
        }
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
        assert.deepEqual(errors, []);
        console.log('PASS: browser warning, menu editing, profile, desktop/mobile layout, and no JavaScript exceptions');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
