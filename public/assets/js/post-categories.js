document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-post-category-create]').forEach(panel => {
        const form = panel.closest('form');
        const select = form.querySelector('[name="category_id"]');
        const input = panel.querySelector('[data-new-category-name]');
        const button = panel.querySelector('[data-create-category]');
        const message = panel.querySelector('[data-category-message]');
        let busy = false;
        const report = (text, error = false) => {
            message.textContent = text;
            message.hidden = !text;
            message.classList.toggle('is-error', error);
        };
        async function create() {
            if (busy) return;
            const name = input.value.trim();
            if (!name) {
                input.setAttribute('aria-invalid', 'true');
                report('カテゴリ名を入力してください。', true);
                input.focus();
                return;
            }
            busy = true;
            button.disabled = input.disabled = true;
            panel.setAttribute('aria-busy', 'true');
            input.removeAttribute('aria-invalid');
            report('カテゴリを追加しています…');
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(panel.dataset.createUrl, {
                    method: 'POST', cache: 'no-store', credentials: 'same-origin',
                    headers: {Accept: 'application/json'}, signal: controller.signal,
                    body: new URLSearchParams({_csrf_token: form.querySelector('[name="_csrf_token"]').value, name}),
                });
                if (response.redirected || response.status === 419) {
                    throw new Error('ログインの有効期限が切れました。再ログインしてからお試しください。');
                }
                const result = await response.json().catch(() => null);
                if (!response.ok) {
                    throw new Error(result?.error || (response.status === 403
                        ? 'カテゴリを追加する権限がありません。' : 'カテゴリを追加できませんでした。'));
                }
                const category = result?.category;
                if (!Number.isInteger(category?.id) || category.id < 1 || typeof category.name !== 'string') {
                    throw new Error('追加したカテゴリを確認できませんでした。もう一度お試しください。');
                }
                let option = Array.from(select.options).find(option => option.value === String(category.id));
                if (!option) {
                    option = new Option(category.name, String(category.id));
                    select.insertBefore(option, select.options[1] || null);
                }
                select.value = String(category.id);
                select.dispatchEvent(new Event('change', {bubbles: true}));
                input.value = '';
                report(result.created ? 'カテゴリを追加し、選択しました。' : '既存のカテゴリを選択しました。');
                select.focus();
            } catch (error) {
                report(error instanceof TypeError || error.name === 'AbortError'
                    ? '通信できませんでした。もう一度お試しください。' : error.message, true);
            } finally {
                clearTimeout(timer);
                busy = false;
                button.disabled = input.disabled = false;
                panel.removeAttribute('aria-busy');
            }
        }
        button.addEventListener('click', create);
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.isComposing) {
                event.preventDefault();
                create();
            }
        });
        form.addEventListener('submit', event => {
            if (busy) {
                event.preventDefault();
                event.stopImmediatePropagation();
                report('カテゴリの追加が完了してから投稿を保存してください。');
            }
        }, true);
    });
});
