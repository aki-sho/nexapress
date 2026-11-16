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
        await page.locator('[data-page-field="page"]').selectOption({label: '既存ページ'});
        await page.locator('[data-page-field="enabled"]').check();
        const renderedSection = page.frameLocator('#nx-design-frame').locator('[data-layout-section="0"]');
        const hex = page.locator('[data-page-field="hex"]');
        assert.equal(await page.locator('[data-page-color]').count(), 15);
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
        await page.locator('[data-page-field="widthMode"]').selectOption('percent');
        await page.locator('[data-page-field="columns"]').selectOption('2');
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
        assert.deepEqual(errors, []);
        console.log('PASS: browser warning, menu editing, profile, desktop/mobile layout, and no JavaScript exceptions');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
