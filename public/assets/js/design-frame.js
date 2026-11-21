(() => {
    'use strict';

    window.NexaPressDesignFrame = ({panel, options, field, escape, settings, theme, previewElement, changed, showError, saving}) => {
        const {modes, styles, lineDefault, shadowDefault, cardSelector, limit} = options;
        const color = window.NexaPressDesignColor;
        const sideLabels = {top: '上', right: '右', bottom: '下', left: '左'};
        const sides = Object.keys(sideLabels);
        const corners = ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft'];
        const shadowFields = {x: '左右（右＋）', y: '上下（下＋）', blur: 'ぼかし', spread: '広がり'};
        const samples = new Map();
        const shadowSamples = new Map();
        const keyFor = area => theme() + ':' + area;
        const sample = area => samples.get(keyFor(area)) ?? {line: lineDefault, radius: null};
        const lineFor = area => settings(area).line ?? sample(area).line;
        const radiusFor = area => settings(area).radius ?? sample(area).radius;
        const allWidth = widths => sides.every(side => widths[side] === widths.top) ? widths.top : null;
        const groupFor = input => input.closest('[data-frame-settings]');
        const number = (value, minimum = 0) => Number(Math.min(limit, Math.max(minimum, value)).toFixed(3));
        const shadowKey = (area, key) => keyFor(area) + ':' + key;
        const shadowMinimum = name => name === 'blur' ? 0 : -limit;
        const numeric = (label, value, attribute, minimum = 0, placeholder = '') => `<label>${label}（px）
            <input type="number" min="${minimum}" max="${limit}" step="0.001" ${attribute}
                value="${escape(value)}" placeholder="${placeholder}"></label>`;

        function measure(area, element) {
            const previous = sample(area);
            const current = settings(area);
            const style = element.ownerDocument.defaultView.getComputedStyle(element);
            let line = previous.line;
            if (current.line === null) {
                const widths = Object.fromEntries(sides.map(side =>
                    [side, number(parseFloat(style.getPropertyValue('border-' + side + '-width')) || 0)]));
                const visible = sides.find(side => widths[side] > 0);
                line = {...lineDefault, widths};
                if (visible) {
                    const computedStyle = style.getPropertyValue('border-' + visible + '-style');
                    line.style = Object.hasOwn(styles, computedStyle) ? computedStyle : lineDefault.style;
                    line.color = color.normalize(style.getPropertyValue('border-' + visible + '-color')) ?? lineDefault.color;
                }
            }
            let radius = previous.radius;
            if (current.radius === null) {
                const radii = corners.map(corner => style['border' + corner + 'Radius']);
                const pixels = /^(\d+(?:\.\d+)?)px$/.exec(radii[0]);
                radius = pixels && radii.every(value => value === radii[0]) ? number(Number(pixels[1])) : null;
            }
            samples.set(keyFor(area), {line, radius});
        }

        function measureShadow(area, key, element) {
            const computed = element.ownerDocument.defaultView.getComputedStyle(element).boxShadow;
            const shade = computed.match(/rgba?\([^)]*\)/)?.[0];
            const normalized = shade && color.normalize(shade);
            const lengths = shade ? computed.replace(shade, '').trim().split(/\s+/) : [];
            const id = shadowKey(area, key);
            if (!normalized || lengths.length !== 4 || lengths.some(value => !/^-?\d+(?:\.\d+)?px$/.test(value))) {
                shadowSamples.delete(id);
                return;
            }
            const dimensions = Object.fromEntries(Object.keys(shadowFields).map((name, index) =>
                [name, number(parseFloat(lengths[index]), shadowMinimum(name))]));
            shadowSamples.set(id, {...dimensions, color: normalized});
        }

        function renderBorder(area) {
            const mode = field('表示設定', 'frame-mode', Object.entries(modes), settings(area).mode, 'data-frame-mode');
            if (settings(area).mode === 'none') return mode;
            const line = lineFor(area);
            return `<div class="nx-chrome-form-row">${mode}
                    ${field('線の種類', 'frame-style', Object.entries(styles), line.style, 'data-frame-style')}</div>
                <div class="nx-chrome-form-row">
                    ${numeric('四辺一括', allWidth(line.widths), 'data-frame-number="all"', 0, '四辺の太さを揃える')}
                    ${numeric('角丸', radiusFor(area), 'data-frame-number="radius"', 0, 'テーマの角丸')}</div>
                <div class="nx-chrome-form-row">
                    ${sides.map(side => numeric(sideLabels[side] + 'の線', line.widths[side], `data-frame-number="${side}"`)).join('')}
                </div>
                <p class="nx-design-note">線を消す辺は 0 px。角丸を空欄にするとテーマの値に戻ります。</p>
                ${color.fields(line.color, 'frame', escape, true)}`;
        }

        function renderShadow(area, key, label) {
            const {mode, value} = settings(area)[key];
            const control = field(label, 'frame-' + key, Object.entries(modes), mode, `data-shadow-mode="${key}"`);
            let controls = control;
            if (mode === 'custom') {
                const numbers = Object.entries(shadowFields).map(([name, caption]) =>
                    numeric(caption, value[name], `data-shadow-number="${name}"`, shadowMinimum(name))).join('');
                controls += `<div class="nx-chrome-form-row">${numbers}</div>` + color.fields(value.color, 'frame-' + key, escape, true);
            }
            return `<div class="nx-frame-shadow" data-frame-shadow="${key}">${controls}</div>`;
        }

        function render(area) {
            return `<div class="nx-frame-settings" data-frame-settings="${area}">
                ${renderBorder(area)}
                ${renderShadow(area, 'shadow', area === 'body' ? '本文全体の影' : '影')}
                ${area === 'body' ? renderShadow(area, 'cardShadow', '記事・固定ページのカードの影') : ''}
            </div>`;
        }

        function paint(group) {
            const area = group.dataset.frameSettings;
            const current = settings(area);
            if (current.mode === 'none') return;
            const line = lineFor(area);
            const values = {...line.widths, all: allWidth(line.widths), radius: radiusFor(area)};
            for (const input of group.querySelectorAll('[data-frame-number]')) {
                if (input === document.activeElement || !input.validity.valid) continue;
                input.value = values[input.dataset.frameNumber] ?? '';
            }
            const style = group.querySelector('[data-frame-style]');
            if (style !== document.activeElement) style.value = line.style;
            const colorGroup = group.querySelector('[data-design-color="frame"]');
            if (!colorGroup.contains(document.activeElement)) color.sync(colorGroup, line.color);
        }

        function sync() {
            const group = panel.querySelector('[data-frame-settings]');
            if (!group) return;
            const area = group.dataset.frameSettings;
            const current = settings(area);
            const element = previewElement(area);
            if (element && current.mode !== 'none') measure(area, element);
            if (element && current.shadow.mode === 'theme') measureShadow(area, 'shadow', element);
            if (current.cardShadow?.mode === 'theme') {
                const card = previewElement(area, cardSelector);
                if (card) measureShadow(area, 'cardShadow', card);
            }
            paint(group);
        }

        function applyBorder(group) {
            const area = group.dataset.frameSettings;
            settings(area).mode = 'custom';
            group.querySelector('[data-frame-mode]').value = 'custom';
            paint(group);
            changed(area);
        }

        function editShadow(group, key, name, value) {
            const area = group.dataset.frameSettings;
            const shadow = settings(area)[key];
            if (shadow.value[name] === value) {
                showError();
                return;
            }
            shadow.value[name] = value;
            changed(area);
        }

        panel.addEventListener('change', event => {
            const input = event.target;
            const group = groupFor(input);
            if (!group || saving()) return;
            const area = group.dataset.frameSettings;
            const current = settings(area);
            const shadowKeyName = input.dataset.shadowMode;
            if (shadowKeyName) {
                const shadow = current[shadowKeyName];
                if (shadow.mode === input.value) return;
                let value = shadow.value;
                if (input.value === 'custom' && value === null) {
                    const fallback = area === 'footer' ? {...shadowDefault, y: -shadowDefault.y} : shadowDefault;
                    value = {...(shadowSamples.get(shadowKey(area, shadowKeyName)) ?? fallback)};
                } else if (input.value === 'theme') {
                    value = null;
                }
                current[shadowKeyName] = {mode: input.value, value};
                changed(area, `[data-shadow-mode="${shadowKeyName}"]`);
            } else if (input.hasAttribute('data-frame-mode')) {
                if (current.mode === input.value) return;
                current.mode = input.value;
                if (current.mode === 'theme') {
                    current.line = null;
                    current.radius = null;
                }
                changed(area, '[data-frame-mode]');
            } else if (input.hasAttribute('data-frame-style') && input.value !== lineFor(area).style) {
                current.line = {...lineFor(area), style: input.value};
                applyBorder(group);
            }
        });

        panel.addEventListener('input', event => {
            const input = event.target;
            const field = input.dataset.frameNumber;
            const shadowField = input.dataset.shadowNumber;
            if ((!field && !shadowField) || saving()) return;
            const minimum = Number(input.min);
            input.setCustomValidity('');
            if (!input.validity.valid) {
                const label = shadowField ? '影' : '枠';
                const message = label + 'の数値は' + minimum + '〜' + limit + 'で、小数第3位まで入力できます。';
                input.setCustomValidity(message);
                showError(message);
                return;
            }
            const group = groupFor(input);
            const value = input.value === '' ? null : number(input.valueAsNumber, minimum);
            if (shadowField) {
                if (value !== null) editShadow(group, input.closest('[data-frame-shadow]').dataset.frameShadow, shadowField, value);
                return;
            }
            const area = group.dataset.frameSettings;
            const current = settings(area);
            if (field === 'radius') {
                if (current.radius === value || (current.radius === null && value === radiusFor(area))) {
                    showError();
                    return;
                }
                current.radius = value;
            } else {
                if (value === null) return;
                const line = lineFor(area);
                const keys = field === 'all' ? sides : [field];
                if (keys.every(side => line.widths[side] === value)) {
                    showError();
                    return;
                }
                const widths = {...line.widths};
                for (const side of keys) widths[side] = value;
                current.line = {...line, widths};
            }
            applyBorder(group);
        });

        panel.addEventListener('focusout', event => {
            const input = event.target;
            if (input.hasAttribute('data-frame-number')) requestAnimationFrame(sync);
            const name = input.dataset.shadowNumber;
            if (name && input.value === '' && input.validity.valid) {
                const area = groupFor(input).dataset.frameSettings;
                const key = input.closest('[data-frame-shadow]').dataset.frameShadow;
                input.value = settings(area)[key].value[name];
            }
        });

        color.bind(panel, 'frame', group => lineFor(groupFor(group).dataset.frameSettings).color, (value, editor) => {
            if (saving()) return;
            const group = groupFor(editor);
            const area = group.dataset.frameSettings;
            const line = lineFor(area);
            if (line.color === value) return;
            settings(area).line = {...line, color: value};
            applyBorder(group);
        }, showError, '枠線の色');

        for (const key of ['shadow', 'cardShadow']) {
            color.bind(panel, 'frame-' + key, group => settings(groupFor(group).dataset.frameSettings)[key].value.color,
                (value, editor) => {
                    if (!saving()) editShadow(groupFor(editor), key, 'color', value);
                }, showError, '影の色');
        }

        return {render, sync};
    };
})();
