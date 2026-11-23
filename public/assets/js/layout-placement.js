(() => {
    'use strict';

    const sizes = [
        ['data-nx-layout-width', '--nx-layout-width', 1, 100, value => value + '%'],
        ['data-nx-layout-gap', '--nx-layout-gap', 0, 64, value => value + 'px'],
        ['data-nx-column-width', '--nx-column-share', 1, 100, value => String(value / 100)],
        ['data-nx-button-radius', '--nx-button-radius', 0, 64, value => value + 'px'],
    ];
    const colors = [
        ['data-nx-button-background', '--nx-button-background'],
        ['data-nx-button-color', '--nx-button-color'],
        ['data-nx-button-border', '--nx-button-border'],
    ];
    const selector = '.nx-article-columns, .nx-article-column, .nx-article-buttons, .nx-article-cover, ' +
        '.nx-article-button, [data-nx-layout-width]';

    window.nxApplyLayoutPlacement = scope => {
        if (!scope) return;
        for (const node of scope.querySelectorAll(selector)) {
            for (const [attribute, property, min, max, format] of sizes) {
                const text = node.getAttribute(attribute) || '';
                const value = Number(text);
                node.style.setProperty(property, /^\d{1,3}$/.test(text) && value >= min && value <= max ? format(value) : '');
            }
            for (const [attribute, property] of colors) {
                const value = node.getAttribute(attribute) || '';
                node.style.setProperty(property, /^#[a-f0-9]{6}$/i.test(value) ? value : '');
            }
        }
    };

    if (document.currentScript?.hasAttribute('data-nx-layout-render')) {
        window.nxApplyLayoutPlacement(document.body || document.documentElement);
    }
})();
