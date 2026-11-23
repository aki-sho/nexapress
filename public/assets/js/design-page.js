(() => {
    'use strict';

    const designColor = window.NexaPressDesignColor;
    const backgrounds = [['full', '画面いっぱい'], ['content', '中身と同じ'], ['none', '背景なし']];
    const clone = value => structuredClone(value);
    const owns = (value, key) => Object.prototype.hasOwnProperty.call(value, key);

    function editableRecord(snapshot) {
        const saved = clone(snapshot);
        if (saved.overrides.sections) {
            saved.overrides.sections = saved.overrides.sections.map(section => ({...section}));
        }
        return {saved, draft: clone(saved.overrides)};
    }

    window.NexaPressPageEditor = function (options) {
        const {panel, data, field, escape, changed, showError, redraw, preview, reveal, checkResponse, previewDocument} = options;
        const config = data.pageLayout;
        const target = data.legacyTarget;
        let common = clone(data.themes[data.active].state.contentLayouts);
        let record = null;
        let loading = true;
        const widths = Object.entries(config.widths);
        const numericFields = ['percent', 'share', 'gap', 'columnsWidth', 'paddingTop', 'paddingBottom'];
        const newElements = {
            page_title: {type: 'page_title'}, page_content: {type: 'page_content'},
            heading: {type: 'heading', text: '見出し'}, text: {type: 'text', text: ''},
            image: {type: 'image', media_id: null, alt: ''}, button: {type: 'button', text: '詳しく見る', url: ''},
        };
        const images = config.images;
        let sectionIndex = 0;
        let column = 0;
        let elementIndex = 0;
        let elementType = 'heading';
        let showColors = false;
        let showLayout = false;
        let showElements = false;
        let showSpacing = false;

        const dirty = () => record !== null && JSON.stringify(record.draft) !== JSON.stringify(record.saved.overrides);
        const action = (id, text, attributes = '') =>
            `<button type="button" class="nx-design-small" data-page-action="${id}" ${attributes}>${text}</button>`;
        const reset = (key, label) =>
            `<button type="button" class="nx-design-small nx-page-reset" data-page-reset="${key}">${label}</button>`;
        const select = (label, name, values, value) => field(label, name, values, value, `data-page-field="${name}"`);

        function merge(base, patch) {
            const result = clone(base);
            if (owns(patch, 'enabled')) result.enabled = patch.enabled;
            if (owns(patch, 'sectionCount')) {
                result.sections = result.sections.slice(0, patch.sectionCount);
                while (result.sections.length < patch.sectionCount) result.sections.push(clone(config.sectionDefaults));
            }
            for (const [index, section] of (patch.sections ?? []).entries()) {
                if (result.sections[index]) Object.assign(result.sections[index], clone(section));
            }
            return result;
        }

        const inherited = () => merge(merge({enabled: false, sections: []}, common.all), common[target.kind]);
        const effective = () => record ? merge(inherited(), record.draft) : null;
        const section = () => effective()?.sections[sectionIndex];
        const elements = () => section()?.groups[column] ?? [];
        const sectionOverrides = () => record?.draft.sections?.[sectionIndex] ?? {};
        const sectionBase = () => inherited().sections[sectionIndex] ?? config.sectionDefaults;

        function editSection() {
            const patch = record.draft;
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
            const sections = record.draft.sections;
            if (!sections) return;
            while (sections.length && Object.keys(sections.at(-1)).length === 0) sections.pop();
            if (!sections.length) delete record.draft.sections;
        }

        function inheritedSelect(label, name, values) {
            const base = sectionBase()[name];
            const inheritedLabel = values.find(([value]) => String(value) === String(base))?.[1] ?? base;
            return select(label, name, [['inherit', '引き継ぐ（' + inheritedLabel + '）'], ...values],
                sectionOverrides()[name] ?? 'inherit');
        }

        function sectionPadding(name) {
            const doc = previewDocument();
            const section = doc?.querySelector('[data-layout-section="' + sectionIndex + '"]');
            if (section) return parseFloat(doc.defaultView.getComputedStyle(section)[name]);
            const width = doc?.documentElement.clientWidth || 1600;
            return Math.min(72, Math.max(24, width * .05));
        }

        const numericValue = name => section()?.[name] ??
            (name.startsWith('padding') ? Number(sectionPadding(name).toFixed(3)) : config.sectionDefaults[name]);

        function integer(label, name, min, max) {
            const specified = owns(sectionOverrides(), name);
            const value = numericValue(name);
            return `<label>${label}<input type="number" data-page-field="${name}" value="${value}"
                min="${min}" max="${max}" step="${specified ? 1 : 'any'}"></label>`;
        }

        function syncSpacing() {
            if (!record || !section()) return;
            for (const name of ['paddingTop', 'paddingBottom']) {
                const input = panel.querySelector('[data-page-field="' + name + '"]');
                if (input && input !== document.activeElement && !owns(sectionOverrides(), name)) {
                    input.value = String(numericValue(name));
                }
            }
        }

        const ratio = item => {
            const gap = Math.min(item.gap, item.columnsWidth / 2);
            const space = item.columnsWidth - gap;
            return `左 ${Number((space * item.share / 100).toFixed(2))}% ・ 間隔 ${gap}% ・ 右 ${Number((space * (100 - item.share) / 100).toFixed(2))}%`;
        };
        const elementLabel = element => {
            const detail = element.text || images.find(image => image.id === element.media_id)?.label;
            return config.elements[element.type] + (detail ? ' · ' + detail.slice(0, 28) : '');
        };
        const sectionLabel = (item, index) => {
            const heading = item.groups.flat().find(element => element.type === 'heading' && element.text.trim());
            return heading ? heading.text.trim().slice(0, 36) : 'セクション ' + (index + 1);
        };

        async function load() {
            try {
                const base = target.kind === 'post' ? config.postUrl : config.url;
                const response = await fetch(base + '/' + encodeURIComponent(target.id), {cache: 'no-store'});
                await checkResponse(response);
                record = editableRecord(await response.json());
            } catch (error) {
                showError(error.message);
            } finally {
                loading = false;
                redraw();
                preview();
            }
        }

        function colorFields(item) {
            return `<details class="nx-page-details" data-page-details="colors" ${showColors ? 'open' : ''}>
                <summary>背景の色</summary>
                <div class="nx-page-fields">
                    <label class="nx-design-check"><input type="checkbox" data-page-inherit="color"
                        ${owns(sectionOverrides(), 'color') ? '' : 'checked'}>引き継いだ色を使用</label>
                    ${designColor.fields(item.color, 'section', escape)}
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
                    ${integer('列の合計幅（%）', 'columnsWidth', 1, 100)}
                    ${select('配置', 'align', Object.entries(data.alignments), item.align)}
                    ${owns(sectionOverrides(), 'align') ? reset('align', '配置を戻す') : ''}
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
                    ${owns(sectionOverrides(), 'groups') ? reset('groups', '中身を引き継ぐ') : ''}
                    ${item.columns === 2 || item.groups[1].length ? select('編集するカラム', 'column', slots, column) : ''}
                    ${current ? select('編集する要素', 'element', list.map((element, index) => [index, elementLabel(element)]), elementIndex) + fields +
                        `<div class="nx-design-actions">${action('element-remove', '要素を削除')}</div>` :
                        '<p class="nx-design-note">このカラムには要素がありません。</p>'}
                    ${select('追加する要素', 'elementType', Object.entries(config.elements), elementType)}
                    <div>${action('element-add', '＋ 要素を追加')}</div>
                </div>
            </details>`;
        }

        function render() {
            showColors = panel.querySelector('[data-page-details="colors"]')?.open ?? showColors;
            showLayout = panel.querySelector('[data-page-details="layout"]')?.open ?? showLayout;
            showElements = panel.querySelector('[data-page-details="elements"]')?.open ?? showElements;
            showSpacing = panel.querySelector('[data-page-details="spacing"]')?.open ?? showSpacing;
            if (!record) return loading ? '<p class="nx-design-note">読み込み中…</p>' : '';
            const layout = effective();
            sectionIndex = Math.max(0, Math.min(sectionIndex, layout.sections.length - 1));
            const item = layout.sections[sectionIndex];
            let html = layout.enabled ? '' : '<p class="nx-design-note">「本文の構成」で表示を切り替えられます。</p>';
            html += '<div class="nx-page-section-picker"><h3>セクション</h3>';
            if (item) {
                html += `<select data-page-field="section" aria-label="編集するセクション">
                    ${layout.sections.map((section, index) =>
                        `<option value="${index}" ${index === sectionIndex ? 'selected' : ''}>${escape(sectionLabel(section, index))}</option>`).join('')}
                </select>`;
            }
            html += '</div>';
            html += `<div class="nx-design-actions">${action('section-add', '＋ セクションを追加')}
                ${item ? action('section-remove', 'セクションを削除') : ''}
                ${owns(record.draft, 'sectionCount') ? reset('sections', '共通のセクションを使う') : ''}</div>`;
            if (!item) return html;
            const spacing = `<details class="nx-page-details" data-page-details="spacing" ${showSpacing ? 'open' : ''}>
                <summary>セクションの余白</summary><div class="nx-page-color-fields">
                    ${integer('内側・上（px）', 'paddingTop', 0, data.spacingLimit)}
                    ${integer('内側・下（px）', 'paddingBottom', 0, data.spacingLimit)}
                </div></details>`;
            return html + inheritedSelect('背景の幅', 'background', backgrounds) +
                (item.background === 'none' ? '' : colorFields(item)) + layoutFields(item) + spacing + elementControls(item);
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

        designColor.bind(panel, 'section', () => section().color, color => {
            editSection().color = color;
            panel.querySelector('[data-page-inherit="color"]').checked = false;
            changed(undefined, false);
        }, showError);

        panel.addEventListener('click', event => {
            const control = event.target.closest('button');
            if (!control || control.disabled || !record) return;
            const key = control.dataset.pageReset;
            if (key) {
                if (key === 'sections') {
                    delete record.draft.sectionCount;
                    delete record.draft.sections;
                    sectionIndex = column = elementIndex = 0;
                } else {
                    delete editSection()[key];
                    prune();
                }
                changed();
                return;
            }
            const command = control.dataset.pageAction;
            if (!command) return;
            const sections = effective().sections;
            if (command === 'section-add') {
                if (sections.length >= config.sectionLimit) {
                    showError('追加できるセクション数の上限に達しました。');
                    return;
                }
                record.draft.sectionCount = sections.length + 1;
                sectionIndex = sections.length;
                editSection();
                column = elementIndex = 0;
            } else if (command === 'section-remove') {
                sections.splice(sectionIndex, 1);
                record.draft.sectionCount = sections.length;
                record.draft.sections = sections;
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
            if (input.dataset.colorField) return;
            const name = input.dataset.pageField;
            const value = input.value;
            if (!record) return;
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
            if (['section', 'column', 'element', 'elementType'].includes(name)) {
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
                if (numericFields.includes(name)) {
                    if (!input.validity.valid || (value !== '' && !Number.isInteger(Number(value)))) {
                        input.value = String(numericValue(name));
                        showError('指定範囲内の整数を入力してください。');
                        return;
                    }
                    if (value === '') delete editSection()[name];
                    else editSection()[name] = Number(value);
                    prune();
                    input.value = String(numericValue(name));
                } else if (['columns', 'background', 'widthMode', 'align'].includes(name)) {
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
            if (numericFields.includes(name) || ['text', 'alt', 'url'].includes(name)) {
                refreshValues();
                return;
            }
            changed(`[data-page-field="${name}"]`);
        });

        panel.addEventListener('input', event => {
            const input = event.target;
            if (input.dataset.colorField) return;
            const name = input.dataset.pageField;
            if (!name || !section()) return;
            if (numericFields.includes(name) && input.validity.valid && (input.value === '' || Number.isInteger(Number(input.value)))) {
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
            if (details.dataset.pageDetails === 'colors') showColors = details.open;
            if (details.dataset.pageDetails === 'layout') showLayout = details.open;
            if (details.dataset.pageDetails === 'elements') showElements = details.open;
            if (details.dataset.pageDetails === 'spacing') showSpacing = details.open;
        }, true);

        return {
            render, widths, load, syncSpacing,
            sectionIndex: () => sectionIndex,
            hasChanges: dirty,
            hasAnyChanges: dirty,
            commonPayload: () => common,
            payload: () => dirty() ? [{
                kind: record.saved.kind, id: record.saved.id, revision: record.saved.revision, overrides: record.draft,
            }] : [],
            saved: result => {
                common = clone(Object.values(result.states)[0].contentLayouts);
                const snapshot = result.content?.find(item => item?.kind === target?.kind && String(item.id) === target.id);
                if (snapshot) record = editableRecord(snapshot);
            },
        };
    };
})();
