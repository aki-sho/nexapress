(() => {
    'use strict';

    const watched = new WeakMap();
    const canReadHeight = CSS.supports('height: attr(data-nx-cover-height px)');

    function layoutCover(cover) {
        const body = cover.querySelector(':scope > .nx-article-cover-content');
        if (!body) return;

        const height = Number(cover.dataset.nxCoverHeight);
        const fixed = Number.isInteger(height) && height >= 80 && height <= 3000;
        if (!canReadHeight) {
            cover.style.setProperty('--nx-cover-fixed-size', fixed ? height + 'px' : '');
        }
        if (cover.matches('.nx-cover-width-wide, .nx-cover-width-full')) {
            const view = cover.ownerDocument.defaultView;
            const style = view.getComputedStyle(cover);
            if (style.getPropertyValue('--nx-cover-viewport-aligned').trim() === '1') {
                const box = cover.getBoundingClientRect();
                // Unequal article padding shifts the normal center of the content column.
                const viewportWidth = cover.ownerDocument.documentElement.clientWidth;
                const unshiftedLeft = box.left - parseFloat(style.left);
                const shift = (viewportWidth - box.width) / 2 - unshiftedLeft;
                cover.style.setProperty('--nx-cover-inline-shift', Math.abs(shift) > 0.5 ? shift + 'px' : '');
            }
        }
        const images = [...body.querySelectorAll('img[data-nx-cover-pop], img[style*="--nx-cover-image-shift"]')]
            .filter(image => image.closest('.nx-article-cover') === cover);
        if (!images.length && !cover.hasAttribute('data-nx-cover-projecting')) return;
        for (const image of images) image.style.removeProperty('--nx-cover-image-shift');

        const box = cover.getBoundingClientRect();
        const style = cover.ownerDocument.defaultView.getComputedStyle(cover);
        const top = box.top + parseFloat(style.borderTopWidth);
        const bottom = box.bottom - parseFloat(style.borderBottomWidth);
        let spaceAbove = 0;
        let spaceBelow = 0;

        // A fixed cover with longer content needs its inner scrollbar.
        if (box.width > 0 && (!fixed || body.scrollHeight <= body.clientHeight + 1)) {
            for (const image of images) {
                const pixels = Number(image.dataset.nxCoverPop);
                if (!Number.isInteger(pixels) || !pixels || Math.abs(pixels) > 300) continue;
                const imageBox = image.getBoundingClientRect();
                if (!imageBox.width || !imageBox.height) continue;

                const shift = pixels < 0 ? top + pixels - imageBox.top : bottom + pixels - imageBox.bottom;
                image.style.setProperty('--nx-cover-image-shift', shift + 'px');
                spaceAbove = Math.max(spaceAbove, top - imageBox.top - shift);
                spaceBelow = Math.max(spaceBelow, imageBox.bottom + shift - bottom);
            }
        }
        // Transparent borders reserve space without increasing the background's height.
        cover.style.setProperty('--nx-cover-out-top', spaceAbove > 0.1 ? Math.ceil(spaceAbove - 0.01) + 'px' : '');
        cover.style.setProperty('--nx-cover-out-bottom', spaceBelow > 0.1 ? Math.ceil(spaceBelow - 0.01) + 'px' : '');
        cover.toggleAttribute('data-nx-cover-projecting', spaceAbove > 0.1 || spaceBelow > 0.1);
    }

    window.nxRefreshArticleCovers = scope => {
        if (!scope) return;
        let refresh = watched.get(scope);
        if (!refresh) {
            let frame = 0;
            refresh = () => {
                if (frame) return;
                frame = requestAnimationFrame(() => {
                    frame = 0;
                    if (!scope.isConnected) return;
                    for (const cover of scope.querySelectorAll('.nx-article-cover')) layoutCover(cover);
                });
            };
            watched.set(scope, refresh);
            scope.addEventListener('load', refresh, true);
            scope.ownerDocument.defaultView.addEventListener('resize', refresh);
            scope.ownerDocument.fonts?.ready.then(refresh);
            new ResizeObserver(refresh).observe(scope);
        }
        refresh();
    };

    if (document.currentScript?.hasAttribute('data-nx-cover-render') && document.querySelector('.nx-article-cover')) {
        window.nxRefreshArticleCovers(document.body);
    }
})();
