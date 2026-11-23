(() => {
    for (const button of document.querySelectorAll('[data-media-confirm-button]')) {
        button.disabled = false;
    }

    document.addEventListener('click', event => {
        const open = event.target.closest('[data-open-media-dialog]');
        if (open) {
            document.getElementById(open.dataset.openMediaDialog).showModal();
        }
        if (event.target.closest('[data-close-media-dialog]')) {
            event.target.closest('dialog').close();
        }
    });

    document.addEventListener('submit', event => {
        const message = event.target.dataset.mediaConfirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    function selectionNames(items, getName) {
        const names = items.slice(0, 8).map(getName);
        if (items.length > names.length) {
            names.push('ほか ' + (items.length - names.length) + '件');
        }
        return names.join('\n');
    }

    const folderActions = document.querySelector('[data-media-folder-actions]');
    if (folderActions) {
        const choices = Array.from(folderActions.querySelectorAll('[data-media-folder-select]'));
        const selectAll = folderActions.querySelector('[data-media-folder-select-all]');
        const selectedCount = folderActions.querySelector('[data-media-folder-count]');
        const deleteButton = folderActions.querySelector('[data-media-folder-delete]');
        const checkedFolders = () => choices.filter(input => input.checked);

        function updateFolderSelection() {
            const count = checkedFolders().length;
            selectAll.checked = count === choices.length;
            selectAll.indeterminate = count > 0 && !selectAll.checked;
            selectedCount.textContent = count + '件選択';
            deleteButton.disabled = count === 0;
        }

        selectAll.addEventListener('change', () => {
            for (const input of choices) input.checked = selectAll.checked;
            updateFolderSelection();
        });
        folderActions.querySelector('nav').addEventListener('change', updateFolderSelection);
        window.addEventListener('pageshow', updateFolderSelection);
        updateFolderSelection();

        folderActions.addEventListener('submit', event => {
            const checked = checkedFolders();
            if (checked.length === 0) {
                event.preventDefault();
                return;
            }
            const names = selectionNames(checked, input => input.nextElementSibling.textContent.trim());
            if (!window.confirm(checked.length + '件のフォルダを削除しますか？\n\n' + names +
                '\n\n中のメディアは「未分類」に戻ります。')) {
                event.preventDefault();
                return;
            }
            folderActions.elements.folder_ids.value = JSON.stringify(checked.map(input => Number(input.value)));
        });
    }

    const actions = document.querySelector('[data-media-actions]');
    if (!actions) return;

    const checkboxes = Array.from(document.querySelectorAll('[data-media-select]'));
    const selectAll = actions.querySelector('[data-media-select-all]');
    const selectedCount = actions.querySelector('[data-media-count]');
    const destination = actions.elements.folder_id;
    const mediaIds = actions.elements.media_ids;
    const moveButton = actions.querySelector('[data-media-move]');
    const deleteButton = actions.querySelector('[data-media-bulk-delete]');
    const optimizeButton = actions.querySelector('[data-media-optimize]');
    const checkedItems = () => checkboxes.filter(input => input.checked);
    const canOptimize = input => Boolean(input.dataset.mediaOptimizeUrl);
    let optimizing = false;

    function updateSelection() {
        const checked = checkedItems();
        const count = checked.length;
        selectAll.checked = count === checkboxes.length;
        selectAll.indeterminate = count > 0 && !selectAll.checked;
        selectedCount.textContent = count + '件選択';
        moveButton.disabled = optimizing || count === 0 || destination.value === '';
        deleteButton.disabled = optimizing || count === 0;
        if (optimizeButton) optimizeButton.disabled = optimizing || !checked.some(canOptimize);
    }

    selectAll.addEventListener('change', () => {
        for (const input of checkboxes) {
            input.checked = selectAll.checked;
        }
        updateSelection();
    });
    document.querySelector('.media-grid').addEventListener('change', updateSelection);
    destination.addEventListener('change', updateSelection);
    window.addEventListener('pageshow', updateSelection);
    updateSelection();

    function setMediaIds(items) {
        mediaIds.value = JSON.stringify(items.map(input => Number(input.value)));
    }

    function sizeLabel(bytes) {
        if (bytes >= 1048576) return Math.round(bytes / 104857.6) / 10 + ' MB';
        if (bytes >= 1024) return Math.round(bytes / 102.4) / 10 + ' KB';
        return bytes + ' B';
    }

    optimizeButton?.addEventListener('click', async () => {
        const items = checkedItems().filter(canOptimize);
        if (optimizing || items.length === 0) return;

        const optimizeProgress = actions.querySelector('[data-media-optimize-progress]');
        const progress = optimizeProgress.querySelector('progress');
        const optimizeStatus = actions.querySelector('[data-media-optimize-status]');
        optimizing = true;
        clearDrag();
        updateSelection();
        progress.max = items.length;
        progress.value = 0;
        optimizeProgress.hidden = false;

        const body = new URLSearchParams({_csrf_token: actions.elements._csrf_token.value});
        let optimized = 0;
        let unchanged = 0;
        let failed = 0;
        let saved = 0;
        let stopped = false;
        let firstError = '';

        function report(state) {
            const counts = [
                '軽量化 ' + optimized + '件',
                '変更なし ' + unchanged + '件',
                '失敗 ' + failed + '件',
                '削減 ' + sizeLabel(saved),
            ];
            optimizeStatus.textContent = state + ' ' + progress.value + ' / ' + items.length +
                '件。' + counts.join('・') + (firstError ? '。' + firstError : '');
        }

        report('軽量化中');
        try {
            for (const input of items) {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 45000);
                try {
                    const response = await fetch(input.dataset.mediaOptimizeUrl, {
                        method: 'POST', body, redirect: 'manual', signal: controller.signal,
                    });
                    if (response.type === 'opaqueredirect' || [401, 403, 419].includes(response.status)) {
                        stopped = true;
                        firstError = 'ログイン状態を確認してください。';
                        break;
                    }

                    const result = await response.json().catch(() => null);
                    if (!response.ok || !Number.isSafeInteger(result?.bytes) || result.bytes < 1 ||
                        !Number.isSafeInteger(result.saved) || result.saved < 0) {
                        throw new Error(typeof result?.error === 'string' ? result.error : '画像を軽量化できませんでした。');
                    }

                    const card = input.closest('[data-media-card]');
                    card.querySelector('[data-media-size]').textContent = sizeLabel(result.bytes);
                    const dialog = document.getElementById(card.querySelector('[data-open-media-dialog]').dataset.openMediaDialog);
                    const editButton = dialog?.querySelector('[data-open-image-editor]');
                    if (editButton) editButton.dataset.imageBytes = result.bytes;
                    if (result.saved > 0) {
                        optimized++;
                        saved += result.saved;
                    } else {
                        unchanged++;
                    }
                } catch (error) {
                    failed++;
                    firstError ||= controller.signal.aborted ? '画像の応答が時間内に返りませんでした。'
                        : error instanceof TypeError ? '通信を確認してください。' : error.message;
                } finally {
                    clearTimeout(timeout);
                }
                progress.value++;
                report('軽量化中');
            }
        } finally {
            optimizing = false;
            updateSelection();
            report(stopped ? '軽量化を中断' : '軽量化完了');
        }
    });

    actions.addEventListener('submit', event => {
        const items = checkedItems();
        const deleting = event.submitter === deleteButton;
        if (optimizing || items.length === 0 || (!deleting && destination.value === '')) {
            event.preventDefault();
            return;
        }

        if (deleting) {
            const names = selectionNames(items, input => input.closest('[data-media-card]').dataset.mediaTitle);
            const message = items.length + '件のメディアを削除しますか？\n\n' + names
                + '\n\n記事などで使用中の場合は表示できなくなります。';
            if (!window.confirm(message)) {
                event.preventDefault();
                return;
            }
        }

        setMediaIds(items);
    });

    let draggedItems = null;
    let activeFolder = null;

    function showDropTarget(folder) {
        if (activeFolder === folder) return;
        activeFolder?.classList.remove('is-drop-target');
        folder?.classList.add('is-drop-target');
        activeFolder = folder;
    }

    function clearDrag() {
        draggedItems = null;
        showDropTarget(null);
    }

    document.addEventListener('dragstart', event => {
        const card = event.target.closest('[data-media-card]');
        if (!card || event.target !== card || !event.dataTransfer) return;
        if (optimizing) {
            event.preventDefault();
            return;
        }

        const input = card.querySelector('[data-media-select]');
        draggedItems = input.checked ? checkedItems() : [input];
        event.dataTransfer.setData('text/plain', input.value);
        event.dataTransfer.effectAllowed = 'move';
    });

    document.addEventListener('dragover', event => {
        if (!draggedItems) return;
        const folder = event.target.closest('[data-media-drop-folder]');
        showDropTarget(folder);
        if (folder) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        }
    });

    document.addEventListener('dragleave', event => {
        if (activeFolder?.contains(event.target) && !activeFolder.contains(event.relatedTarget)) {
            showDropTarget(null);
        }
    });

    document.addEventListener('drop', event => {
        const folder = event.target.closest('[data-media-drop-folder]');
        if (optimizing || !draggedItems || !folder) return;

        event.preventDefault();
        setMediaIds(draggedItems);
        destination.value = folder.dataset.mediaDropFolder;
        clearDrag();
        actions.submit();
    });

    document.addEventListener('dragend', clearDrag);
})();
