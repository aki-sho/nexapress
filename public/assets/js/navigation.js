document.querySelectorAll('.navigation-item-form').forEach(form => {
    const select = form.querySelector('[data-navigation-type]');
    const update = () => form.querySelectorAll('[data-navigation-target]').forEach(group => {
        const active = group.dataset.navigationTarget === select.value;
        group.hidden = !active;
        group.querySelectorAll('input, select').forEach(input => { input.disabled = !active; });
    });
    select.addEventListener('change', update);
    update();
});
