(() => {
    'use strict';

    const properties = new Set(['color', 'background-color', 'font-size', 'font-weight', 'font-style', 'text-decoration']);

    window.nxApplyArticleStyles = scope => {
        if (!scope) return;
        for (const node of scope.querySelectorAll('.post-content [data-nx-display-style]')) {
            for (const declaration of node.dataset.nxDisplayStyle.split('; ')) {
                const [property, value] = declaration.split(': ', 2);
                if (properties.has(property) && CSS.supports(property, value)) {
                    node.style.setProperty(property, value);
                }
            }
            node.removeAttribute('data-nx-display-style');
        }
    };

    if (document.currentScript?.hasAttribute('data-nx-article-style-render')) {
        window.nxApplyArticleStyles(document.body || document.documentElement);
    }
})();
