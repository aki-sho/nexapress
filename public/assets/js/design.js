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
    const previewOverlay = root.querySelector('#nx-design-preview-overlay');
    const spacingOverlay = root.querySelector('#nx-design-spacing-overlay');
    const widgetHighlight = root.querySelector('#nx-design-widget-highlight');
    const widgetNote = root.querySelector('#nx-design-widget-note');
    const {linkTypes, targets, regions} = data;
    const regionIds = Object.keys(regions);
    const linkTypeIds = Object.keys(linkTypes);
    const areas = data.widgetAreas;
    const menus = {header: 'ヘッダーのリンク', footer: 'フッターのリンク'};
    const labels = {header: 'ヘッダー', header_rows: 'ヘッダーの段構成', columns: '本文カラム',
        footer: 'フッター', footer_rows: 'フッターの段構成', page_layout: 'ページ毎のレイアウト', ...regions};
    const designColor = window.NexaPressDesignColor;
    const chromeOpen = new Map();
    const widgetHighlights = new Map();
    const selectedWidgetAreas = {header: 'header1', columns: 'sidebar', footer: 'footer1'};
    const clone = value => JSON.parse(JSON.stringify(value));
    const same = (left, right) => JSON.stringify(left) === JSON.stringify(right);
    const isHeaderWidgetArea = area => data.headerWidgetAreas.includes(area);
    const isHeaderWidgetType = type => data.headerWidgetTypes.includes(type);
    const widgetLimit = area => isHeaderWidgetArea(area) ? data.headerWidgetLimit : data.widgetLimit;
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
    let spacingOverlayArea = null;
    let previewOverlayFrame = 0;
    let previewOverlayObserver;

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
    const widgetColumn = /^(header|footer)(?:_(above|bottom))?([1-5])$/;
    const widgetLocation = area => {
        if (area === 'header_above') return {row: 'above', column: 1};
        const match = widgetColumn.exec(area);
        return match ? {row: match[2] || 'top', column: Number(match[3])} : {row: area, column: null};
    };
    const activeAreas = () => Object.keys(areas).filter(key => {
        if (regionIds.includes(key)) return supports(key);
        if (key === 'sidebar') return supports('columns') && layouts[theme].columns !== 'none';
        if (key === 'header_above') return supports('header_rows') && layouts[theme].headerAbove;
        const match = widgetColumn.exec(key);
        if (!match) return false;
        const [, kind, row, number] = match;
        if (!supports(row ? kind + '_rows' : kind)) return false;
        if (row === 'above') {
            const above = layouts[theme].footerAbove;
            return above.enabled && Number(number) <= above.columns;
        }
        const rows = chromeRows(kind);
        return (!row || rows.split) && Number(number) <=
            (kind === 'footer' && !row ? layouts[theme].footer : rows[row || 'top'].columns);
    });
    const widgetAreaLabel = area => area === 'header_bottom1' && chromeRows('header').bottom.columns === 1 ? '下段' : areas[area];
    const hasChanges = id => !same(menuItems, published[id].menus) || !same(layouts[id], published[id].layout);
    const widgetPane = area => area === 'sidebar' ? 'columns' : area.startsWith('header') ? 'header' : 'footer';
    const widgetDestinations = (kind, type) => activeAreas().filter(area =>
        (kind === 'columns' || widgetPane(area) === kind) && (!isHeaderWidgetType(type) || isHeaderWidgetArea(area)));
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
            const destinations = widgetDestinations(pane, item.type);
            const destination = destinations.includes(item.area) ? item.area : selectedWidgetAreas[pane];
            const types = Object.entries(data.widgets).filter(([type]) => !isHeaderWidgetType(type) || isHeaderWidgetArea(destination));
            fields = field('種類', 'type', types, item.type, 'data-form-setting="type"') +
                (pane === 'columns' ? field('表示場所', 'area', destinations.map(id => [id, widgetAreaLabel(id)]), destination, 'required')
                    : widgetLocationFields(pane, destinations, destination, 'form'));
            if (isHeaderWidgetType(item.type)) {
                fields += '<p class="nx-design-note">サイトに登録した' + escape(data.widgets[item.type]) + 'を表示します。</p>';
            } else if (item.type === 'category') {
                fields += categoryField(item) + `<label><span data-category-title-label>${categoryTitleLabel(categoryIds(item).length)}</span>
                    <input name="title" maxlength="255" value="${escape(item.title)}"></label>`;
            } else if (linkTypeIds.includes(item.type)) {
                fields += linkField(item) + `<label>表示名
                    <input name="title" maxlength="255" value="${escape(item.title)}" ${item.type === 'custom' ? 'required' : ''}></label>`;
            } else {
                fields += `<label>見出し<input name="title" maxlength="255" value="${escape(item.title)}"></label>`;
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

    function chromeGroup(kind, key, title, content, open = false, action = '') {
        const id = kind + ':' + key;
        const summaryLabel = action ? ` aria-label="${title}"` : '';
        return `<details class="nx-chrome-settings" data-chrome-details="${id}" ${(chromeOpen.get(id) ?? open) ? 'open' : ''}>
            <summary${summaryLabel}>${title}${action}</summary><div class="nx-chrome-fields">${content}</div></details>`;
    }

    const overlayActionLabel = visible => visible ? '非表示' : '表示';

    function overlayButton(action, label, visible, attributes = '') {
        const text = overlayActionLabel(visible);
        return button(action, text,
            `data-overlay-label="${label}" data-overlay-visible="${visible}" aria-label="${label}を${text}" ${attributes}`);
    }

    function syncOverlayButton(control, visible) {
        const text = overlayActionLabel(visible);
        control.textContent = text;
        control.dataset.overlayVisible = String(visible);
        control.setAttribute('aria-label', control.dataset.overlayLabel + 'を' + text);
    }

    function spacingGroup(kind) {
        const area = kind === 'columns' ? 'body' : kind;
        const spacing = layouts[theme].spacing[area];
        const highlighted = spacingOverlayArea === area;
        const fields = [
            ['marginTop', '外側・上'], ['marginBottom', '外側・下'],
            ['marginLeft', '外側・左'], ['marginRight', '外側・右'],
            ['paddingTop', '内側・上'], ['paddingBottom', '内側・下'],
            ['paddingLeft', '内側・左'], ['paddingRight', '内側・右'],
        ].map(([key, label]) => `<label><span class="nx-spacing-label">${label}（px）
                <small class="nx-design-note" data-spacing-origin>${spacing[key] === null ? 'テーマ' : '指定'}</small></span>
            <input type="number" ${spacing[key] === null ? 'step="any"' : `min="0" max="${data.spacingLimit}" step="1"`}
                value="${escape(spacing[key])}" placeholder="プレビューを確認中"
                data-spacing-area="${area}" data-spacing-field="${key}">
            </label>`).join('');
        return chromeGroup(kind, 'spacing', '余白',
            `<p class="nx-spacing-key" data-spacing-key ${highlighted ? '' : 'hidden'}>
                <span data-spacing-color="margin">外側（margin）</span><span data-spacing-color="padding">内側（padding）</span></p>` +
            '<div class="nx-chrome-form-row">' + fields + '</div>' +
            '<p class="nx-design-note">空欄にするとテーマの値に戻ります。</p>', false,
            overlayButton('toggle-spacing', '余白', highlighted, `data-spacing-target="${area}"`));
    }

    function syncSpacingValues() {
        if (stage.getAttribute('aria-busy') !== 'false' || frame.dataset.previewTheme !== theme) return;
        const preview = frame.contentDocument;
        const view = frame.contentWindow;
        if (!preview || !view) return;
        for (const input of panel.querySelectorAll('[data-spacing-area]')) {
            const {spacingArea, spacingField} = input.dataset;
            if (layouts[theme].spacing[spacingArea][spacingField] !== null ||
                input === document.activeElement || !input.validity.valid) continue;
            const selector = spacingArea === 'body' ? '.site-main' : '.site-' + spacingArea;
            const element = preview.querySelector(selector);
            if (!element) continue;
            let value = parseFloat(view.getComputedStyle(element)[spacingField]);
            if (spacingArea !== 'body' && spacingField.startsWith('padding')) {
                // Themes place header/footer padding on their inner rows.
                const rows = Array.from(element.children).filter(row => row.classList.contains('site-' + spacingArea + '-inner'));
                const row = spacingField === 'paddingBottom' ? rows.at(-1) : rows[0];
                if (row) value += parseFloat(view.getComputedStyle(row)[spacingField]);
            }
            if (Number.isFinite(value)) input.value = String(Number(value.toFixed(3)));
        }
    }

    function appendSpacingRing(fragment, kind, outer, inner, sides = ['top', 'right', 'bottom', 'left']) {
        const sideTop = sides.includes('top') ? inner.top : outer.top;
        const sideBottom = sides.includes('bottom') ? inner.bottom : outer.bottom;
        const bands = {
            top: {left: outer.left, top: outer.top, width: outer.right - outer.left, height: inner.top - outer.top},
            bottom: {left: outer.left, top: inner.bottom, width: outer.right - outer.left, height: outer.bottom - inner.bottom},
            left: {left: outer.left, top: sideTop, width: inner.left - outer.left, height: sideBottom - sideTop},
            right: {left: inner.right, top: sideTop, width: outer.right - inner.right, height: sideBottom - sideTop},
        };
        for (const side of sides) {
            const box = bands[side];
            if (box.width <= 0 || box.height <= 0) continue;
            const band = document.createElement('div');
            band.className = 'nx-spacing-band nx-spacing-' + kind;
            band.dataset.spacingType = kind;
            band.dataset.spacingSide = side;
            Object.assign(band.style, {left: box.left + 'px', top: box.top + 'px', width: box.width + 'px', height: box.height + 'px'});
            fragment.append(band);
        }
    }

    function appendPaddingOverlay(fragment, element, sides) {
        const box = element.getBoundingClientRect();
        const style = frame.contentWindow.getComputedStyle(element);
        const pixels = name => parseFloat(style[name]) || 0;
        const outer = {
            left: box.left + pixels('borderLeftWidth'), top: box.top + pixels('borderTopWidth'),
            right: box.right - pixels('borderRightWidth'), bottom: box.bottom - pixels('borderBottomWidth'),
        };
        const inner = {
            left: outer.left + pixels('paddingLeft'), top: outer.top + pixels('paddingTop'),
            right: outer.right - pixels('paddingRight'), bottom: outer.bottom - pixels('paddingBottom'),
        };
        appendSpacingRing(fragment, 'padding', outer, inner, sides);
    }

    function renderSpacingOverlay() {
        const area = spacingOverlayArea;
        const currentArea = pane === 'columns' ? 'body' : pane;
        const preview = frame.contentDocument;
        const element = area && preview?.querySelector(area === 'body' ? '.site-main' : '.site-' + area);
        spacingOverlay.hidden = !element || area !== currentArea || resetDraft !== null ||
            stage.getAttribute('aria-busy') !== 'false' || frame.dataset.previewTheme !== theme;
        if (spacingOverlay.hidden) return;
        spacingOverlay.dataset.spacingArea = area;
        const fragment = document.createDocumentFragment();
        const box = element.getBoundingClientRect();
        const style = frame.contentWindow.getComputedStyle(element);
        const margin = side => Math.max(0, parseFloat(style['margin' + side]) || 0);
        appendSpacingRing(fragment, 'margin', {
            left: box.left - margin('Left'), top: box.top - margin('Top'),
            right: box.right + margin('Right'), bottom: box.bottom + margin('Bottom'),
        }, box);
        appendPaddingOverlay(fragment, element);
        if (area !== 'body') {
            const rows = Array.from(element.children).filter(row => row.classList.contains('site-' + area + '-inner'));
            for (const [index, row] of rows.entries()) {
                const sides = ['left', 'right'];
                if (index === 0) sides.push('top');
                if (index === rows.length - 1) sides.push('bottom');
                appendPaddingOverlay(fragment, row, sides);
            }
        }
        const outline = document.createElement('div');
        outline.className = 'nx-spacing-outline';
        Object.assign(outline.style, {left: box.left + 'px', top: box.top + 'px', width: box.width + 'px', height: box.height + 'px'});
        fragment.append(outline);
        spacingOverlay.replaceChildren(fragment);
    }

    const widgetDetails = () => panel.querySelector('[data-chrome-details="' + pane + ':widgets"]');
    const widgetHighlightVisible = (kind, expanded) => widgetHighlights.get(kind) ?? Boolean(expanded);

    function previewWidgetArea(group = widgetDetails()) {
        if (!group || resetDraft || (!group.open && !widgetHighlightVisible(pane, group.open))) return null;
        return editor?.list === 'widgets' ? group.querySelector('[name="area"]')?.value : selectedWidgetAreas[pane];
    }

    function previewWidgetTarget(area) {
        if (!area || frame.dataset.previewTheme !== theme || (mode === 'published' && theme !== data.active)) return null;
        const attribute = regionIds.includes(area) ? 'data-design-area' : 'data-widget-area';
        return frame.contentDocument?.querySelector('[' + attribute + '="' + CSS.escape(area) + '"]');
    }

    function renderWidgetHighlight() {
        const group = widgetDetails();
        const area = previewWidgetArea(group);
        const ready = area && widgetHighlightVisible(pane, group.open) && stage.getAttribute('aria-busy') === 'false' &&
            frame.dataset.previewTheme === theme;
        const bounds = ready ? previewWidgetTarget(area)?.getBoundingClientRect() : null;
        widgetHighlight.hidden = !bounds?.width || !bounds.height;
        if (!widgetHighlight.hidden) {
            Object.assign(widgetHighlight.style, {top: bounds.top + 'px', left: bounds.left + 'px',
                width: bounds.width + 'px', height: bounds.height + 'px'});
        }
        const message = ready && widgetHighlight.hidden
            ? mode === 'published' ? 'この場所は「編集中」で確認できます。'
                : 'このプレビューでは配置範囲を表示できません。' : '';
        widgetNote.hidden = !message;
        if (widgetNote.textContent !== message) widgetNote.textContent = message;
    }

    function schedulePreviewOverlays() {
        if (previewOverlayFrame) return;
        previewOverlayFrame = requestAnimationFrame(() => {
            previewOverlayFrame = 0;
            renderSpacingOverlay();
            renderWidgetHighlight();
        });
    }

    function renderList(list, kind) {
        const links = kind === 'links';
        return renderRows(list, links) + (editor?.list === kind ? renderForm() :
            '<div>' + button('add', links ? '＋ 項目を追加' : '＋ ウィジェットを追加') + '</div>');
    }

    function widgetLocationFields(kind, available, current, picker) {
        const {row, column} = widgetLocation(current);
        const names = {above: '上段', top: labels[kind] + '本体', bottom: '下段', ...regions};
        const rows = Object.entries(names).filter(([key]) => available.some(area => widgetLocation(area).row === key));
        const place = field('表示場所', '', rows, row, 'data-widget-row="' + picker + '"');
        const editing = picker === 'form';
        if (column === null) {
            return place + (editing ? '<input type="hidden" name="area" value="' + escape(current) + '">' : '');
        }
        const columns = available.filter(area => widgetLocation(area).row === row)
            .map(area => [area, widgetLocation(area).column + '列目']);
        const select = field('列', editing ? 'area' : 'widget-area', columns, current,
            editing ? 'required' : 'data-setting="area"');
        return '<div class="nx-chrome-form-row">' + place + select + '</div>';
    }

    function widgetRegionFields(area) {
        if (!regionIds.includes(area)) return '';
        const {direction, align} = layouts[theme].regions[area];
        return '<div class="nx-chrome-form-row">' +
            field('並び方', 'region-direction', Object.entries(data.regionDirections), direction, 'data-region-field="direction"') +
            field('配置', 'region-align', Object.entries(data.regionAlignments[direction]), align, 'data-region-field="align"') +
            '</div>';
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
            widgetLocationFields(kind, available, area, 'list') + widgetRegionFields(area);
        const highlighted = widgetHighlightVisible(kind, chromeOpen.get(kind + ':widgets'));
        return chromeGroup(kind, 'widgets', sidebar ? 'サイドバーのウィジェット' : 'ウィジェット',
            picker + renderList(layouts[theme].widgets[area], 'widgets'), false,
            overlayButton('toggle-widget-highlight', 'ウィジェットの配置範囲', highlighted));
    }

    function chromeStructure(kind, hasAbove, hasBelow) {
        const action = kind + '-row';
        const toggle = (row, label, direction, enabled) => {
            const attributes = 'data-row="' + row + '"';
            return enabled ? '<div class="nx-header-row-label"><strong>' + label + '</strong>' +
                button(action, '削除', attributes + ' aria-label="' + label + 'を削除"') + '</div>'
                : button(action, '＋ ' + direction + 'に段を追加', attributes);
        };
        return '<div class="nx-header-structure">' + toggle('above', '上段', '上', hasAbove) +
            '<div class="nx-header-base-row"><strong>' + labels[kind] + '本体</strong><span class="nx-design-note">ウィジェットを配置</span></div>' +
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
            html += spacingGroup(kind);
        }
        html += chromeGroup(kind, 'links', 'リンク', renderList(menuItems[kind], 'links'));
        if (!supports(kind)) return html + widgetGroup(kind);
        if (capable) {
            const above = kind === 'header' ? layout.headerAbove : layout.footerAbove.enabled;
            const structure = chromeStructure(kind, above, rows.split);
            html += chromeGroup(kind, 'structure', '段構成', structure, true);
            const shownRows = ['top'];
            if (kind === 'footer' && above) shownRows.unshift('above');
            if (rows.split) shownRows.push('bottom');
            for (const row of shownRows) {
                const settings = row === 'above' ? layout.footerAbove : rows[row] ?? {align: 'center'};
                const count = kind === 'footer' && row === 'top' ? layout.footer : settings.columns;
                const label = {above: '上段', top: title + '本体', bottom: '下段'}[row];
                html += chromeGroup(kind, row, label + 'のカラム・配置',
                    '<div class="nx-chrome-form-row">' + control('カラム数', row, 'columns', columnChoices, count) +
                    control('配置', row, 'align', Object.entries(data.alignments), settings.align) + '</div>', kind === 'footer');
            }
        } else if (kind === 'footer') {
            html += field('カラム数', 'footer', columnChoices, layout.footer, 'data-setting="footer"');
        }
        html += widgetGroup(kind);
        return html;
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
            if (id === pane + ':widgets') {
                syncOverlayButton(event.target.querySelector('[data-action="toggle-widget-highlight"]'),
                    widgetHighlightVisible(pane, event.target.open));
                if (event.target.open) revealPreviewArea();
                schedulePreviewOverlays();
            }
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
                layout.columns, 'data-setting="columns"') + spacingGroup('columns') + widgetGroup('columns');
        } else if (pane === 'page_layout') {
            panel.innerHTML = pageEditor.render();
        } else if (pane === 'general') {
            panel.innerHTML = generalPanel();
        }
        renderStatus();
        syncSpacingValues();
        schedulePreviewOverlays();
        if (focus) root.querySelector(focus)?.focus();
    }

    function resizePreview() {
        const wide = supports('page_layout') || supports('header') || supports('footer');
        const width = device === 'mobile' ? 390 : wide ? 1600 : 1080;
        const style = getComputedStyle(stage);
        const available = stage.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        if (available <= 0) return;
        const scale = Math.min(1, available / width);
        const geometry = {width: width + 'px', height: canvas.clientHeight / scale + 'px', transform: `scale(${scale})`};
        Object.assign(frame.style, geometry);
        Object.assign(previewOverlay.style, geometry);
        canvas.style.width = width * scale + 'px';
        syncSpacingValues();
        schedulePreviewOverlays();
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

    function revealPreviewArea(preferSpacing = false) {
        if (resetDraft) return;
        const footer = pane === 'footer';
        const body = pane === 'columns';
        const document = frame.contentDocument;
        const widgetTarget = previewWidgetTarget(previewWidgetArea());
        const section = pane === 'page_layout' ? document?.querySelector(`[data-layout-section="${pageEditor.sectionIndex()}"]`) : null;
        const currentArea = pane === 'columns' ? 'body' : pane;
        const spacingTarget = spacingOverlayArea === currentArea
            ? document?.querySelector(currentArea === 'body' ? '.site-main' : '.site-' + currentArea) : null;
        const highlightTarget = preferSpacing ? spacingTarget || widgetTarget : widgetTarget || spacingTarget;
        const anchor = highlightTarget || section || (footer ? (document?.querySelector('.site-footer') || document?.querySelector('footer'))
            : body ? document?.querySelector('.site-main, main') : (document?.querySelector('.site-header') || document?.querySelector('header')));
        const scroller = document?.scrollingElement;
        if (anchor && scroller) {
            const margin = anchor === spacingTarget
                ? Math.max(0, parseFloat(frame.contentWindow.getComputedStyle(anchor).marginTop) || 0) + 12
                : anchor === widgetTarget ? 12 : 0;
            scroller.scrollTop += anchor.getBoundingClientRect().top - margin;
        }
        schedulePreviewOverlays();
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
        delete frame.dataset.previewTheme;
        stage.setAttribute('aria-busy', 'true');
        spacingOverlay.hidden = true;
        widgetHighlight.hidden = true;
        widgetNote.hidden = true;
        if (resetDraft) {
            resetDraft.ready = false;
            renderStatus();
        }
        previewTimer = setTimeout(async () => {
            const controller = new AbortController();
            previewRequest = controller;
            const previewTheme = theme;
            try {
                const response = await fetch(data.preview, {method: 'POST', body: body({mode}), signal: controller.signal});
                await checkResponse(response);
                const html = await response.text();
                if (!controller.signal.aborted) {
                    if (resetDraft) resetDraft.ready = mode === 'draft';
                    frame.dataset.previewTheme = previewTheme;
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
        if (panel.querySelector('[data-spacing-area]:invalid')?.reportValidity() === false) return;
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

    function toggleChromeRow(kind, row) {
        const layout = layouts[theme];
        const above = row === 'above';
        let enabled;
        if (kind === 'footer') {
            if (above) {
                layout.footerAbove.enabled = !layout.footerAbove.enabled;
                enabled = layout.footerAbove.enabled;
            } else {
                const rows = ensureChrome(kind);
                rows.split = !rows.split;
                enabled = rows.split;
            }
        } else if (above) {
            enabled = layout.headerAbove = !layout.headerAbove;
        } else if (layout.headerRows) {
            const rows = layout.headerRows;
            enabled = rows.split = !rows.split;
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
            enabled = layout.headerBelow = !layout.headerBelow;
        }
        if (enabled && !editor) {
            selectedWidgetAreas[kind] = above ? (kind === 'header' ? 'header_above' : 'footer_above1') : kind + '_bottom1';
            const group = panel.querySelector('[data-chrome-details="' + kind + ':widgets"]');
            if (group) group.open = true;
            else chromeOpen.set(kind + ':widgets', true);
        }
        changed('[data-action="' + kind + '-row"][data-row="' + row + '"]');
    }

    root.addEventListener('click', event => {
        const control = event.target.closest('button');
        if (!control || publishing) return;
        const target = control.dataset;
        if (target.pane) {
            spacingOverlayArea = null;
            spacingOverlay.hidden = true;
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
        } else if (target.action === 'header-row' || target.action === 'footer-row') {
            toggleChromeRow(pane, target.row);
        } else if (target.action === 'toggle-widget-highlight') {
            event.preventDefault();
            const visible = !widgetHighlightVisible(pane, control.closest('details').open);
            widgetHighlights.set(pane, visible);
            syncOverlayButton(control, visible);
            if (visible) revealPreviewArea();
            schedulePreviewOverlays();
        } else if (target.action === 'toggle-spacing') {
            event.preventDefault();
            const area = target.spacingTarget;
            if (!['header', 'body', 'footer'].includes(area)) return;
            spacingOverlayArea = spacingOverlayArea === area ? null : area;
            syncOverlayButton(control, spacingOverlayArea !== null);
            panel.querySelector('[data-spacing-key]').hidden = spacingOverlayArea === null;
            if (spacingOverlayArea) revealPreviewArea(true);
            schedulePreviewOverlays();
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
                if (!forMenu) revealPreviewArea();
            } else if (target.action === 'add' || target.action === 'edit') {
                if (index < 0 && list.length >= (forMenu ? 100 : widgetLimit(selectedWidgetAreas[pane]))) {
                    showError('登録できる項目数の上限に達しました。');
                    return;
                }
                const empty = forMenu
                    ? {id: null, type: 'page', label: '', reference_id: null, url: ''}
                    : {type: 'text', title: '', text: '', menu: pane === 'header' ? 'header' : 'footer', area: selectedWidgetAreas[pane]};
                editor = {list: kind, index, item: {...empty, ...list[index]}};
                if (!forMenu && !editor.item.title && ['page', 'post'].includes(editor.item.type)) {
                    const choice = targets[editor.item.type]?.find(item => item.id === editor.item.reference_id);
                    if (choice) editor.item.title = choice.name ?? choice.label;
                }
                renderPanel('[data-designer-form] select');
                if (!forMenu) revealPreviewArea();
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

    panel.addEventListener('input', event => {
        const input = event.target;
        const {spacingArea, spacingField} = input.dataset;
        if (!spacingArea || publishing) return;
        input.setCustomValidity('');
        const value = input.value === '' ? null : input.valueAsNumber;
        if (!input.validity.valid || (value !== null &&
            (!Number.isInteger(value) || value < 0 || value > data.spacingLimit))) {
            const message = '余白は0〜' + data.spacingLimit + 'の整数で入力してください。';
            input.setCustomValidity(message);
            showError(message);
            return;
        }
        input.min = value === null ? '' : '0';
        input.max = value === null ? '' : String(data.spacingLimit);
        input.step = value === null ? 'any' : '1';
        input.closest('label').querySelector('[data-spacing-origin]').textContent = value === null ? 'テーマ' : '指定';
        const spacing = layouts[theme].spacing[spacingArea];
        if (spacing[spacingField] === value) {
            showError();
            return;
        }
        spacing[spacingField] = value;
        changed(undefined, false);
    });

    panel.addEventListener('focusout', event => {
        if (event.target.matches('[data-spacing-area]')) requestAnimationFrame(syncSpacingValues);
    });

    root.addEventListener('change', event => {
        if (publishing) return;
        const input = event.target;
        const {setting, formSetting, chrome, chromeRow, chromeField, widgetRow, regionField} = input.dataset;
        if (chrome) {
            if (chrome === 'footer' && chromeRow === 'above') {
                layouts[theme].footerAbove[chromeField] = chromeField === 'columns' ? Number(input.value) : input.value;
            } else if (chrome === 'footer' && chromeRow === 'top' && chromeField === 'columns') {
                layouts[theme].footer = Number(input.value);
            } else if (chrome === 'footer' && chromeRow === 'top') {
                ensureChrome(chrome).top = {align: input.value};
            } else {
                const rows = ensureChrome(chrome);
                rows[chromeRow][chromeField] = chromeField === 'columns' ? Number(input.value) : input.value;
                if (chrome === 'header') {
                    for (const position of Object.values(rows.positions)) {
                        if (!rows.split) position.row = 'top';
                        position.column = Math.min(position.column, rows[position.row].columns);
                    }
                    layouts[theme].header.placement = rows.split ? 'stack' : 'row';
                }
            }
            changed(`[data-chrome="${chrome}"][data-chrome-row="${chromeRow ?? ''}"][data-chrome-field="${chromeField}"]`);
        } else if (widgetRow) {
            const editing = widgetRow === 'form';
            const destination = widgetDestinations(pane, editing ? editor.item.type : undefined)
                .find(area => widgetLocation(area).row === input.value);
            if (!destination) return;
            if (editing) {
                rememberForm(input.form);
                editor.item.area = destination;
            } else {
                selectedWidgetAreas[pane] = destination;
            }
            renderPanel('[data-widget-row="' + widgetRow + '"]');
            revealPreviewArea();
        } else if (regionField) {
            const region = layouts[theme].regions[selectedWidgetAreas[pane]];
            region[regionField] = input.value;
            if (regionField === 'direction' && !Object.hasOwn(data.regionAlignments[region.direction], region.align)) {
                region.align = 'auto';
            }
            changed('[data-region-field="' + regionField + '"]');
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
        } else if (['columns', 'footer', 'width', 'headerWidth', 'footerWidth'].includes(setting)) {
            layouts[theme][setting] = setting === 'footer' ? Number(input.value) : input.value;
            changed(`[data-setting="${setting}"]`);
        } else if (formSetting === 'type' && editor) {
            rememberForm(input.form);
            editor.item.reference_id = null;
            delete editor.item.reference_ids;
            renderPanel('[data-form-setting="type"]');
        } else if (input.name === 'area' && editor?.list === 'widgets') {
            rememberForm(input.form);
            renderPanel('[data-designer-form] [name="area"]');
            revealPreviewArea();
        } else if (input.name === 'reference_id' && editor) {
            const choice = targets[editor.item.type]?.find(item => item.id === Number(input.value));
            const fieldName = editor.list === 'links' ? 'label' : 'title';
            if (choice) input.form.elements.namedItem(fieldName).value = choice.name ?? choice.label;
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
        const name = ((forMenu ? values.label : values.title) || '').trim();
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
            if (!widgetDestinations(pane, values.type).includes(values.area)) {
                showError('表示場所を選び直してください。');
                return;
            }
            const destination = layouts[theme].widgets[values.area];
            if ((destination !== list || index < 0) && destination.length >= widgetLimit(values.area)) {
                showError('この場所には' + widgetLimit(values.area) + '個まで配置できます。');
                return;
            }
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
        if (!frame.dataset.previewTheme) return;
        // Keep the site controls read-only while allowing the iframe's native scrolling.
        const body = frame.contentDocument?.body;
        if (body) body.inert = true;
        previewOverlayObserver?.disconnect();
        previewOverlayObserver = new ResizeObserver(schedulePreviewOverlays);
        if (body) previewOverlayObserver.observe(body);
        frame.contentDocument.addEventListener('scroll', schedulePreviewOverlays, {capture: true, passive: true});
        frame.contentDocument.addEventListener('load', schedulePreviewOverlays, true);
        frame.contentDocument.fonts.ready.then(schedulePreviewOverlays);
        revealPreviewArea();
        stage.setAttribute('aria-busy', 'false');
        syncSpacingValues();
        schedulePreviewOverlays();
        if (resetDraft) renderStatus();
    });
    window.addEventListener('resize', resizePreview);
    renderPanel();
    syncPreviewButtons();
    updatePreview();
})();
