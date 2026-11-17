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

    const actions = document.querySelector('[data-media-actions]');
    if (!actions) return;

    const checkboxes = Array.from(document.querySelectorAll('[data-media-select]'));
    const selectAll = actions.querySelector('[data-media-select-all]');
    const selectedCount = actions.querySelector('[data-media-count]');
    const destination = actions.elements.folder_id;
    const mediaIds = actions.elements.media_ids;
    const moveButton = actions.querySelector('[data-media-move]');
    const deleteButton = actions.querySelector('[data-media-bulk-delete]');
    const checkedItems = () => checkboxes.filter(input => input.checked);

    function updateSelection() {
        const count = checkedItems().length;
        selectAll.checked = count === checkboxes.length;
        selectAll.indeterminate = count > 0 && !selectAll.checked;
        selectedCount.textContent = count + '件選択';
        moveButton.disabled = count === 0 || destination.value === '';
        deleteButton.disabled = count === 0;
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

    actions.addEventListener('submit', event => {
        const items = checkedItems();
        const deleting = event.submitter === deleteButton;
        if (items.length === 0 || (!deleting && destination.value === '')) {
            event.preventDefault();
            return;
        }

        if (deleting) {
            const names = items.slice(0, 8).map(input => input.closest('[data-media-card]').dataset.mediaTitle);
            if (items.length > names.length) {
                names.push('ほか ' + (items.length - names.length) + '件');
            }
            const message = items.length + '件のメディアを削除しますか？\n\n' + names.join('\n')
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
        if (!draggedItems || !folder) return;

        event.preventDefault();
        setMediaIds(draggedItems);
        destination.value = folder.dataset.mediaDropFolder;
        clearDrag();
        actions.submit();
    });

    document.addEventListener('dragend', clearDrag);
})();
