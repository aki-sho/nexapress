const assert = require('node:assert/strict');

module.exports = async function openLegacySections(page, base, title, width) {
    await page.goto(base + '/admin/design');
    const catalog = JSON.parse(await page.locator('#nx-design-data').textContent());
    const target = catalog.targets.page.find(item => item.name === title);
    assert(target, 'Expected a fixed page called ' + title);
    const state = catalog.themes[catalog.active].state;
    const saved = await (await page.request.get(base + '/admin/design/page-layout/' + target.id)).json();
    const section = {groups: [[{type: 'page_title'}, {type: 'page_content'}], []]};
    state.content = [{kind: 'page', id: target.id, revision: saved.revision, overrides: {
        ...saved.overrides, enabled: true, sectionCount: 1, sections: [section],
        ...(width ? {width} : {})
    }}];
    const response = await page.request.post(base + '/admin/design/publish', {form: {
        _csrf_token: catalog.token, design: JSON.stringify(state), revision: state.revision,
    }});
    assert.equal(response.status(), 200, await response.text());
    await page.goto(base + '/admin/design?layout=page:' + target.id);
    await page.locator('[data-page-field="section"]').waitFor();
};
