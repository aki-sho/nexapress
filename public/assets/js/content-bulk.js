'use strict';

for (const form of document.querySelectorAll('[data-content-bulk]')) {
    const elements = Array.from(form.elements);
    const checkboxes = elements.filter(input => input.name === 'selected_ids[]');
    const all = elements.find(input => input.hasAttribute('data-content-bulk-all'));
    const action = form.elements.namedItem('bulk_action');
    const apply = form.querySelector('button[type="submit"]');
    const count = form.querySelector('[data-content-bulk-count]');
    const selectedCount = () => checkboxes.filter(input => input.checked).length;

    function update() {
        const selected = selectedCount();
        all.checked = selected === checkboxes.length;
        all.indeterminate = selected > 0 && selected < checkboxes.length;
        count.textContent = selected + '件選択';
        apply.disabled = selected === 0 || action.value === '';
    }

    all.addEventListener('change', () => {
        for (const input of checkboxes) input.checked = all.checked;
        update();
    });
    for (const input of checkboxes) input.addEventListener('change', update);
    action.addEventListener('change', update);
    form.addEventListener('submit', event => {
        const selected = selectedCount();
        if (selected === 0 || (action.value === 'trash' && !window.confirm(selected + '件をゴミ箱へ移動しますか？'))) {
            event.preventDefault();
        }
    });

    window.addEventListener('pageshow', update);
    all.hidden = false;
    count.hidden = false;
    update();
}
