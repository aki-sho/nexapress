(() => {
    'use strict';

    const root = document.getElementById('nx-design-editor');
    const data = JSON.parse(document.getElementById('nx-design-data').textContent);
    const panel = root.querySelector('#nx-design-panel');
    const note = root.querySelector('#nx-design-theme-note');
    const status = root.querySelector('#nx-design-status');
    const error = root.querySelector('#nx-design-error');
    const publishButton = root.querySelector('[data-action="publish"]');
    const resetButton = root.querySelector('[data-action="reset"]');
    const stage = root.querySelector('#nx-design-stage');
    const canvas = root.querySelector('#nx-design-canvas');
    const frame = root.querySelector('#nx-design-frame');
    const {linkTypes, targets, regions} = data;
    const regionIds = Object.keys(regions);
    const linkTypeIds = Object.keys(linkTypes);
    const areas = data.widgetAreas;
    const menus = {header: 'ヘッダーのリンク', footer: 'フッターのリンク'};
    const labels = {header: 'ヘッダー', header_rows: 'ヘッダーの段構成', columns: '本文カラム',
        footer: 'フッター', footer_rows: 'フッターの上下分割', page_layout: 'ページ毎のレイアウト', ...regions};
    const designColor = window.NexaPressDesignColor;
    const chromeOpen = new Map();
    const selectedWidgetAreas = {header: 'header_after', columns: 'sidebar', footer: 'footer_before'};
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
    let background = clone(published[theme].background);
    let pane = 'header';
    let mode = 'draft';
    let device = 'desktop';
    let editor = null;
    let publishing = false;
    let uploadingImage = false;
    let resetRevision = data.resetRevision;
    let resetDraft = null;
    let previewTimer;
    let previewRequest;

    const supports = feature => data.themes[theme].areas.includes(feature);
    const state = () => ({
        theme, layout: layouts[theme], menus: menuItems, background,
        contentLayouts: pageEditor.commonPayload(),
        content: pageEditor.payload(),
    });
    function chromeRows(kind) {
        const layout = layouts[theme];
        const saved = layout[kind + 'Rows'];
        if (saved) return saved;
        if (kind === 'footer') return data.chromeDefaults.footer;
        if (layout.header.placement === 'stack') {
            const stacked = data.chromeDefaults.stackedHeader;
            return {...stacked, split: layout.headerBelow,
                positions: Object.fromEntries(Object.keys(stacked.positions).map(part => [part, {row: 'top', column: 1}]))};
        }
        return {...data.chromeDefaults.header, split: layout.headerBelow};
    }
    function ensureChrome(kind) {
        const layout = layouts[theme];
        const key = kind + 'Rows';
        if (!layout[key]) {
            layout[key] = clone(chromeRows(kind));
            if (kind === 'header') layout.headerBelow = false;
        }
        return layout[key];
    }
    const activeAreas = () => Object.keys(areas).filter(key => {
        if (regionIds.includes(key)) return supports(key);
        if (key === 'sidebar') return supports('columns') && layouts[theme].columns !== 'none';
        if (key === 'header_above') return supports('header_rows') && layouts[theme].headerAbove;
        const match = /^(header|footer)(_bottom)?([1-5])$/.exec(key);
        if (!match) return false;
        const [, kind, bottom, number] = match;
        if (!supports(kind === 'header' || bottom ? kind + '_rows' : kind)) return false;
        const rows = chromeRows(kind);
        return (!bottom || rows.split) && Number(number) <=
            (kind === 'footer' && !bottom ? layouts[theme].footer : rows[bottom ? 'bottom' : 'top'].columns);
    });
    const widgetAreaLabel = area => area === 'header_bottom1' && chromeRows('header').bottom.columns === 1 ? '下段' : areas[area];
    const hasChanges = id => !same(menuItems, published[id].menus) || !same(layouts[id], published[id].layout);
    const widgetPane = area => area === 'sidebar' ? 'columns' : area.startsWith('header') ? 'header' : 'footer';
    const currentList = list => list === 'links' ? menuItems[pane] : layouts[theme].widgets[selectedWidgetAreas[pane]];
    const itemControl = (list, selector) => '[data-chrome-details="' + pane + ':' + list + '"] ' + selector;
    const categoryIds = item => item.reference_ids ?? (item.reference_id ? [item.reference_id] : []);
    const button = (action, label, attributes = '') =>
        `<button type="button" class="nx-design-small" data-action="${action}" ${attributes}>${label}</button>`;
    const field = (label, name, pairs, current, attributes = '') =>
        `<label>${label}<select name="${name}" ${attributes}>${options(pairs, current)}</select></label>`;
    const pageEditor = window.NexaPressPageEditor({
        panel, data, field, escape, changed, showError, checkResponse,
        redraw: focus => pane === 'page_layout' ? renderPanel(focus) : renderStatus(),
        preview: updatePreview, reveal: revealPreviewArea,
        themeWidth: () => layouts[theme].width,
        previewMode: () => mode,
    });
    const pageChanges = () => pageEditor.hasChanges();
    const currentChanges = () => hasChanges(theme) || !same(background, published[theme].background) || pageChanges();

    designColor.bind(panel, 'site', () => background.color, color => {
        background.color = color;
        changed(undefined, false);
    }, showError);

    function showError(message = '') {
        error.textContent = message;
        error.hidden = !message;
    }

    function renderStatus() {
        const canPublish = resetDraft ? resetDraft.ready && stage.getAttribute('aria-busy') === 'false' : currentChanges();
        publishButton.disabled = publishing || uploadingImage || editor !== null || !canPublish;
        resetButton.disabled = publishing || uploadingImage || editor !== null;
        resetButton.textContent = resetDraft ? '初期化を取り消す' : 'デザインを初期状態に戻す';
        status.textContent = uploadingImage ? '画像を登録中…' : publishing ? '公開中…' : resetDraft ? '初期化プレビュー' : currentChanges() ? '未公開の変更'
            : pageEditor.hasAnyChanges() ? '別のページに未公開の変更' : '公開済み';
        panel.disabled = publishing;
        root.querySelector('[data-setting="theme"]').disabled = publishing;
    }

    function renderRows(list, forMenu) {
        if (!list.length) {
            return `<p class="nx-design-note">${forMenu ? 'リンク' : 'ウィジェット'}はまだありません。</p>`;
        }
        return `<ol class="nx-design-item-list">${list.map((item, index) => {
            const kind = (forMenu ? linkTypes : data.widgets)[item.type];
            const target = targets[item.type]?.find(choice => choice.id === item.reference_id);
            const count = !forMenu && item.type === 'category' ? categoryIds(item).length : 0;
            const label = escape(item.label || item.title || (count > 1 ? 'カテゴリ（' + count + '件）' : target?.label) || kind);
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

    function linkField(item) {
        if (item.type === 'custom') {
            return `<label>URL<input name="url" maxlength="2048" required value="${escape(item.url)}"
                placeholder="/ または https://example.com/"></label>`;
        }
        const available = targets[item.type];
        const choices = [['', '選択してください'], ...available.map(target => [target.id, target.label])];
        if (item.reference_id && !available.some(target => target.id === item.reference_id)) {
            choices.push([item.reference_id, '現在のリンク先（非表示）']);
        }
        return field('リンク先', 'reference_id', choices, item.reference_id, 'required');
    }

    function categoryField(item) {
        const selected = categoryIds(item);
        const choices = [...targets.category];
        selected.filter(id => !choices.some(choice => choice.id === id)).forEach(id =>
            choices.push({id, label: '現在のカテゴリ（削除済み）'}));
        return `<fieldset class="nx-design-category-picker"><legend>カテゴリ（複数選択可）
            <span class="nx-design-note" data-category-count>${selected.length}件</span></legend>
            <div class="nx-design-category-choices">${choices.length ? choices.map(choice =>
                `<label class="nx-design-check"><input type="checkbox" name="reference_ids" value="${choice.id}"
                    ${selected.includes(choice.id) ? 'checked' : ''}>${escape(choice.label)}</label>`).join('') :
                '<p class="nx-design-note">カテゴリを作成してください。</p>'}</div></fieldset>`;
    }

    const categoryTitleLabel = count => count > 1 ? '見出し（任意）' : '表示名（省略するとカテゴリ名）';

    function renderForm() {
        const item = editor.item;
        let fields;
        if (editor.list === 'links') {
            fields = field('種類', 'type', Object.entries(linkTypes), item.type, 'data-form-setting="type"') +
                linkField(item) +
                `<label>表示名<input name="label" maxlength="255" required value="${escape(item.label)}"></label>`;
        } else {
            const destinations = activeAreas();
            const destination = destinations.includes(item.area) ? item.area : selectedWidgetAreas[pane];
            fields = field('種類', 'type', Object.entries(data.widgets), item.type, 'data-form-setting="type"') +
                field('表示場所', 'area', destinations.map(id => [id, widgetAreaLabel(id)]), destination, 'required');
            if (item.type === 'category') {
                fields += categoryField(item) + `<label><span data-category-title-label>${categoryTitleLabel(categoryIds(item).length)}</span>
                    <input name="title" maxlength="80" value="${escape(item.title)}"></label>`;
            } else if (linkTypeIds.includes(item.type)) {
                fields += linkField(item) + `<label>${item.type === 'custom' ? '表示名' : '表示名（省略するとリンク先の名前）'}
                    <input name="title" maxlength="80" value="${escape(item.title)}" ${item.type === 'custom' ? 'required' : ''}></label>`;
            } else {
                fields += `<label>見出し<input name="title" maxlength="80" value="${escape(item.title)}"></label>`;
            }
            if (['text', 'copyright'].includes(item.type)) {
                fields += `<label>テキスト<textarea rows="3" name="text" maxlength="1000">${escape(item.text)}</textarea></label>`;
            } else if (item.type === 'menu') {
                fields += field('表示するリンク', 'menu', Object.entries(menus), item.menu);
            }
        }
        return `<form class="nx-design-form" data-designer-form>
            <h3>${editor.index < 0 ? '追加' : '編集'}</h3>${fields}
            <div class="nx-design-actions"><button class="button" type="submit">${editor.index < 0 ? '追加' : '反映'}</button>
            ${button('cancel', 'キャンセル')}</div></form>`;
    }

    function chromeGroup(kind, key, title, content, open = false) {
        const id = kind + ':' + key;
        return `<details class="nx-chrome-settings" data-chrome-details="${id}" ${(chromeOpen.get(id) ?? open) ? 'open' : ''}>
            <summary>${title}</summary><div class="nx-chrome-fields">${content}</div></details>`;
    }

    function renderList(list, kind) {
        const links = kind === 'links';
        return renderRows(list, links) + (editor?.list === kind ? renderForm() :
            '<div>' + button('add', links ? '＋ 項目を追加' : '＋ ウィジェットを追加') + '</div>');
    }

    function widgetGroup(kind) {
        const available = activeAreas().filter(area => widgetPane(area) === kind);
        if (!available.includes(selectedWidgetAreas[kind])) {
            if (editor?.list === 'widgets') editor = null;
            selectedWidgetAreas[kind] = available[0];
        }
        if (!available.length) return '';

        const area = selectedWidgetAreas[kind];
        const sidebar = kind === 'columns';
        const picker = sidebar || editor?.list === 'widgets' ? '' :
            field('表示場所', 'widget-area', available.map(id => [id, widgetAreaLabel(id)]), area, 'data-setting="area"');
        return chromeGroup(kind, 'widgets', sidebar ? 'サイドバーのウィジェット' : 'ウィジェット',
            picker + renderList(layouts[theme].widgets[area], 'widgets'));
    }

    function headerStructure(hasAbove, hasBelow) {
        const toggle = (row, label, direction, enabled) => {
            const attributes = 'data-row="' + row + '"';
            return enabled ? '<div class="nx-header-row-label"><strong>' + label + '</strong>' +
                button('header-row', '削除', attributes + ' aria-label="' + label + 'を削除"') + '</div>'
                : button('header-row', '＋ ' + direction + 'に段を追加', attributes);
        };
        return '<div class="nx-header-structure">' + toggle('above', '上段', '上', hasAbove) +
            '<div class="nx-header-base-row"><strong>ヘッダー本体</strong><span class="nx-design-note">サイト名・メニュー・検索欄</span></div>' +
            toggle('below', '下段', '下', hasBelow) + '</div>';
    }

    function chromePanel(kind) {
        const layout = layouts[theme];
        const rows = chromeRows(kind);
        const title = kind === 'header' ? 'ヘッダー' : 'フッター';
        const capable = supports(kind + '_rows');
        const columnChoices = Array.from({length: 5}, (_, index) => [index + 1, (index + 1) + 'カラム']);
        const control = (label, row, name, choices, value) => field(label, kind + '-' + row + '-' + name, choices, value,
            `data-chrome="${kind}" data-chrome-row="${row}" data-chrome-field="${name}"` +
            (kind === 'footer' && row === 'top' && name === 'columns' ? ' data-setting="footer"' : ''));
        let html = '<h2>' + title + '</h2>';
        if (supports(kind)) {
            const width = kind + 'Width';
            html += field(title + 'の幅', width, Object.entries(data.chromeWidths), layout[width], 'data-setting="' + width + '"');
        }
        html += chromeGroup(kind, 'links', 'リンク', renderList(menuItems[kind], 'links'));
        if (!supports(kind)) return html + widgetGroup(kind);
        if (capable) {
            const structure = kind === 'header' ? headerStructure(layout.headerAbove, rows.split)
                : control('段数', '', 'split', [[1, '1段'], [2, '上下2段']], rows.split ? 2 : 1);
            html += chromeGroup(kind, 'structure', '段構成', structure, true);
            for (const row of rows.split ? ['top', 'bottom'] : ['top']) {
                const count = kind === 'footer' && row === 'top' ? layout.footer : rows[row].columns;
                const label = row === 'bottom' ? '下段' : kind === 'header' ? 'ヘッダー本体' : '上段';
                html += chromeGroup(kind, row, label + 'のカラム・配置',
                    '<div class="nx-chrome-form-row">' + control('カラム数', row, 'columns', columnChoices, count) +
                    control('配置', row, 'align', Object.entries(data.alignments), rows[row].align) + '</div>', kind === 'footer');
            }
        } else if (kind === 'footer') {
            html += field('カラム数', 'footer', columnChoices, layout.footer, 'data-setting="footer"');
        } else {
            html += field('配置', 'placement', [['row', '横並び'], ['stack', 'ロゴ・サイト名の下にメニュー']],
                layout.header.placement, 'data-setting="placement"');
        }
        html += widgetGroup(kind);
        let content = '';
        if (kind === 'header') {
            const positions = (rows.split ? ['top', 'bottom'] : ['top']).flatMap(row =>
                Array.from({length: rows[row].columns}, (_, index) =>
                    [row + ':' + (index + 1), widgetAreaLabel((row === 'top' ? 'header' : 'header_bottom') + (index + 1))]));
            for (const [part, label, flags] of [
                ['brand', 'サイト情報', {logo: 'サイトアイコン', name: 'サイト名'}],
                ['menu', 'メニュー', {menu: 'メニュー'}], ['search', '検索欄', {search: '検索欄'}],
            ]) {
                content += '<div class="nx-chrome-part"><strong>' + label + '</strong>' +
                    Object.entries(flags).map(([key, text]) => `<label class="nx-design-check">
                        <input type="checkbox" data-header="${key}" ${layout.header[key] ? 'checked' : ''}> ${text}を表示</label>`).join('');
                if (capable) content += field('表示場所', kind + '-' + part, positions,
                    rows.positions[part].row + ':' + rows.positions[part].column,
                    `data-chrome="header" data-chrome-field="position" data-chrome-part="${part}"`);
                content += '</div>';
            }
        }
        return kind === 'header' ? html + chromeGroup(kind, 'content', '中身の配置', content) : html;
    }

    function generalPanel() {
        const modes = [['theme', 'テーマの既定'], ['color', '背景色'], ['image', '背景画像']];
        let html = '<h2>全体設定</h2>' +
            field('サイト全体の背景', 'background-mode', modes, background.mode, 'data-setting="background-mode"');
        if (background.mode === 'color') {
            html += designColor.fields(background.color, 'site', escape);
        } else if (background.mode === 'image') {
            html += field('背景画像', 'background-image', [['', '画像を選択'], ...data.pageLayout.images.map(item => [item.id, item.label])],
                background.media_id ?? '', 'data-setting="background-image"' + (uploadingImage ? ' disabled' : ''));
            html += '<label>画像を追加<input type="file" data-setting="background-file" ' +
                'accept="image/jpeg,image/png,image/gif,image/webp"' + (uploadingImage ? ' disabled' : '') + '></label>';
            html += '<div><a href="' + escape(data.media) + '" target="_blank" rel="noopener">メディアを開く</a></div>';
        }
        return html;
    }

    panel.addEventListener('toggle', event => {
        const id = event.target.dataset.chromeDetails;
        if (id && event.target.isConnected) {
            chromeOpen.set(id, event.target.open);
            if (id === pane + ':widgets' && event.target.open) revealPreviewArea();
        }
    }, true);

    function renderPanel(focus) {
        panel.querySelectorAll('[data-chrome-details]').forEach(details =>
            chromeOpen.set(details.dataset.chromeDetails, details.open));
        const layout = layouts[theme];
        const supported = data.themes[theme].areas.map(id => labels[id]);
        const partial = supported.length < Object.keys(labels).length;
        note.hidden = !partial && theme === data.active;
        note.textContent = [
            theme !== data.active ? 'プレビューするテーマです。メニューはサイト共通です。' : '',
            partial ? (supported.length ? 'このテーマの対応範囲：' + supported.join('・') : 'このテーマはレイアウト編集に対応していません。') : '',
        ].filter(Boolean).join(' ');
        root.querySelectorAll('[data-pane]').forEach(control => {
            const key = control.dataset.pane;
            control.disabled = publishing || Boolean(resetDraft) || (!['header', 'footer', 'general'].includes(key) && !supports(key));
            control.setAttribute('aria-pressed', String(key === pane));
        });

        if (resetDraft) {
            const choices = [['', 'トップページ']];
            for (const [kind, label] of [['page', '固定ページ'], ['post', '投稿']]) {
                for (const item of targets[kind]) choices.push([kind + ':' + item.id, label + ' · ' + item.label]);
            }
            const target = resetDraft.target;
            panel.innerHTML = '<h2>初期状態の確認</h2>' +
                '<p class="nx-design-note">サイト全体の背景、全テーマのデザイン、リンク、ウィジェット、ページレイアウトが初期状態に戻ります。「公開」で確定します。</p>' +
                field('プレビューするページ', 'reset-target', choices, target.id ? target.kind + ':' + target.id : '', 'data-setting="reset-target"');
        } else if (pane === 'header' || pane === 'footer') {
            panel.innerHTML = chromePanel(pane);
        } else if (pane === 'columns') {
            panel.innerHTML = '<h2>本文カラム</h2>' + (supports('page_layout')
                ? field('テーマの本文幅', 'width', pageEditor.widths, layout.width, 'data-setting="width"') : '') + field('レイアウト', 'columns',
                [['none', '1カラム・本文のみ'], ['left', '2カラム・左サイドバー'], ['right', '2カラム・右サイドバー']],
                layout.columns, 'data-setting="columns"') + widgetGroup('columns');
        } else if (pane === 'page_layout') {
            panel.innerHTML = pageEditor.render();
        } else if (pane === 'general') {
            panel.innerHTML = generalPanel();
        }
        renderStatus();
        if (focus) root.querySelector(focus)?.focus();
    }

    function resizePreview() {
        const wide = supports('page_layout') || supports('header') || supports('footer');
        const width = device === 'mobile' ? 390 : wide ? 1600 : 1080;
        const style = getComputedStyle(stage);
        const available = stage.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        if (available <= 0) return;
        const scale = Math.min(1, available / width);
        frame.style.width = width + 'px';
        frame.style.height = canvas.clientHeight / scale + 'px';
        frame.style.transform = `scale(${scale})`;
        canvas.style.width = width * scale + 'px';
    }

    function syncPreviewButtons() {
        root.querySelector('[data-mode="draft"]').textContent = resetDraft ? '初期状態' : '編集中';
        root.querySelectorAll('[data-mode]').forEach(control =>
            control.setAttribute('aria-pressed', String(control.dataset.mode === mode)));
        root.querySelectorAll('[data-device]').forEach(control =>
            control.setAttribute('aria-pressed', String(control.dataset.device === device)));
        resizePreview();
        pageEditor.refreshPreviewStatus();
    }

    function revealPreviewArea() {
        if (resetDraft) return;
        const footer = pane === 'footer';
        const body = pane === 'columns';
        const document = frame.contentDocument;
        const widgetGroupOpen = panel.querySelector('[data-chrome-details="' + pane + ':widgets"][open]');
        const area = widgetGroupOpen && editor?.list !== 'links' ? selectedWidgetAreas[pane] : null;
        const headerSlot = pane === 'header' && area && !regionIds.includes(area);
        const region = area && !headerSlot
            ? document?.querySelector(regionIds.includes(area) ? `[data-design-area="${area}"]` : `[data-widget-area="${area}"]`)
            : pane === 'page_layout' ? document?.querySelector(`[data-layout-section="${pageEditor.sectionIndex()}"]`) : null;
        const anchor = region || (footer ? (document?.querySelector('.site-footer') || document?.querySelector('footer'))
            : body ? document?.querySelector('.site-main, main') : (document?.querySelector('.site-header') || document?.querySelector('header')));
        const scroller = document?.scrollingElement;
        if (anchor && scroller) {
            scroller.scrollTop += anchor.getBoundingClientRect().top;
        }
    }

    function body(extra) {
        const target = resetDraft?.target ?? pageEditor.target();
        const design = resetDraft ? {reset: '1', theme} : {design: JSON.stringify(state())};
        return new URLSearchParams({
            _csrf_token: data.token, ...design,
            content_kind: target.kind,
            content_id: resetDraft || supports('page_layout') ? target.id : '', ...extra,
        });
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

    async function uploadBackgroundImage(input) {
        const file = input.files[0];
        if (!file || uploadingImage) return;
        uploadingImage = true;
        input.disabled = true;
        panel.querySelector('[data-setting="background-image"]').disabled = true;
        showError();
        renderStatus();
        const request = new FormData();
        request.set('_csrf_token', data.token);
        request.set('media_file', file);
        try {
            const response = await fetch(data.imageUpload, {method: 'POST', body: request});
            await checkResponse(response);
            const {image} = await response.json();
            if (!Number.isInteger(image?.id) || image.id < 1) throw new Error('画像を確認できませんでした。');
            data.pageLayout.images.unshift({id: image.id, label: image.title});
            background.media_id = image.id;
            changed('[data-setting="background-image"]');
        } catch (cause) {
            showError(cause.message);
        } finally {
            uploadingImage = false;
            const field = panel.querySelector('[data-setting="background-file"]');
            if (field) {
                field.disabled = false;
                field.value = '';
                panel.querySelector('[data-setting="background-image"]').disabled = false;
            }
            renderStatus();
        }
    }

    function updatePreview() {
        clearTimeout(previewTimer);
        previewRequest?.abort();
        stage.setAttribute('aria-busy', 'true');
        if (resetDraft) {
            resetDraft.ready = false;
            renderStatus();
        }
        previewTimer = setTimeout(async () => {
            const controller = new AbortController();
            previewRequest = controller;
            try {
                const response = await fetch(data.preview, {method: 'POST', body: body({mode}), signal: controller.signal});
                await checkResponse(response);
                const html = await response.text();
                if (!controller.signal.aborted) {
                    if (resetDraft) resetDraft.ready = mode === 'draft';
                    frame.srcdoc = html;
                }
            } catch (cause) {
                if (controller.signal.aborted) return;
                stage.setAttribute('aria-busy', 'false');
                if (mode === 'published') {
                    frame.srcdoc = `<!doctype html><html lang="ja"><meta charset="UTF-8">
                        <p style="font:24px sans-serif;margin:2rem">${escape(cause.message)}</p></html>`;
                }
                showError(cause.message);
            }
        }, 180);
    }

    function rememberForm(form = panel.querySelector('[data-designer-form]')) {
        if (!editor || !form) return;
        const formData = new FormData(form);
        const draft = Object.fromEntries(formData);
        if ('reference_id' in draft) draft.reference_id = Number(draft.reference_id) || null;
        if (editor.list === 'widgets' && draft.type === 'category') {
            draft.reference_ids = formData.getAll('reference_ids').map(Number);
        }
        Object.assign(editor.item, draft);
    }

    function changed(focus, redraw = true) {
        if (redraw) rememberForm();
        mode = 'draft';
        showError();
        if (redraw) renderPanel(focus);
        else renderStatus();
        syncPreviewButtons();
        updatePreview();
    }

    function toggleResetPreview() {
        if (resetDraft) {
            theme = resetDraft.previousTheme;
            mode = resetDraft.previousMode;
            resetDraft = null;
            root.querySelector('[data-setting="theme"]').value = theme;
        } else {
            resetDraft = {previousTheme: theme, previousMode: mode, target: pageEditor.target(), ready: false};
            mode = 'draft';
        }
        changedResetPreview();
    }

    function changedResetPreview() {
        showError();
        renderPanel();
        syncPreviewButtons();
        updatePreview();
    }

    async function publish() {
        if (publishButton.disabled) return;
        if (resetDraft && !window.confirm('全テーマのデザインを初期状態に戻して公開しますか？登録済みのリンク、ウィジェット、ページのレイアウト設定も初期化されます。')) return;
        if (!resetDraft && conflicts.has(theme)) {
            showError('別の操作でこのテーマの設定が更新されました。画面を開き直してください。');
            return;
        }
        publishing = true;
        showError();
        renderPanel();
        try {
            const revision = resetDraft ? resetRevision : published[theme].revision;
            const response = await fetch(data.publish, {method: 'POST', body: body({revision})});
            await checkResponse(response);
            const result = await response.json();
            if (resetDraft) {
                window.removeEventListener('beforeunload', warnBeforeUnload);
                window.location.reload();
                return;
            }
            resetRevision = result.resetRevision;
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
            background = clone(published[theme].background);
            pageEditor.saved(result);
            editor = null;
            updatePreview();
        } catch (cause) {
            showError(cause.message);
        } finally {
            publishing = false;
            renderPanel();
        }
    }

    function toggleHeaderRow(row) {
        const layout = layouts[theme];
        const above = row === 'above';
        const enabled = above ? !layout.headerAbove : !chromeRows('header').split;
        if (above) {
            layout.headerAbove = enabled;
        } else if (layout.headerRows) {
            const rows = layout.headerRows;
            rows.split = enabled;
            layout.header.placement = enabled ? 'stack' : 'row';
            if (!enabled) {
                for (const position of Object.values(rows.positions)) {
                    if (position.row === 'bottom') {
                        position.row = 'top';
                        position.column = Math.min(position.column, rows.top.columns);
                    }
                }
            }
        } else {
            layout.headerBelow = enabled;
        }
        if (enabled && !editor) {
            selectedWidgetAreas.header = above ? 'header_above' : 'header_bottom1';
            const group = panel.querySelector('[data-chrome-details="header:widgets"]');
            if (group) group.open = true;
            else chromeOpen.set('header:widgets', true);
        }
        changed('[data-action="header-row"][data-row="' + row + '"]');
    }

    root.addEventListener('click', event => {
        const control = event.target.closest('button');
        if (!control || publishing) return;
        const target = control.dataset;
        if (target.pane) {
            pane = target.pane;
            editor = null;
            renderPanel();
            revealPreviewArea();
        } else if (target.mode || target.device) {
            mode = target.mode || mode;
            device = target.device || device;
            syncPreviewButtons();
            if (target.mode) updatePreview();
            else revealPreviewArea();
        } else if (target.action === 'publish') {
            publish();
        } else if (target.action === 'reset') {
            if (!resetButton.disabled) toggleResetPreview();
        } else if (target.action === 'header-row') {
            toggleHeaderRow(target.row);
        } else if (target.action) {
            const group = control.closest('[data-chrome-details]');
            const kind = group?.dataset.chromeDetails.split(':')[1];
            if (kind !== 'links' && kind !== 'widgets') return;
            const list = currentList(kind);
            const forMenu = kind === 'links';
            const add = itemControl(kind, '[data-action="add"]');
            const index = Number(target.index ?? -1);
            if (target.action === 'cancel') {
                editor = null;
                showError();
                renderPanel(add);
            } else if (target.action === 'add' || target.action === 'edit') {
                if (index < 0 && list.length >= (forMenu ? 100 : 12)) {
                    showError('登録できる項目数の上限に達しました。');
                    return;
                }
                const empty = forMenu
                    ? {id: null, type: 'page', label: '', reference_id: null, url: ''}
                    : {type: 'text', title: '', text: '', menu: 'footer', area: selectedWidgetAreas[pane]};
                editor = {list: kind, index, item: {...empty, ...list[index]}};
                renderPanel('[data-designer-form] select');
            } else if (target.action === 'remove') {
                list.splice(index, 1);
                editor = null;
                changed(add);
            } else if (target.action === 'up' || target.action === 'down') {
                const next = index + (target.action === 'up' ? -1 : 1);
                [list[index], list[next]] = [list[next], list[index]];
                editor = null;
                changed(itemControl(kind, `[data-action="edit"][data-index="${next}"]`));
            }
        }
    });

    root.addEventListener('change', event => {
        if (publishing) return;
        const input = event.target;
        const {setting, header, formSetting, chrome, chromeRow, chromeField, chromePart} = input.dataset;
        if (chrome) {
            const rows = ensureChrome(chrome);
            if (chromeField === 'split') rows.split = input.value === '2';
            else if (chromeField === 'position') {
                const [row, column] = input.value.split(':');
                rows.positions[chromePart] = {row, column: Number(column)};
            } else if (chrome === 'footer' && chromeRow === 'top' && chromeField === 'columns') {
                layouts[theme].footer = Number(input.value);
            } else rows[chromeRow][chromeField] = chromeField === 'columns' ? Number(input.value) : input.value;
            if (chrome === 'header') {
                for (const position of Object.values(rows.positions)) {
                    if (!rows.split) position.row = 'top';
                    position.column = Math.min(position.column, rows[position.row].columns);
                }
                layouts[theme].header.placement = rows.split ? 'stack' : 'row';
            }
            if (chromeField === 'split' && rows.split) chromeOpen.set(chrome + ':bottom', true);
            changed(chromeField === 'position' ? `[data-chrome-part="${chromePart}"]` :
                `[data-chrome="${chrome}"][data-chrome-row="${chromeRow ?? ''}"][data-chrome-field="${chromeField}"]`);
        } else if (setting === 'theme') {
            theme = input.value;
            if (resetDraft) {
                mode = 'draft';
                changedResetPreview();
                return;
            }
            editor = null;
            if ((pane === 'columns' || pane === 'page_layout') && !supports(pane)) {
                pane = 'header';
            }
            changed('[data-setting="theme"]');
        } else if (setting === 'reset-target' && resetDraft) {
            const [kind, id] = input.value ? input.value.split(':') : ['page', ''];
            resetDraft.target = {kind, id};
            showError();
            updatePreview();
        } else if (setting === 'area') {
            selectedWidgetAreas[pane] = input.value;
            if (editor?.list === 'widgets') editor = null;
            rememberForm();
            renderPanel('[data-setting="area"]');
            revealPreviewArea();
        } else if (setting === 'background-file') {
            uploadBackgroundImage(input);
        } else if (setting === 'background-mode' || setting === 'background-image') {
            if (setting === 'background-mode') background.mode = input.value;
            else background.media_id = input.value ? Number(input.value) : null;
            changed('[data-setting="' + setting + '"]');
        } else if (setting === 'placement' || header) {
            layouts[theme].header[header || setting] = header ? input.checked : input.value;
            changed(header ? `[data-header="${header}"]` : '[data-setting="placement"]');
        } else if (['columns', 'footer', 'width', 'headerWidth', 'footerWidth'].includes(setting)) {
            layouts[theme][setting] = setting === 'footer' ? Number(input.value) : input.value;
            changed(`[data-setting="${setting}"]`);
        } else if (formSetting === 'type' && editor) {
            rememberForm(input.form);
            editor.item.reference_id = null;
            delete editor.item.reference_ids;
            renderPanel('[data-form-setting="type"]');
        } else if (input.name === 'reference_id' && editor?.list === 'links') {
            const choice = targets[editor.item.type]?.find(item => item.id === Number(input.value));
            if (choice) input.form.elements.namedItem('label').value = choice.name ?? choice.label;
        } else if (input.name === 'reference_ids' && editor) {
            const count = new FormData(input.form).getAll('reference_ids').length;
            panel.querySelector('[data-category-count]').textContent = count + '件';
            panel.querySelector('[data-category-title-label]').textContent = categoryTitleLabel(count);
        }
    });

    function linkDestination(values) {
        if (values.type === 'custom') {
            const url = values.url.trim();
            return /^(?:\/(?!\/)|https?:\/\/)[^\s\\]*$/i.test(url) ? {reference_id: null, url} : null;
        }
        const id = Number(values.reference_id);
        return Number.isInteger(id) && id > 0 ? {reference_id: id, url: ''} : null;
    }

    panel.addEventListener('submit', event => {
        event.preventDefault();
        if (!editor || publishing) return;
        const formData = new FormData(event.target);
        const values = Object.fromEntries(formData);
        const kind = editor.list;
        const list = currentList(kind);
        const index = editor.index;
        const forMenu = kind === 'links';
        const name = (forMenu ? values.label : values.title).trim();
        const isLink = linkTypeIds.includes(values.type);
        const categories = !forMenu && values.type === 'category' ? formData.getAll('reference_ids').map(Number) : null;
        if (categories && (!categories.length || categories.length > data.categoryLimit ||
            categories.some(id => !Number.isInteger(id) || id < 1))) {
            showError('カテゴリは1〜' + data.categoryLimit + '件で選択してください。');
            return;
        }
        const link = categories ? {reference_id: categories[0], reference_ids: categories, url: ''} :
            isLink ? linkDestination(values) : null;
        if (isLink && (!link || ((forMenu || values.type === 'custom') && !name))) {
            showError('表示名とリンク先を確認してください。');
            return;
        }
        if (forMenu) {
            const item = {id: editor.item.id, label: name, type: values.type, ...link};
            if (index < 0) list.push(item);
            else list[index] = item;
        } else {
            if (!activeAreas().includes(values.area)) {
                showError('表示場所を選び直してください。');
                return;
            }
            const destination = layouts[theme].widgets[values.area];
            if ((destination !== list || index < 0) && destination.length >= 12) {
                showError('この場所には12個まで配置できます。');
                return;
            }
            if (/^header[1-5]$/.test(values.area)) ensureChrome('header');
            const item = {type: values.type, title: name, text: values.text?.trim() || '', menu: values.menu || 'footer', ...link};
            if (destination === list && index >= 0) list[index] = item;
            else {
                if (index >= 0) list.splice(index, 1);
                destination.push(item);
            }
            const destinationPane = widgetPane(values.area);
            if (pane !== destinationPane) chromeOpen.set(destinationPane + ':widgets', true);
            pane = destinationPane;
            selectedWidgetAreas[pane] = values.area;
        }
        editor = null;
        changed(itemControl(kind, '[data-action="add"]'));
    });

    function warnBeforeUnload(event) {
        if (!resetDraft && !editor && !Object.keys(layouts).some(hasChanges) && !pageEditor.hasAnyChanges()) return;
        event.preventDefault();
        event.returnValue = '';
    }
    window.addEventListener('beforeunload', warnBeforeUnload);
    frame.addEventListener('load', () => {
        if (!frame.hasAttribute('srcdoc')) return;
        // Keep the site controls read-only while allowing the iframe's native scrolling.
        const body = frame.contentDocument?.body;
        if (body) body.inert = true;
        revealPreviewArea();
        stage.setAttribute('aria-busy', 'false');
        if (resetDraft) renderStatus();
    });
    window.addEventListener('resize', resizePreview);
    renderPanel();
    syncPreviewButtons();
    updatePreview();
})();
