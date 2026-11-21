(() => {
    'use strict';

    window.NexaPressDesignFrame = ({panel, options, field, escape, settings, theme, previewElement, changed, showError, saving}) => {
        const {modes, styles, lineDefault, limit} = options;
        const color = window.NexaPressDesignColor;
        const sideLabels = {top: '上', right: '右', bottom: '下', left: '左'};
        const sides = Object.keys(sideLabels);
        const corners = ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft'];
        const samples = new Map();
        const keyFor = area => theme() + ':' + area;
        const sample = area => samples.get(keyFor(area)) ?? {line: lineDefault, radius: null};
        const lineFor = area => settings(area).line ?? sample(area).line;
        const radiusFor = area => settings(area).radius ?? sample(area).radius;
        const allWidth = widths => sides.every(side => widths[side] === widths.top) ? widths.top : null;
        const groupFor = input => input.closest('[data-frame-settings]');
        const number = value => Number(Math.min(limit, Math.max(0, value)).toFixed(3));

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

        function render(area) {
            const current = settings(area);
            const mode = field('表示設定', 'frame-mode', Object.entries(modes), current.mode, 'data-frame-mode');
            if (current.mode === 'none') return `<div class="nx-frame-settings" data-frame-settings="${area}">${mode}</div>`;
            const line = lineFor(area);
            const numeric = (id, label, value, placeholder = '') => `<label>${label}（px）
                <input type="number" min="0" max="${limit}" step="0.001" data-frame-number="${id}"
                    value="${escape(value)}" placeholder="${placeholder}"></label>`;
            return `<div class="nx-frame-settings" data-frame-settings="${area}">
                <div class="nx-chrome-form-row">${mode}
                    ${field('線の種類', 'frame-style', Object.entries(styles), line.style, 'data-frame-style')}</div>
                <div class="nx-chrome-form-row">
                    ${numeric('all', '四辺一括', allWidth(line.widths), '四辺の太さを揃える')}
                    ${numeric('radius', '角丸', radiusFor(area), 'テーマの角丸')}</div>
                <div class="nx-chrome-form-row">
                    ${sides.map(side => numeric(side, sideLabels[side] + 'の線', line.widths[side])).join('')}
                </div>
                <p class="nx-design-note">線を消す辺は 0 px。角丸を空欄にするとテーマの値に戻ります。</p>
                ${color.fields(line.color, 'frame', escape, true)}
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
            if (settings(area).mode === 'none') return;
            const element = previewElement(area);
            if (element) measure(area, element);
            paint(group);
        }

        function apply(group, redraw = false) {
            const area = group.dataset.frameSettings;
            settings(area).mode = 'custom';
            group.querySelector('[data-frame-mode]').value = 'custom';
            paint(group);
            changed(area, redraw);
        }

        panel.addEventListener('change', event => {
            const input = event.target;
            const group = groupFor(input);
            if (!group || saving()) return;
            const area = group.dataset.frameSettings;
            const current = settings(area);
            if (input.hasAttribute('data-frame-mode')) {
                if (current.mode === input.value) return;
                current.mode = input.value;
                if (current.mode === 'theme') {
                    current.line = null;
                    current.radius = null;
                }
                changed(area, true);
            } else if (input.hasAttribute('data-frame-style') && input.value !== lineFor(area).style) {
                current.line = {...lineFor(area), style: input.value};
                apply(group);
            }
        });

        panel.addEventListener('input', event => {
            const input = event.target;
            const field = input.dataset.frameNumber;
            if (!field || saving()) return;
            input.setCustomValidity('');
            if (!input.validity.valid) {
                const message = '枠の数値は0〜' + limit + 'で、小数第3位まで入力できます。';
                input.setCustomValidity(message);
                showError(message);
                return;
            }
            const group = groupFor(input);
            const area = group.dataset.frameSettings;
            const current = settings(area);
            const value = input.value === '' ? null : number(input.valueAsNumber);
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
            apply(group);
        });

        panel.addEventListener('focusout', event => {
            if (event.target.hasAttribute('data-frame-number')) requestAnimationFrame(sync);
        });

        color.bind(panel, 'frame', group => lineFor(groupFor(group).dataset.frameSettings).color, (value, editor) => {
            if (saving()) return;
            const group = groupFor(editor);
            const area = group.dataset.frameSettings;
            const line = lineFor(area);
            if (line.color === value) return;
            settings(area).line = {...line, color: value};
            apply(group);
        }, showError, '枠線の色');

        return {render, sync};
    };
})();
