(() => {
    'use strict';

    const palettes = [
        ['高級感', ['#18243A', '#654155', '#CBB380']],
        ['優しい', ['#F6E6E6', '#EEE6F7', '#E5F0E7']],
        ['シンプル', ['#FFFFFF', '#F4F5F7', '#E4E7EC']],
        ['明るい', ['#FFF0AD', '#D7F0FF', '#D7F6E9']],
        ['暗い', ['#101827', '#242831', '#2C233D']],
    ];
    const backgrounds = [['full', '画面いっぱい'], ['content', '中身と同じ'], ['none', '背景なし']];
    const clone = value => structuredClone(value);
    const owns = (value, key) => Object.prototype.hasOwnProperty.call(value, key);

    function hexColor(value) {
        const code = value.trim().replace(/^#/, '').toUpperCase();
        if (/^[0-9A-F]{6}$/.test(code)) return '#' + code;
        return /^[0-9A-F]{3}$/.test(code) ? '#' + Array.from(code, digit => digit.repeat(2)).join('') : null;
    }

    function editableRecord(snapshot) {
        const saved = clone(snapshot);
        if (saved.overrides.sections) {
            saved.overrides.sections = saved.overrides.sections.map(section => ({...section}));
        }
        return {saved, draft: clone(saved.overrides)};
    }

    window.NexaPressPageEditor = function (options) {
        const {panel, data, field, escape, changed, showError, redraw, preview, reveal, checkResponse, themeWidth, previewMode} = options;
        const config = data.pageLayout;
        const initial = data.themes[data.active].state.contentLayouts;
        const common = Object.fromEntries(Object.keys(config.scopes).map(key => [
            key, editableRecord({overrides: initial[key]}),
        ]));
        const existing = new Map();
        const requests = new Set();
        const inheritanceUndo = new Map();
        const widths = Object.entries(config.widths);
        const scopeLabels = {...config.scopes, page_item: '固定ページのレイアウト', post_item: '投稿ページのレイアウト'};
        const choices = {page: data.targets.page, post: data.targets.post};
        const home = choices.page.find(page => page.id === config.home);
        const selected = {
            page: String(home?.id ?? choices.page[0]?.id ?? ''),
            post: String(choices.post[0]?.id ?? ''),
        };
        const newElements = {
            page_title: {type: 'page_title'}, page_content: {type: 'page_content'},
            heading: {type: 'heading', text: '見出し'}, text: {type: 'text', text: ''},
            image: {type: 'image', media_id: null, alt: ''}, button: {type: 'button', text: '詳しく見る', url: ''},
        };
        const images = config.images;
        let scope = null;
        let previewTarget = {kind: 'page', id: ''};
        let sectionIndex = 0;
        let column = 0;
        let elementIndex = 0;
        let elementType = 'heading';
        let showColors = false;
        let showLayout = false;
        let showElements = false;

        const individual = () => scope === 'page_item' || scope === 'post_item';
        const kind = () => scope === 'post' || scope === 'post_item' ? 'post' : 'page';
        const recordKey = (type, id) => type + ':' + id;
        const entry = () => common[scope] ?? existing.get(recordKey(kind(), selected[kind()]));
        const dirty = record => JSON.stringify(record.draft) !== JSON.stringify(record.saved.overrides);
        const records = () => [...Object.values(common), ...existing.values()];
        const labelForInheritance = () => scope === 'all' ? '初期設定' : '共通設定';
        const action = (id, text, attributes = '') =>
            `<button type="button" class="nx-design-small" data-page-action="${id}" ${attributes}>${text}</button>`;
        const reset = (key, label) =>
            `<button type="button" class="nx-design-small nx-page-reset" data-page-reset="${key}">${label}</button>`;
        const select = (label, name, values, value) => field(label, name, values, value, `data-page-field="${name}"`);

        function merge(base, patch) {
            const result = clone(base);
            for (const name of ['enabled', 'width']) {
                if (owns(patch, name)) result[name] = patch[name];
            }
            if (owns(patch, 'sectionCount')) {
                result.sections = result.sections.slice(0, patch.sectionCount);
                while (result.sections.length < patch.sectionCount) result.sections.push(clone(config.sectionDefaults));
            }
            for (const [index, section] of (patch.sections ?? []).entries()) {
                if (result.sections[index]) Object.assign(result.sections[index], clone(section));
            }
            return result;
        }

        const emptyLayout = () => ({enabled: false, width: 'inherit', sections: []});
        function inherited() {
            let result = emptyLayout();
            if (scope !== 'all') result = merge(result, common.all.draft);
            if (individual()) result = merge(result, common[kind()].draft);
            return result;
        }
        const effective = () => entry() ? merge(inherited(), entry().draft) : null;
        const section = () => effective()?.sections[sectionIndex];
        const elements = () => section()?.groups[column] ?? [];
        const sectionOverrides = () => entry()?.draft.sections?.[sectionIndex] ?? {};
        const sectionBase = () => inherited().sections[sectionIndex] ?? config.sectionDefaults;

        function editSection() {
            const patch = entry().draft;
            patch.sections ??= [];
            while (patch.sections.length <= sectionIndex) patch.sections.push({});
            return patch.sections[sectionIndex];
        }

        function editElements() {
            const patch = editSection();
            patch.groups ??= clone(section().groups);
            return patch.groups[column];
        }

        function prune() {
            const sections = entry().draft.sections;
            if (!sections) return;
            while (sections.length && Object.keys(sections.at(-1)).length === 0) sections.pop();
            if (!sections.length) delete entry().draft.sections;
        }

        function inheritedSelect(label, name, values) {
            const base = sectionBase()[name];
            const inheritedLabel = values.find(([value]) => String(value) === String(base))?.[1] ?? base;
            return select(label, name, [['inherit', labelForInheritance() + 'を使用（' + inheritedLabel + '）'], ...values],
                sectionOverrides()[name] ?? 'inherit');
        }

        function integer(label, name, min, max) {
            const value = sectionOverrides()[name] ?? '';
            return `<label>${label}<input type="number" data-page-field="${name}" value="${value}"
                min="${min}" max="${max}" step="1" placeholder="${escape(labelForInheritance() + ' ' + sectionBase()[name])}"></label>`;
        }

        const ratio = item => {
            const space = 100 - item.gap;
            return `左 ${Number((space * item.share / 100).toFixed(2))}% ・ 間隔 ${item.gap}% ・ 右 ${Number((space * (100 - item.share) / 100).toFixed(2))}%`;
        };
        const elementLabel = element => {
            const detail = element.text || images.find(image => image.id === element.media_id)?.label;
            return config.elements[element.type] + (detail ? ' · ' + detail.slice(0, 28) : '');
        };
        const sectionLabel = (item, index) => {
            const heading = item.groups.flat().find(element => element.type === 'heading' && element.text.trim());
            return heading ? heading.text.trim().slice(0, 36) : 'セクション ' + (index + 1);
        };

        function previewStatus() {
            if (previewMode() === 'published') {
                return '<p class="nx-design-note">公開版を表示しています。編集内容は「編集中」で確認できます。</p>';
            }
            const key = recordKey(previewTarget.kind, previewTarget.id);
            const record = existing.get(key);
            if (!record) return '<p class="nx-design-note">適用される設定を読み込み中…</p>';

            const sources = {enabled: '初期設定', sectionCount: '初期設定', sections: []};
            const layers = [
                [config.scopes.all, common.all.draft],
                [config.scopes[previewTarget.kind], common[previewTarget.kind].draft],
                [scopeLabels[previewTarget.kind + '_item'], record.draft],
            ];
            let layout = emptyLayout();
            for (const [label, patch] of layers) {
                layout = merge(layout, patch);
                for (const name of ['enabled', 'sectionCount']) {
                    if (owns(patch, name)) sources[name] = label;
                }
                sources.sections.length = layout.sections.length;
                for (const [index, section] of (patch.sections ?? []).entries()) {
                    if (!layout.sections[index]) continue;
                    sources.sections[index] ??= {};
                    for (const name of Object.keys(section)) sources.sections[index][name] = label;
                }
            }
            const item = layout.sections[sectionIndex];
            const sectionSources = sources.sections[sectionIndex] ?? {};
            const visible = layout.enabled && layout.sections.length > 0;
            const displaySource = layout.enabled && !layout.sections.length ? sources.sectionCount : sources.enabled;
            let colorSource = displaySource;
            if (visible) {
                if (!item) {
                    colorSource = sources.sectionCount;
                } else if (item.background === 'none') {
                    colorSource = sectionSources.background ?? '初期設定';
                } else {
                    colorSource = sectionSources.color ?? sources.sectionCount;
                }
            }
            const overridden = Object.keys(record.draft).some(name => name !== 'format');
            const kindOverridden = Object.keys(common[previewTarget.kind].draft).some(name => name !== 'format');
            const editedLayout = effective();
            const editedSection = editedLayout?.sections[sectionIndex];
            const colorBlocked = !individual() && editedLayout?.enabled && editedSection &&
                editedSection.background !== 'none' && (!visible || !item || item.background === 'none' || item.color !== editedSection.color);
            const pageName = choices[previewTarget.kind].find(item => String(item.id) === previewTarget.id)?.label ?? '';
            const actions = [
                overridden ? action('use-common', '個別設定を解除') : '',
                inheritanceUndo.has(key) ? action('restore-individual', '元の個別指定に戻す') : '',
                !individual() ? action('edit-individual', 'このページの個別設定を開く') : '',
                kindOverridden && scope !== previewTarget.kind ? action('edit-kind', escape(config.scopes[previewTarget.kind]) + 'を開く') : '',
            ].join('');
            return (individual() ? '<p class="nx-design-note">' + (overridden ? '個別設定あり' : '共通設定を使用中') + '</p>' : '') +
                (colorBlocked ? `<p class="nx-page-preview-conflict" data-page-preview-conflict>編集中の「${escape(pageName)}」は、このページには適用されていません。<br>確認先：${escape(colorSource)}</p>` : '') +
                (actions ? '<div class="nx-design-actions">' + actions + '</div>' : '');
        }

        function refreshPreviewStatus() {
            const status = panel.querySelector('[data-page-preview-status]');
            if (status) status.innerHTML = previewStatus();
        }

        async function loadDocument(type, id) {
            const key = recordKey(type, id);
            if (!id || existing.has(key) || requests.has(key)) return;
            requests.add(key);
            redraw();
            try {
                const base = type === 'post' ? config.postUrl : config.url;
                const response = await fetch(base + '/' + encodeURIComponent(id), {cache: 'no-store'});
                await checkResponse(response);
                const saved = await response.json();
                existing.set(key, editableRecord(saved));
            } catch (error) {
                if (previewTarget.kind === type && previewTarget.id === id) showError(error.message);
            } finally {
                requests.delete(key);
                if (individual() && recordKey(kind(), selected[kind()]) === key) {
                    redraw();
                } else {
                    refreshPreviewStatus();
                }
                preview();
            }
        }

        function activate(next) {
            scope = next;
            sectionIndex = column = elementIndex = 0;
            showError();
            const type = kind();
            if (scope === 'all') {
                previewTarget = {kind: 'page', id: selected.page};
                if (!previewTarget.id) previewTarget = {kind: 'post', id: selected.post};
            } else {
                previewTarget = {kind: type, id: selected[type]};
            }
            loadDocument(previewTarget.kind, previewTarget.id);
            redraw();
            preview();
        }

        function colorFields(item) {
            return `<details class="nx-page-details" data-page-details="colors" ${showColors ? 'open' : ''}>
                <summary>背景の色</summary>
                <div class="nx-page-fields">
                    <label class="nx-design-check"><input type="checkbox" data-page-inherit="color"
                        ${owns(sectionOverrides(), 'color') ? '' : 'checked'}>${labelForInheritance()}の色を使用</label>
                    <div class="nx-page-color-fields">
                        <label>カラーコード<input type="text" data-page-field="hex" value="${escape(item.color)}" maxlength="7" spellcheck="false"></label>
                        <label>色を選ぶ<input type="color" data-page-field="color" value="${escape(item.color)}"></label>
                    </div>
                    <div class="nx-page-palettes">${palettes.map(([label, colors]) => `<div>
                        <span class="nx-design-note">${label}</span>
                        <div class="nx-page-swatches">${colors.map(color => `<button type="button" class="nx-design-small nx-page-swatch"
                            data-page-color="${color}" aria-label="${label} ${color}" aria-pressed="${item.color === color}">
                            <span style="background-color:${color}" aria-hidden="true"></span>${color}</button>`).join('')}</div>
                    </div>`).join('')}</div>
                </div>
            </details>`;
        }

        function layoutFields(item) {
            return `<details class="nx-page-details" data-page-details="layout" ${showLayout ? 'open' : ''}>
                <summary>中身の幅・カラム数</summary>
                <div class="nx-page-fields">
                    ${inheritedSelect('中身の幅', 'widthMode', [['theme', 'ページの本文幅に合わせる'], ['percent', '割合を指定']])}
                    ${item.widthMode === 'percent' ? integer('画面幅に対する割合（%）', 'percent', 40, 100) : ''}
                    ${inheritedSelect('カラム数', 'columns', [[1, '1カラム'], [2, '2カラム']])}
                    ${item.columns === 2 ? `<div class="nx-page-color-fields">
                        ${integer('左への配分（%）', 'share', 20, 80)}${integer('列の間隔（%）', 'gap', 0, 30)}</div>
                        <p class="nx-design-note nx-page-ratio" data-page-ratio>${ratio(item)}</p>` : ''}
                </div>
            </details>`;
        }

        function elementControls(item) {
            const list = elements();
            const current = list[elementIndex];
            let fields = '';
            if (current?.type === 'image') {
                const media = [['', '選択してください'], ...images.map(image => [image.id, image.label])];
                if (current.media_id && !images.some(image => image.id === current.media_id)) {
                    media.push([current.media_id, '現在の画像（削除済み）']);
                }
                fields = select('メディアから画像を選ぶ', 'media', media, current.media_id ?? '') +
                    `<label>代替テキスト<input type="text" data-page-field="alt" maxlength="255" value="${escape(current.alt)}"></label>`;
            } else if (current?.type === 'text') {
                fields = `<label>文章<textarea data-page-field="text" maxlength="1200" rows="4">${escape(current.text)}</textarea></label>`;
            } else if (current?.type === 'heading' || current?.type === 'button') {
                fields = `<label>${current.type === 'heading' ? '見出し' : 'ボタンの表示名'}<input type="text" data-page-field="text"
                    maxlength="120" value="${escape(current.text)}"></label>`;
                if (current.type === 'button') {
                    fields += `<label>リンク先<input type="text" data-page-field="url" maxlength="2048" value="${escape(current.url)}"
                        placeholder="/contact または https://example.com/"></label>`;
                }
            }
            const slots = item.columns === 2 ? [[0, '左カラム'], [1, '右カラム']] : [[0, '前半'], [1, '後半']];
            return `<details class="nx-page-details" data-page-details="elements" ${showElements ? 'open' : ''}>
                <summary>カラムの中身</summary>
                <div class="nx-page-fields">
                    ${owns(sectionOverrides(), 'groups') ? reset('groups', labelForInheritance() + 'の中身を使用') :
                        '<p class="nx-design-note">' + labelForInheritance() + 'の中身を使用しています。</p>'}
                    ${item.columns === 2 || item.groups[1].length ? select('編集するカラム', 'column', slots, column) : ''}
                    ${current ? select('編集する要素', 'element', list.map((element, index) => [index, elementLabel(element)]), elementIndex) + fields +
                        `<div class="nx-design-actions">${action('element-remove', '要素を削除')}</div>` :
                        '<p class="nx-design-note">このカラムには要素がありません。</p>'}
                    ${select('追加する要素', 'elementType', Object.entries(config.elements), elementType)}
                    <div>${action('element-add', '＋ 要素を追加')}</div>
                </div>
            </details>`;
        }

        function editorFields() {
            const type = kind();
            let picker;
            if (individual()) {
                if (!choices[type].length) return '<p class="nx-design-note">編集する固定ページまたは投稿を作成してください。</p>';
                picker = select(type === 'page' ? '編集する固定ページ' : '編集する投稿', type,
                    choices[type].map(item => [String(item.id), item.label]), selected[type]);
            } else {
                const types = scope === 'all' ? ['page', 'post'] : [type];
                const targets = types.flatMap(type => choices[type].map(item =>
                    [recordKey(type, item.id), (type === 'page' ? '固定ページ · ' : '投稿 · ') + item.label]));
                picker = targets.length ? select('プレビューするページ', 'previewTarget', targets,
                    recordKey(previewTarget.kind, previewTarget.id)) :
                    '<p class="nx-design-note">プレビューする固定ページか投稿を作成してください。</p>';
            }
            const record = entry();
            if (!record) return picker + '<p class="nx-design-note">読み込み中…</p>';
            if (previewTarget.id) {
                picker += '<div class="nx-page-preview-status" data-page-preview-status role="status" aria-live="polite">' +
                    previewStatus() + '</div>';
            }
            const layout = effective();
            sectionIndex = Math.max(0, Math.min(sectionIndex, layout.sections.length - 1));
            const item = layout.sections[sectionIndex];
            const baseWidth = inherited().width;
            const inheritedWidth = config.widths[baseWidth === 'inherit' ? themeWidth() : baseWidth];
            let html = picker + select(individual() ? 'このページの本文幅' : 'この設定の本文幅', 'width',
                [['inherit', labelForInheritance() + 'を使用（' + inheritedWidth + '）'], ...widths],
                record.draft.width ?? 'inherit') +
                `<label class="nx-design-check"><input type="checkbox" data-page-field="enabled" ${layout.enabled ? 'checked' : ''}>
                    セクションで構成する</label>` +
                (owns(record.draft, 'enabled') || owns(record.draft, 'sectionCount')
                    ? reset('enabled', labelForInheritance() + 'の構成を使用') : '');
            if (!layout.enabled) return html;
            html += '<div class="nx-page-section-picker"><h3>セクション</h3>';
            if (item) {
                html += `<select data-page-field="section" aria-label="編集するセクション">
                    ${layout.sections.map((section, index) =>
                        `<option value="${index}" ${index === sectionIndex ? 'selected' : ''}>${escape(sectionLabel(section, index))}</option>`).join('')}
                </select>`;
            }
            html += '</div>';
            if (!item) html += '<p class="nx-design-note">セクションが空の場合は本文を表示します。</p>';
            html += `<div class="nx-design-actions">${action('section-add', '＋ セクションを追加')}
                ${item ? action('section-remove', 'セクションを削除') : ''}
                ${owns(record.draft, 'sectionCount') ? reset('sections', 'セクション設定を' + labelForInheritance() + 'に戻す') : ''}</div>`;
            if (!item) return html;
            return html + inheritedSelect('背景の幅', 'background', backgrounds) +
                (item.background === 'none' ? '' : colorFields(item)) + layoutFields(item) + elementControls(item);
        }

        function render() {
            showColors = panel.querySelector('[data-page-details="colors"]')?.open ?? showColors;
            showLayout = panel.querySelector('[data-page-details="layout"]')?.open ?? showLayout;
            showElements = panel.querySelector('[data-page-details="elements"]')?.open ?? showElements;
            return '<h2>ページ毎のレイアウト</h2><p class="nx-design-note">上の共通設定を引き継ぎ、変更した項目を優先します。</p>' +
                Object.entries(scopeLabels).map(([id, label]) =>
                    `<details class="nx-page-scope" data-layout-scope="${id}" name="nx-page-layout-scope" ${scope === id ? 'open' : ''}>
                        <summary>${escape(label)}</summary>
                        ${scope === id ? '<div class="nx-page-fields">' + editorFields() + '</div>' : ''}
                    </details>`).join('');
        }

        function refreshValues() {
            const item = section();
            const readout = panel.querySelector('[data-page-ratio]');
            if (readout && item) readout.textContent = ratio(item);
            const title = panel.querySelector('[data-page-field="section"]')?.selectedOptions[0];
            if (title && item) title.textContent = sectionLabel(item, sectionIndex);
            const label = panel.querySelector('[data-page-field="element"]')?.selectedOptions[0];
            if (label) label.textContent = elementLabel(elements()[elementIndex]);
            changed(undefined, false);
        }

        function applyColor(color, updateCode) {
            editSection().color = color;
            if (updateCode) panel.querySelector('[data-page-field="hex"]').value = color;
            panel.querySelector('[data-page-field="color"]').value = color;
            panel.querySelector('[data-page-inherit="color"]').checked = false;
            panel.querySelectorAll('[data-page-color]').forEach(button =>
                button.setAttribute('aria-pressed', String(button.dataset.pageColor === color)));
            changed(undefined, false);
        }

        panel.addEventListener('click', event => {
            const control = event.target.closest('button');
            if (!control || control.disabled || !entry()) return;
            const key = control.dataset.pageReset;
            if (key) {
                if (key === 'sections') {
                    delete entry().draft.enabled;
                    delete entry().draft.sectionCount;
                    delete entry().draft.sections;
                    sectionIndex = column = elementIndex = 0;
                } else if (key === 'enabled') {
                    delete entry().draft.enabled;
                    delete entry().draft.sectionCount;
                    prune();
                    sectionIndex = column = elementIndex = 0;
                } else {
                    delete editSection()[key];
                    prune();
                }
                changed();
                return;
            }
            if (control.dataset.pageColor) {
                applyColor(control.dataset.pageColor, true);
                return;
            }
            const command = control.dataset.pageAction;
            if (!command) return;
            if (command === 'edit-individual') {
                selected[previewTarget.kind] = previewTarget.id;
                activate(previewTarget.kind + '_item');
                return;
            }
            if (command === 'edit-kind') {
                selected[previewTarget.kind] = previewTarget.id;
                activate(previewTarget.kind);
                return;
            }
            if (command === 'use-common' || command === 'restore-individual') {
                const key = recordKey(previewTarget.kind, previewTarget.id);
                const record = existing.get(key);
                if (!record) return;
                if (command === 'use-common') {
                    if (!inheritanceUndo.has(key)) inheritanceUndo.set(key, clone(record.draft));
                    record.draft = {format: 2};
                } else {
                    if (!inheritanceUndo.has(key)) return;
                    record.draft = clone(inheritanceUndo.get(key));
                    inheritanceUndo.delete(key);
                }
                sectionIndex = column = elementIndex = 0;
                changed();
                return;
            }
            const sections = effective().sections;
            if (command === 'section-add') {
                if (sections.length >= config.sectionLimit) {
                    showError('追加できるセクション数の上限に達しました。');
                    return;
                }
                entry().draft.sectionCount = sections.length + 1;
                sectionIndex = sections.length;
                editSection();
                column = elementIndex = 0;
            } else if (command === 'section-remove') {
                sections.splice(sectionIndex, 1);
                entry().draft.sectionCount = sections.length;
                entry().draft.sections = sections;
                sectionIndex = Math.max(0, Math.min(sectionIndex, sections.length - 1));
                column = elementIndex = 0;
            } else if (command === 'element-add') {
                const total = sections.reduce((sum, item) => sum + item.groups[0].length + item.groups[1].length, 0);
                if (total >= config.elementLimit) {
                    showError('追加できる要素数の上限に達しました。');
                    return;
                }
                elementIndex = editElements().push(clone(newElements[elementType])) - 1;
            } else if (command === 'element-remove') {
                const list = editElements();
                list.splice(elementIndex, 1);
                elementIndex = Math.max(0, Math.min(elementIndex, list.length - 1));
            }
            changed(command.startsWith('section') ?
                (effective().sections.length ? '[data-page-field="section"]' : '[data-page-action="section-add"]') :
                (elements().length ? '[data-page-field="element"]' : '[data-page-field="elementType"]'));
        });

        panel.addEventListener('change', event => {
            const input = event.target;
            const name = input.dataset.pageField;
            const value = input.value;
            if (name === 'page' || name === 'post') {
                selected[name] = value;
                previewTarget = {kind: name, id: value};
                sectionIndex = column = elementIndex = 0;
                loadDocument(name, value);
                redraw();
                preview();
                return;
            }
            if (name === 'previewTarget') {
                const [type, id] = value.split(':');
                previewTarget = {kind: type, id};
                loadDocument(type, id);
                preview();
                return;
            }
            if (!entry()) return;
            if (input.dataset.pageInherit === 'color') {
                if (input.checked) {
                    delete editSection().color;
                    prune();
                } else {
                    editSection().color = section().color;
                }
                changed('[data-page-inherit="color"]');
                return;
            }
            if (!name) return;
            if (name === 'enabled') {
                entry().draft.enabled = input.checked;
                if (input.checked && !effective().sections.length) {
                    entry().draft.sectionCount = 1;
                    entry().draft.sections = [{groups: [[{type: 'page_title'}, {type: 'page_content'}], []]}];
                    sectionIndex = column = elementIndex = 0;
                }
            } else if (name === 'width') {
                if (value === 'inherit') delete entry().draft.width;
                else entry().draft.width = value;
            } else if (name === 'section' || name === 'column' || name === 'element' || name === 'elementType') {
                if (name === 'section') {
                    sectionIndex = Number(value);
                    column = elementIndex = 0;
                    reveal();
                } else if (name === 'column') {
                    column = Number(value);
                    elementIndex = 0;
                } else if (name === 'element') {
                    elementIndex = Number(value);
                } else {
                    elementType = value;
                    return;
                }
                redraw(`[data-page-field="${name}"]`);
                return;
            } else {
                const item = section();
                if (!item) return;
                if (name === 'hex' || name === 'color') {
                    const color = hexColor(value);
                    if (color) applyColor(color, true);
                    else {
                        input.value = item.color;
                        showError('背景色は #RRGGBB または #RGB で入力してください。');
                    }
                    return;
                }
                if (['percent', 'share', 'gap'].includes(name)) {
                    if (!input.validity.valid) {
                        input.value = sectionOverrides()[name] ?? '';
                        showError('幅・配分・間隔は指定範囲内の整数で入力してください。');
                        return;
                    }
                    if (value === '') delete editSection()[name];
                    else editSection()[name] = Number(value);
                    prune();
                } else if (['columns', 'background', 'widthMode'].includes(name)) {
                    if (value === 'inherit') delete editSection()[name];
                    else editSection()[name] = name === 'columns' ? Number(value) : value;
                    prune();
                    if (section().columns === 1 && column === 1 && !section().groups[1].length) column = elementIndex = 0;
                } else if (elements()[elementIndex] && name === 'media') {
                    editElements()[elementIndex].media_id = value ? Number(value) : null;
                } else if (elements()[elementIndex] && ['text', 'alt', 'url'].includes(name)) {
                    if (name === 'url' && value.trim() && !/^(?:\/(?!\/)|https?:\/\/)[^\s\\]*$/i.test(value.trim())) {
                        input.value = elements()[elementIndex].url;
                        showError('リンク先は / で始まるパスか、http(s) の URL を入力してください。');
                        return;
                    }
                    editElements()[elementIndex][name] = value.trim();
                }
            }
            if (['percent', 'share', 'gap', 'text', 'alt', 'url'].includes(name)) {
                refreshValues();
                return;
            }
            changed(`[data-page-field="${name}"]`);
        });

        panel.addEventListener('input', event => {
            const input = event.target;
            const name = input.dataset.pageField;
            if (!name || !section()) return;
            if (name === 'color') {
                applyColor(input.value.toUpperCase(), true);
            } else if (name === 'hex' && /^#?[0-9a-f]{6}$/i.test(input.value)) {
                applyColor(hexColor(input.value), false);
            } else if (['percent', 'share', 'gap'].includes(name) && input.validity.valid) {
                if (input.value === '') delete editSection()[name];
                else editSection()[name] = Number(input.value);
                prune();
                refreshValues();
            } else if ((name === 'text' || name === 'alt') && elements()[elementIndex]) {
                editElements()[elementIndex][name] = input.value.trim();
                refreshValues();
            }
        });

        panel.addEventListener('toggle', event => {
            const details = event.target;
            if (!details.isConnected) return;
            const next = details.dataset.layoutScope;
            if (next) {
                if (details.open && scope !== next) activate(next);
                else if (!details.open && scope === next) {
                    scope = null;
                    redraw();
                }
                return;
            }
            if (details.dataset.pageDetails === 'colors') showColors = details.open;
            if (details.dataset.pageDetails === 'layout') showLayout = details.open;
            if (details.dataset.pageDetails === 'elements') showElements = details.open;
        }, true);

        return {
            render, widths, refreshPreviewStatus,
            target: () => previewTarget,
            sectionIndex: () => sectionIndex,
            hasChanges: () => records().some(dirty),
            hasAnyChanges: () => records().some(dirty),
            commonPayload: () => Object.fromEntries(Object.entries(common).map(([key, record]) => [key, record.draft])),
            payload: () => Array.from(existing.values()).filter(dirty).map(record => ({
                kind: record.saved.kind, id: record.saved.id, revision: record.saved.revision, overrides: record.draft,
            })),
            saved: result => {
                const state = Object.values(result.states)[0];
                for (const [key, record] of Object.entries(common)) {
                    Object.assign(record, editableRecord({overrides: state.contentLayouts[key]}));
                }
                for (const snapshot of result.content ?? []) {
                    if (!snapshot) continue;
                    const key = recordKey(snapshot.kind, snapshot.id);
                    existing.set(key, editableRecord(snapshot));
                }
                inheritanceUndo.clear();
            },
        };
    };
})();
