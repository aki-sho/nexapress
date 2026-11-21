(() => {
    'use strict';

    const palettes = [
        ['高級感', ['#18243A', '#654155', '#CBB380']],
        ['優しい', ['#F6E6E6', '#EEE6F7', '#E5F0E7']],
        ['シンプル', ['#FFFFFF', '#F4F5F7', '#E4E7EC']],
        ['明るい', ['#FFF0AD', '#D7F0FF', '#D7F6E9']],
        ['暗い', ['#101827', '#242831', '#2C233D']],
    ];

    function parts(value, alpha = 1) {
        const code = value.trim();
        const hex = /^#?([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i.exec(code)?.[1];
        if (hex) {
            const expanded = hex.length < 5 ? [...hex].map(digit => digit.repeat(2)).join('') : hex;
            return {hex: '#' + expanded.slice(0, 6).toUpperCase(),
                alpha: expanded.length === 8 ? parseInt(expanded.slice(6), 16) / 255 : alpha};
        }
        const match = /^(rgba?)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(\d*\.?\d+))?\s*\)$/i.exec(code);
        if (!match || (match[1].toLowerCase() === 'rgba') !== (match[5] !== undefined)) return null;
        const rgb = match.slice(2, 5).map(Number);
        const opacity = match[5] === undefined ? 1 : Number(match[5]);
        return Math.max(...rgb) <= 255 && opacity <= 1
            ? {hex: '#' + rgb.map(value => value.toString(16).padStart(2, '0')).join('').toUpperCase(), alpha: opacity} : null;
    }

    function canonical({hex, alpha}) {
        const opacity = Number(alpha.toFixed(4));
        if (opacity === 1) return hex;
        const rgb = hex.slice(1).match(/../g).map(part => parseInt(part, 16));
        return 'rgba(' + [...rgb, opacity].join(', ') + ')';
    }

    const percentage = alpha => Number((alpha * 100).toFixed(2));

    function fields(color, kind, escape, compact = false) {
        const {hex, alpha} = parts(color);
        const opacity = percentage(alpha);
        return `<div class="nx-design-color" data-design-color="${kind}">
            <div class="nx-page-color-fields">
                <label>カラーコード<input type="text" data-color-field="code" ${kind === 'section' ? 'data-page-field="hex"' : ''}
                    value="${escape(color)}" maxlength="48" spellcheck="false"></label>
                <label>色を選ぶ<input type="color" data-color-field="picker" value="${hex}"></label>
            </div>
            ${compact
                ? `<label>不透明度（％）<input type="number" min="0" max="100" step="0.01" data-color-field="opacity" value="${opacity}"></label>`
                : `<label><span class="nx-design-opacity">不透明度 <output data-color-opacity>${opacity}%</output></span>
                    <input type="range" min="0" max="100" step="0.01" data-color-field="opacity" value="${opacity}"></label>`}
            ${compact ? '' : `<div class="nx-page-palettes">${palettes.map(([label, colors]) => `<div>
                <span class="nx-design-note">${label}</span>
                <div class="nx-page-swatches">${colors.map(value => `<button type="button" class="nx-design-small nx-page-swatch"
                    data-page-color="${value}" aria-label="${label} ${value}" aria-pressed="${hex === value}">
                    <span style="background-color:${value}" aria-hidden="true"></span>${value}</button>`).join('')}</div>
            </div>`).join('')}</div>`}
        </div>`;
    }

    function reflect(group, next, keepCode = false) {
        const opacity = percentage(next.alpha);
        if (!keepCode) group.querySelector('[data-color-field="code"]').value = canonical(next);
        group.querySelector('[data-color-field="picker"]').value = next.hex;
        group.querySelector('[data-color-field="opacity"]').value = opacity;
        const output = group.querySelector('[data-color-opacity]');
        if (output) output.textContent = opacity + '%';
        group.querySelectorAll('[data-page-color]').forEach(button =>
            button.setAttribute('aria-pressed', String(button.dataset.pageColor === next.hex)));
    }

    function bind(panel, kind, current, update, showError, label = '背景色') {
        const editor = target => target.closest('[data-design-color="' + kind + '"]');
        function apply(group, next, keepCode = false) {
            reflect(group, next, keepCode);
            update(canonical(next), group);
        }

        panel.addEventListener('click', event => {
            const swatch = event.target.closest('[data-page-color]');
            const group = swatch && editor(swatch);
            if (group) apply(group, {...parts(current(group)), hex: swatch.dataset.pageColor});
        });

        function onInput(event) {
            const input = event.target;
            const name = input.dataset.colorField;
            const group = name && editor(input);
            if (!group) return;
            const color = current(group);
            const existing = parts(color);
            if (name === 'code') {
                if (event.type === 'input' && /^#?[a-f0-9]{3,4}$/i.test(input.value.trim())) return;
                const next = parts(input.value, existing.alpha);
                if (next) apply(group, next, event.type === 'input');
                else if (event.type === 'change') {
                    input.value = color;
                    showError(label + 'は #RRGGBB または rgba(255, 255, 255, 0.5) で入力してください。');
                }
            } else if (name === 'picker') {
                apply(group, {...existing, hex: input.value.toUpperCase()});
            } else if (input.value !== '' && input.validity.valid) {
                apply(group, {...existing, alpha: Number(input.value) / 100});
            } else if (event.type === 'change') {
                reflect(group, existing);
                showError('不透明度は0〜100％で入力してください。');
            }
        }
        panel.addEventListener('input', onInput);
        panel.addEventListener('change', onInput);
    }

    window.NexaPressDesignColor = {
        fields, bind,
        normalize(value) {
            const next = parts(value);
            return next ? canonical(next) : null;
        },
        sync(group, color) {
            reflect(group, parts(color));
        },
    };
})();
