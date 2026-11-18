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
    const initialSection = (elements = []) => ({
        background: 'full', color: '#F4F5F7', widthMode: 'theme',
        percent: 80, columns: 1, share: 50, gap: 10, groups: [elements, []],
    });

    function hexColor(value) {
        const code = value.trim().replace(/^#/, '').toUpperCase();
        if (/^[0-9A-F]{6}$/.test(code)) return '#' + code;
        return /^[0-9A-F]{3}$/.test(code) ? '#' + Array.from(code, digit => digit.repeat(2)).join('') : null;
    }

    window.NexaPressPageEditor = function (options) {
        const {panel, data, field, escape, changed, showError, redraw, preview, reveal, checkResponse} = options;
        const config = data.pageLayout;
        const existing = new Map();
        const requests = new Set();
        const widths = Object.entries(config.widths);
        const choices = [['', '現在のトップページ'], ...data.targets.page.map(page => [String(page.id), page.label])];
        const newElements = {
            page_title: {type: 'page_title'}, page_content: {type: 'page_content'},
            heading: {type: 'heading', text: '見出し'}, text: {type: 'text', text: ''},
            image: {type: 'image', media_id: null, alt: ''}, button: {type: 'button', text: '詳しく見る', url: ''},
        };
        let opened = false;
        let selected = '';
        let sectionIndex = 0;
        let column = 0;
        let elementIndex = 0;
        let elementType = 'heading';
        let showColors = false;
        let showLayout = false;
        let showElements = false;
        let images = [];

        const entry = () => existing.get(selected);
        const section = () => entry()?.draft.sections[sectionIndex];
        const elements = () => section()?.groups[column] ?? [];
        const ratio = item => {
            const space = 100 - item.gap;
            return `左 ${Number((space * item.share / 100).toFixed(2))}% ・ 間隔 ${item.gap}% ・ 右 ${Number((space * (100 - item.share) / 100).toFixed(2))}%`;
        };
        const dirty = record => JSON.stringify(record.draft) !== JSON.stringify(record.saved.layout);
        const select = (label, name, values, value) => field(label, name, values, value, `data-page-field="${name}"`);
        const action = (id, text, attributes = '') =>
            `<button type="button" class="nx-design-small" data-page-action="${id}" ${attributes}>${text}</button>`;
        const integer = (label, name, value, min, max) => `<label>${label}
            <input type="number" data-page-field="${name}" value="${value}" min="${min}" max="${max}" step="1" required></label>`;
        const elementLabel = element => {
            const detail = element.text || images.find(image => image.id === element.media_id)?.label;
            return config.elements[element.type] + (detail ? ' · ' + detail.slice(0, 28) : '');
        };

        async function choosePage(id) {
            selected = id;
            sectionIndex = column = elementIndex = 0;
            showError();
            if (!id || existing.has(id) || requests.has(id)) {
                redraw();
                preview();
                return;
            }
            requests.add(id);
            redraw();
            preview();
            try {
                const response = await fetch(config.url + '/' + encodeURIComponent(id), {cache: 'no-store'});
                await checkResponse(response);
                const {images: media, ...saved} = await response.json();
                images = media;
                existing.set(id, {saved, draft: structuredClone(saved.layout)});
            } catch (error) {
                if (id === selected) showError(error.message);
            } finally {
                requests.delete(id);
                if (id === selected) redraw();
            }
        }

        function open() {
            if (opened) return;
            opened = true;
            const home = choices.find(([id]) => id === String(config.home));
            const first = home ?? choices[1];
            if (first) choosePage(first[0]);
        }

        function colorFields(item) {
            return `<details class="nx-page-details" data-page-details="colors" ${showColors ? 'open' : ''}>
                <summary>背景の色</summary>
                <div class="nx-page-fields">
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
                    ${select('中身の幅', 'widthMode', [['theme', 'ページの本文幅に合わせる'], ['percent', '割合を指定']], item.widthMode)}
                    ${item.widthMode === 'percent' ? integer('画面幅に対する割合（%）', 'percent', item.percent, 40, 100) : ''}
                    ${select('カラム数', 'columns', [[1, '1カラム'], [2, '2カラム']], item.columns)}
                    ${item.columns === 2 ? `<div class="nx-page-color-fields">
                        ${integer('左への配分（%）', 'share', item.share, 20, 80)}${integer('列の間隔（%）', 'gap', item.gap, 0, 30)}</div>
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
                    ${item.columns === 2 || item.groups[1].length ? select('編集するカラム', 'column', slots, column) : ''}
                    ${current ? select('編集する要素', 'element', list.map((element, index) => [index, elementLabel(element)]), elementIndex) + fields +
                        `<div class="nx-design-actions">${action('element-remove', '要素を削除')}</div>` :
                        '<p class="nx-design-note">このカラムには要素がありません。</p>'}
                    ${select('追加する要素', 'elementType', Object.entries(config.elements), elementType)}
                    <div>${action('element-add', '＋ 要素を追加')}</div>
                </div>
            </details>`;
        }

        function sectionLabel(item, index) {
            const heading = item.groups.flat().find(element => element.type === 'heading' && element.text.trim());
            return heading ? heading.text.trim().slice(0, 36) : 'セクション ' + (index + 1);
        }

        function render() {
            showColors = panel.querySelector('[data-page-details="colors"]')?.open ?? showColors;
            showLayout = panel.querySelector('[data-page-details="layout"]')?.open ?? showLayout;
            showElements = panel.querySelector('[data-page-details="elements"]')?.open ?? showElements;
            const title = '<h2>固定ページのレイアウト</h2>';
            if (choices.length === 1) return title + '<p class="nx-design-note">固定ページを作成するとここで編集できます。</p>';
            const picker = select('編集する固定ページ', 'page', choices, selected);
            const record = entry();
            if (!record) {
                const info = requests.has(selected) ? '<p class="nx-design-note">読み込み中…</p>' : '';
                return title + picker + info;
            }
            const layout = record.draft;
            let html = title + picker + select('このページの本文幅', 'width', [['inherit', 'テーマの既定幅'], ...widths], layout.width) +
                `<label class="nx-design-check"><input type="checkbox" data-page-field="enabled" ${layout.enabled ? 'checked' : ''}>
                    セクションで構成する</label>`;
            if (!layout.enabled) return html;
            const item = section();
            html += '<div class="nx-page-section-picker"><h3>セクション</h3>';
            if (item) {
                html += `<select data-page-field="section" aria-label="編集するセクション">
                    ${layout.sections.map((section, index) =>
                        `<option value="${index}" ${index === sectionIndex ? 'selected' : ''}>${escape(sectionLabel(section, index))}</option>`).join('')}
                </select>`;
            }
            html += '</div>';
            if (!item) {
                html += '<p class="nx-design-note">セクションが空の場合は固定ページの本文を表示します。</p>';
            }
            html += `<div class="nx-design-actions">${action('section-add', '＋ セクションを追加')}
                ${item ? action('section-remove', 'セクションを削除') : ''}</div>`;
            if (!item) return html;
            return html + select('背景の幅', 'background', backgrounds, item.background) +
                (item.background === 'none' ? '' : colorFields(item)) +
                layoutFields(item) + elementControls(item);
        }

        function applyColor(color, updateCode) {
            if (updateCode) panel.querySelector('[data-page-field="hex"]').value = color;
            panel.querySelector('[data-page-field="color"]').value = color;
            panel.querySelectorAll('[data-page-color]').forEach(button =>
                button.setAttribute('aria-pressed', String(button.dataset.pageColor === color)));
            if (section().color === color) return;
            section().color = color;
            changed(undefined, false);
        }

        function refreshValues() {
            const item = section();
            const readout = panel.querySelector('[data-page-ratio]');
            if (readout) readout.textContent = ratio(item);
            const title = panel.querySelector('[data-page-field="section"]')?.selectedOptions[0];
            if (title) title.textContent = sectionLabel(item, sectionIndex);
            const label = panel.querySelector('[data-page-field="element"]')?.selectedOptions[0];
            if (label) label.textContent = elementLabel(elements()[elementIndex]);
            changed(undefined, false);
        }

        panel.addEventListener('click', event => {
            const control = event.target.closest('button');
            if (!control || control.disabled || !entry()) return;
            if (control.dataset.pageColor) {
                applyColor(control.dataset.pageColor, true);
                return;
            }
            const command = control.dataset.pageAction;
            if (!command) return;
            const sections = entry().draft.sections;
            if (command === 'section-add') {
                if (sections.length >= config.sectionLimit) {
                    showError('追加できるセクション数の上限に達しました。');
                    return;
                }
                sectionIndex = sections.push(initialSection()) - 1;
                column = elementIndex = 0;
            } else if (command === 'section-remove') {
                sections.splice(sectionIndex, 1);
                sectionIndex = Math.max(0, Math.min(sectionIndex, sections.length - 1));
                column = elementIndex = 0;
            } else if (command === 'element-add') {
                const total = sections.reduce((sum, item) => sum + item.groups[0].length + item.groups[1].length, 0);
                if (total >= config.elementLimit) {
                    showError('追加できる要素数の上限に達しました。');
                    return;
                }
                elementIndex = elements().push({...newElements[elementType]}) - 1;
            } else if (command === 'element-remove') {
                const list = elements();
                list.splice(elementIndex, 1);
                elementIndex = Math.max(0, Math.min(elementIndex, list.length - 1));
            }
            changed(command.startsWith('section') ?
                (sections.length ? '[data-page-field="section"]' : '[data-page-action="section-add"]') :
                (elements().length ? '[data-page-field="element"]' : '[data-page-field="elementType"]'));
        });

        panel.addEventListener('change', event => {
            const input = event.target;
            const name = input.dataset.pageField;
            const value = input.value;
            if (!name) return;
            if (name === 'page') {
                choosePage(value);
                return;
            }
            const layout = entry()?.draft;
            if (!layout) return;
            if (name === 'enabled') {
                layout.enabled = input.checked;
                if (input.checked && !layout.sections.length) {
                    layout.sections.push(initialSection([{type: 'page_title'}, {type: 'page_content'}]));
                    sectionIndex = column = elementIndex = 0;
                }
            } else if (name === 'width') {
                layout.width = value;
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
                const element = elements()[elementIndex];
                if (name === 'hex' || name === 'color') {
                    const color = hexColor(value);
                    if (color) {
                        applyColor(color, true);
                    } else {
                        input.value = item.color;
                        showError('背景色は #RRGGBB または #RGB で入力してください。');
                    }
                    return;
                }
                if (['percent', 'share', 'gap'].includes(name)) {
                    if (!input.validity.valid) {
                        input.value = item[name];
                        showError('幅・配分・間隔は指定範囲内の整数で入力してください。');
                        return;
                    }
                    item[name] = Number(value);
                } else if (name === 'columns' || name === 'background' || name === 'widthMode') {
                    item[name] = name === 'columns' ? Number(value) : value;
                    if (item.columns === 1 && column === 1 && !item.groups[1].length) column = elementIndex = 0;
                } else if (element && name === 'media') {
                    element.media_id = value ? Number(value) : null;
                } else if (element && ['text', 'alt', 'url'].includes(name)) {
                    if (name === 'url' && value.trim() && !/^(?:\/(?!\/)|https?:\/\/)[^\s\\]*$/i.test(value.trim())) {
                        input.value = element.url;
                        showError('リンク先は / で始まるパスか、http(s) の URL を入力してください。');
                        return;
                    }
                    element[name] = value.trim();
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
            const item = section();
            if (!name || !item) return;
            if (name === 'color') {
                applyColor(input.value.toUpperCase(), true);
            } else if (name === 'hex' && /^#?[0-9a-f]{6}$/i.test(input.value)) {
                applyColor(hexColor(input.value), false);
            } else if (['percent', 'share', 'gap'].includes(name) && input.validity.valid) {
                item[name] = Number(input.value);
                refreshValues();
            } else if (name === 'text' || name === 'alt') {
                const element = elements()[elementIndex];
                if (!element) return;
                element[name] = input.value.trim();
                refreshValues();
            }
        });
        panel.addEventListener('toggle', event => {
            if (!event.target.isConnected) return;
            if (event.target.dataset.pageDetails === 'colors') showColors = event.target.open;
            if (event.target.dataset.pageDetails === 'layout') showLayout = event.target.open;
            if (event.target.dataset.pageDetails === 'elements') showElements = event.target.open;
        }, true);

        return {
            open, render, widths,
            pageId: () => selected,
            sectionIndex: () => sectionIndex,
            hasChanges: () => Boolean(entry() && dirty(entry())),
            hasAnyChanges: () => Array.from(existing.values()).some(dirty),
            payload: () => entry() && dirty(entry()) ? {...entry().saved, layout: entry().draft} : null,
            saved: snapshot => {
                const record = snapshot && existing.get(String(snapshot.id));
                if (record) {
                    record.saved = snapshot;
                    record.draft = structuredClone(snapshot.layout);
                }
            },
        };
    };
})();
