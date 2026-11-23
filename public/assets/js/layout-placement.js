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
    const column = '.nx-article-columns > div.nx-article-column:not([data-nx-column-area])';
    const mediaDirectory = new URL('../../uploads/media/', document.currentScript.src);
    const imageFilename = /^[a-z0-9_-][a-z0-9_.-]{0,239}\.(?:jpe?g|png|gif|webp)$/i;

    // Column backgrounds reference a file inside this installation's media directory.
    window.nxColumnBackgroundUrl = filename => typeof filename === 'string' && imageFilename.test(filename)
        ? new URL(filename, mediaDirectory).href : null;

    function updateSharedTracks(node) {
        const children = [...node.children];
        if (!children.some(child => child.hasAttribute('data-nx-column-area'))) return;
        const columns = children.filter(child => child.matches('div.nx-article-column:not([data-nx-column-area])'));
        const weights = columns.length >= 2 && columns.length <= 4 ? columns.map(column => {
            const width = column.getAttribute('data-nx-column-width') || '';
            return /^\d{1,3}$/.test(width) && Number(width) >= 1 && Number(width) <= 100
                ? Number(width) / 100 : 1 / columns.length;
        }) : [];
        const total = Math.max(1, weights.reduce((sum, value) => sum + value, 0));
        for (let index = 0; index < 4; index++) {
            node.style.setProperty('--nx-column-track-' + (index + 1), weights[index] ? String(weights[index] / total) : '');
        }
    }

    window.nxApplyLayoutPlacement = scope => {
        if (!scope) return;
        for (const node of scope.querySelectorAll(selector)) {
            if (node.classList.contains('nx-article-columns')) updateSharedTracks(node);
            if (node.matches(column)) {
                const backgroundUrl = window.nxColumnBackgroundUrl(node.dataset.nxColumnBackground);
                node.style.setProperty('--nx-column-background-image', backgroundUrl ? 'url("' + CSS.escape(backgroundUrl) + '")' : '');
            }
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
