module.exports = async function selectWidgetLocation(container, area, editing = false) {
    const row = container.locator('[data-widget-row="' + (editing ? 'form' : 'list') + '"]');
    const column = container.locator(editing ? 'select[name="area"]' : '[data-setting="area"]');
    if (!(await row.count())) {
        if (await column.count()) await column.selectOption(area);
        return;
    }

    const match = /^(?:header_after|footer_before|header|footer)(?:_(above|top|bottom))?[1-5]$/.exec(area);
    const rowValue = area === 'header_above' ? 'above' : match?.[1] || 'top';
    if (await row.inputValue() !== rowValue) await row.selectOption(rowValue);
    if (await column.count()) await column.selectOption(area);
};
