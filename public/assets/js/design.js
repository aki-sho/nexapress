(() => {
    'use strict';

    const root = document.getElementById('nx-design-editor');
    const data = JSON.parse(document.getElementById('nx-design-data').textContent);
    const panel = root.querySelector('#nx-design-panel');
    const note = root.querySelector('#nx-design-theme-note');
    const status = root.querySelector('#nx-design-status');
    const error = root.querySelector('#nx-design-error');
    const publishButton = root.querySelector('[data-action="publish"]');
    const stage = root.querySelector('#nx-design-stage');
    const canvas = root.querySelector('#nx-design-canvas');
    const frame = root.querySelector('#nx-design-frame');
    const areas = {sidebar: 'サイドバー', footer1: 'フッター 1', footer2: 'フッター 2', footer3: 'フッター 3'};
    const menus = {header: 'ヘッダー用', footer: 'フッター用'};
    const linkTypes = {page: '固定ページ', category: 'カテゴリ', custom: '任意リンク'};
    const labels = {header: 'ヘッダー', columns: '本文カラム', footer: 'フッター'};
    const clone = value => JSON.parse(JSON.stringify(value));
    const same = (left, right) => JSON.stringify(left) === JSON.stringify(right);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char =>
        ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
    const options = (pairs, current) => pairs.map(([key, label]) =>
        `<option value="${escape(key)}"${String(key) === String(current) ? ' selected' : ''}>${escape(label)}</option>`).join('');

    let theme = data.active;
    let published = Object.fromEntries(Object.entries(data.themes).map(([id, item]) => [id, item.state]));
    const layouts = Object.fromEntries(Object.entries(published).map(([id, item]) => [id, clone(item.layout)]));
    const conflicts = new Set();
    let menuItems = clone(published[theme].menus);
    let pane = 'menus';
    let menuName = 'header';
    let area = 'sidebar';
    let mode = 'draft';
    let device = 'desktop';
    let editor = null;
    let publishing = false;
    let previewTimer;
    let previewRequest;

    const state = () => ({theme, layout: layouts[theme], menus: menuItems});
    const supports = feature => data.themes[theme].areas.includes(feature);
    const activeAreas = () => Object.keys(areas).filter(key => key === 'sidebar'
        ? supports('columns') && layouts[theme].columns !== 'none'
        : supports('footer') && Number(key.slice(-1)) <= layouts[theme].footer);
    const hasChanges = id => !same(menuItems, published[id].menus) || !same(layouts[id], published[id].layout);
    const currentList = () => pane === 'menus' ? menuItems[menuName] : layouts[theme].widgets[area];
    const button = (action, label, attributes = '') =>
        `<button type="button" class="nx-design-small" data-action="${action}" ${attributes}>${label}</button>`;
    const field = (label, name, pairs, current, attributes = '') =>
        `<label>${label}<select name="${name}" ${attributes}>${options(pairs, current)}</select></label>`;

    function showError(message = '') {
        error.textContent = message;
        error.hidden = !message;
    }

    function renderStatus() {
        publishButton.disabled = publishing || editor !== null || !hasChanges(theme);
        status.textContent = publishing ? '公開中…' : (hasChanges(theme) ? '未公開の変更' : '公開済み');
        panel.disabled = publishing;
        root.querySelector('[data-setting="theme"]').disabled = publishing;
    }

    function renderRows(list, forMenu) {
        if (!list.length) {
            return `<p class="nx-design-note">${forMenu ? 'メニュー項目' : 'ウィジェット'}はまだありません。</p>`;
        }
        return `<ol class="nx-design-item-list">${list.map((item, index) => {
            const kind = (forMenu ? linkTypes : data.widgets)[item.type];
            const label = escape(item.label || item.title || kind);
            const attrs = `data-index="${index}"`;
            return `<li class="nx-design-item">
                <div><strong>${label}</strong><span class="nx-design-note">${escape(kind)}</span></div>
                <div>
                    ${button('up', '↑', `${attrs} aria-label="${label}を上へ" ${index === 0 ? 'disabled' : ''}`)}
                    ${button('down', '↓', `${attrs} aria-label="${label}を下へ" ${index === list.length - 1 ? 'disabled' : ''}`)}
                    ${button('edit', '編集', `${attrs} aria-label="${label}を編集"`)}
                    ${button('remove', '<span class="nx-design-danger">削除</span>', `${attrs} aria-label="${label}を削除"`)}
                </div></li>`;
        }).join('')}</ol>`;
    }

    function renderForm() {
        const item = editor.item;
        let fields;
        if (pane === 'menus') {
            const targets = item.type === 'page' ? data.pages : data.categories;
            const choices = [['', '選択してください'], ...targets.map(target => [target.id, target.label])];
            if (item.reference_id && !targets.some(target => target.id === item.reference_id)) {
                choices.push([item.reference_id, '現在のリンク先（非表示）']);
            }
            fields = field('種類', 'type', Object.entries(linkTypes), item.type, 'data-form-setting="type"') +
                (item.type === 'custom'
                    ? `<label>URL<input name="url" maxlength="2048" required value="${escape(item.url)}" placeholder="/ または https://example.com/"></label>`
                    : field('リンク先', 'reference_id', choices, item.reference_id, 'required')) +
                `<label>表示名<input name="label" maxlength="255" required value="${escape(item.label)}"></label>`;
        } else {
            fields = field('種類', 'type', Object.entries(data.widgets), item.type, 'data-form-setting="type"') +
                field('表示場所', 'area', activeAreas().map(id => [id, areas[id]]), item.area) +
                `<label>見出し<input name="title" maxlength="80" value="${escape(item.title)}"></label>`;
            if (['text', 'copyright'].includes(item.type)) {
                fields += `<label>テキスト<textarea rows="3" name="text" maxlength="1000">${escape(item.text)}</textarea></label>`;
            } else if (item.type === 'menu') {
                fields += field('表示するメニュー', 'menu', Object.entries(menus), item.menu);
            }
        }
        return `<form class="nx-design-form" data-designer-form>
            <h3>${editor.index < 0 ? '追加' : '編集'}</h3>${fields}
            <div class="nx-design-actions"><button class="button" type="submit">${editor.index < 0 ? '追加' : '反映'}</button>
            ${button('cancel', 'キャンセル')}</div></form>`;
    }

    function renderPanel(focus) {
        const usable = activeAreas();
        if (!usable.includes(area)) area = usable[0] ?? '';
        const layout = layouts[theme];
        const supported = data.themes[theme].areas.map(id => labels[id]);
        note.hidden = supported.length === 3 && theme === data.active;
        note.textContent = [
            theme !== data.active ? 'プレビューするテーマです。メニューはサイト共通です。' : '',
            supported.length < 3 ? (supported.length ? 'このテーマの対応範囲：' + supported.join('・') : 'このテーマはレイアウト編集に対応していません。') : '',
        ].filter(Boolean).join(' ');
        root.querySelectorAll('[data-pane]').forEach(control => {
            const key = control.dataset.pane;
            control.disabled = publishing || (key !== 'menus' && (key === 'widgets'
                ? !supports('columns') && !supports('footer') : !supports(key)));
            control.setAttribute('aria-pressed', String(key === pane));
        });

        if (pane === 'menus' || pane === 'widgets') {
            const forMenu = pane === 'menus';
            const choose = forMenu
                ? `<div class="nx-design-switch" role="group" aria-label="編集するメニュー">${Object.entries(menus).map(([id, label]) =>
                    `<button type="button" data-menu="${id}" aria-pressed="${menuName === id}">${label}</button>`).join('')}</div>`
                : field('表示場所', 'area', usable.map(id => [id, areas[id]]), area, 'data-setting="area"');
            panel.innerHTML = `<h2>${forMenu ? 'メニュー' : 'ウィジェット'}</h2>` + (forMenu || usable.length
                ? choose + renderRows(currentList(), forMenu) + (editor
                    ? renderForm() : `<div>${button('add', forMenu ? '＋ 項目を追加' : '＋ ウィジェットを追加')}</div>`)
                : '<p class="nx-design-note">本文カラムでサイドバーを選ぶと配置できます。</p>');
        } else if (pane === 'header') {
            panel.innerHTML = '<h2>ヘッダー</h2>' + field('配置', 'placement',
                [['row', '横並び'], ['stack', 'ロゴ・サイト名の下にメニュー']], layout.header.placement, 'data-setting="placement"') +
                Object.entries({logo: 'サイトアイコン', name: 'サイト名', menu: 'メニュー', search: '検索欄'}).map(([key, label]) =>
                    `<label class="nx-design-check"><input type="checkbox" data-header="${key}" ${layout.header[key] ? 'checked' : ''}> ${label}を表示</label>`).join('');
        } else if (pane === 'columns') {
            panel.innerHTML = '<h2>本文カラム</h2>' + field('レイアウト', 'columns',
                [['none', '1カラム・本文のみ'], ['left', '2カラム・左サイドバー'], ['right', '2カラム・右サイドバー']],
                layout.columns, 'data-setting="columns"') +
                (layout.columns === 'none' ? '' : `<div>${button('area', 'サイドバーの中身を編集', 'data-area="sidebar"')}</div>`);
        } else if (pane === 'footer') {
            panel.innerHTML = '<h2>フッター</h2>' + field('カラム数', 'footer',
                [[1, '1カラム'], [2, '2カラム'], [3, '3カラム']], layout.footer, 'data-setting="footer"') +
                usable.filter(id => id.startsWith('footer')).map(id =>
                    `<div>${button('area', areas[id] + 'の中身を編集', `data-area="${id}"`)}</div>`).join('');
        }
        renderStatus();
        if (focus) root.querySelector(focus)?.focus();
    }

    function resizePreview() {
        const width = device === 'mobile' ? 390 : 1080;
        const style = getComputedStyle(stage);
        const available = stage.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        if (available <= 0) return;
        const scale = Math.min(1, available / width);
        frame.style.width = width + 'px';
        frame.style.height = '1px';
        const height = Math.max(280, frame.contentDocument?.documentElement?.scrollHeight || 0);
        frame.style.height = height + 'px';
        frame.style.transform = `scale(${scale})`;
        canvas.style.width = width * scale + 'px';
        canvas.style.height = height * scale + 'px';
    }

    function syncPreviewButtons() {
        root.querySelectorAll('[data-mode]').forEach(control =>
            control.setAttribute('aria-pressed', String(control.dataset.mode === mode)));
        root.querySelectorAll('[data-device]').forEach(control =>
            control.setAttribute('aria-pressed', String(control.dataset.device === device)));
        resizePreview();
    }

    function revealPreviewArea() {
        const footer = pane === 'footer' || (pane === 'widgets' && area.startsWith('footer')) ||
            (pane === 'menus' && menuName === 'footer');
        const body = pane === 'columns' || (pane === 'widgets' && area === 'sidebar');
        const document = frame.contentDocument;
        const anchor = footer ? (document?.querySelector('.site-footer') || document?.querySelector('footer'))
            : body ? document?.querySelector('.site-main, main') : (document?.querySelector('.site-header') || document?.querySelector('header'));
        if (anchor && frame.clientWidth) {
            stage.scrollTop = anchor.getBoundingClientRect().top * canvas.clientWidth / frame.clientWidth;
        }
    }

    function body(extra) {
        return new URLSearchParams({_csrf_token: data.token, design: JSON.stringify(state()), ...extra});
    }

    async function checkResponse(response) {
        if (response.redirected || response.status === 419) {
            throw new Error('セッションの有効期限が切れました。デザイン画面を開き直してください。');
        }
        if (!response.ok) {
            const result = await response.json().catch(() => null);
            throw new Error(result?.error || 'デザイン設定を処理できませんでした。');
        }
    }

    function updatePreview() {
        clearTimeout(previewTimer);
        previewRequest?.abort();
        stage.setAttribute('aria-busy', 'true');
        previewTimer = setTimeout(async () => {
            const controller = new AbortController();
            previewRequest = controller;
            try {
                const response = await fetch(data.preview, {method: 'POST', body: body({mode}), signal: controller.signal});
                await checkResponse(response);
                const html = await response.text();
                if (!controller.signal.aborted) frame.srcdoc = html;
            } catch (cause) {
                if (controller.signal.aborted) return;
                stage.setAttribute('aria-busy', 'false');
                showError(cause.message);
            }
        }, 180);
    }

    function changed(focus) {
        mode = 'draft';
        showError();
        renderPanel(focus);
        syncPreviewButtons();
        updatePreview();
    }

    async function publish() {
        if (publishing || !hasChanges(theme)) return;
        if (conflicts.has(theme)) {
            showError('別の操作でこのテーマの設定が更新されました。画面を開き直してください。');
            return;
        }
        publishing = true;
        showError();
        renderPanel();
        try {
            const response = await fetch(data.publish, {method: 'POST', body: body({revision: published[theme].revision})});
            await checkResponse(response);
            const result = await response.json();
            const nextStates = {...published, ...result.states};
            for (const id of Object.keys(layouts)) {
                if (id === theme || same(layouts[id], published[id].layout)) {
                    layouts[id] = clone(nextStates[id].layout);
                    conflicts.delete(id);
                } else if (!same(published[id].layout, nextStates[id].layout)) {
                    conflicts.add(id);
                }
            }
            published = nextStates;
            menuItems = clone(published[theme].menus);
            editor = null;
            updatePreview();
        } catch (cause) {
            showError(cause.message);
        } finally {
            publishing = false;
            renderPanel();
        }
    }

    root.addEventListener('click', event => {
        const control = event.target.closest('button');
        if (!control || publishing) return;
        const target = control.dataset;
        if (target.pane || target.menu) {
            pane = target.pane || pane;
            menuName = target.menu || menuName;
            editor = null;
            renderPanel(target.menu ? `[data-menu="${menuName}"]` : undefined);
            revealPreviewArea();
        } else if (target.mode || target.device) {
            mode = target.mode || mode;
            device = target.device || device;
            syncPreviewButtons();
            if (target.mode) updatePreview();
            else revealPreviewArea();
        } else if (target.action === 'publish') {
            publish();
        } else if (target.action === 'area') {
            pane = 'widgets';
            area = target.area;
            editor = null;
            renderPanel('[data-setting="area"]');
            revealPreviewArea();
        } else if (target.action === 'cancel') {
            editor = null;
            renderPanel('[data-action="add"]');
        } else if (target.action) {
            const list = currentList();
            const index = Number(target.index ?? -1);
            if (target.action === 'add' || target.action === 'edit') {
                if (index < 0 && list.length >= (pane === 'menus' ? 100 : 12)) {
                    showError('登録できる項目数の上限に達しました。');
                    return;
                }
                const empty = pane === 'menus'
                    ? {id: null, type: 'page', label: '', reference_id: null, url: ''}
                    : {type: 'text', title: '', text: '', menu: 'footer', area};
                editor = {index, item: {...empty, ...list[index]}};
                renderPanel('[data-designer-form] select');
            } else if (target.action === 'remove') {
                list.splice(index, 1);
                editor = null;
                changed('[data-action="add"]');
            } else if (target.action === 'up' || target.action === 'down') {
                const next = index + (target.action === 'up' ? -1 : 1);
                [list[index], list[next]] = [list[next], list[index]];
                editor = null;
                changed(`[data-action="edit"][data-index="${next}"]`);
            }
        }
    });

    root.addEventListener('change', event => {
        if (publishing) return;
        const input = event.target;
        const {setting, header, formSetting} = input.dataset;
        if (setting === 'theme') {
            theme = input.value;
            editor = null;
            if (pane !== 'menus' && (pane === 'widgets' ? !supports('columns') && !supports('footer') : !supports(pane))) {
                pane = 'menus';
            }
            changed('[data-setting="theme"]');
        } else if (setting === 'area') {
            area = input.value;
            editor = null;
            renderPanel('[data-setting="area"]');
            revealPreviewArea();
        } else if (setting === 'placement' || header) {
            layouts[theme].header[header || setting] = header ? input.checked : input.value;
            changed(header ? `[data-header="${header}"]` : '[data-setting="placement"]');
        } else if (setting === 'columns' || setting === 'footer') {
            layouts[theme][setting] = setting === 'footer' ? Number(input.value) : input.value;
            changed(`[data-setting="${setting}"]`);
        } else if (formSetting === 'type' && editor) {
            Object.assign(editor.item, Object.fromEntries(new FormData(input.form)));
            editor.item.reference_id = null;
            renderPanel('[data-form-setting="type"]');
        }
    });

    panel.addEventListener('submit', event => {
        event.preventDefault();
        if (!editor || publishing) return;
        const values = Object.fromEntries(new FormData(event.target));
        const list = currentList();
        const index = editor.index;
        if (pane === 'menus') {
            const item = {
                id: editor.item.id, label: values.label.trim(), type: values.type,
                reference_id: values.type === 'custom' ? null : Number(values.reference_id),
                url: values.type === 'custom' ? values.url.trim() : '',
            };
            if (!item.label || (item.type === 'custom' && !/^(?:\/(?!\/)|https?:\/\/)[^\s\\]*$/i.test(item.url))) {
                showError('表示名とリンク先を確認してください。');
                return;
            }
            if (index < 0) list.push(item);
            else list[index] = item;
        } else {
            const destination = layouts[theme].widgets[values.area];
            if ((destination !== list || index < 0) && destination.length >= 12) {
                showError('この場所には12個まで配置できます。');
                return;
            }
            const item = {type: values.type, title: values.title.trim(), text: values.text?.trim() || '', menu: values.menu || 'footer'};
            if (destination === list && index >= 0) list[index] = item;
            else {
                if (index >= 0) list.splice(index, 1);
                destination.push(item);
            }
            area = values.area;
        }
        editor = null;
        changed('[data-action="add"]');
    });

    window.addEventListener('beforeunload', event => {
        if (!editor && !Object.keys(layouts).some(hasChanges)) return;
        event.preventDefault();
        event.returnValue = '';
    });
    frame.addEventListener('load', () => {
        resizePreview();
        revealPreviewArea();
        stage.setAttribute('aria-busy', 'false');
    });
    window.addEventListener('resize', resizePreview);
    renderPanel();
    syncPreviewButtons();
    updatePreview();
})();
