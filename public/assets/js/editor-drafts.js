document.addEventListener('DOMContentLoaded', () => {
    const configNode = document.getElementById('editor-drafts-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent);
    let storageAvailable = true;
    try {
        (config.savedKeys || []).forEach(key => localStorage.removeItem(key));
    } catch (_) { storageAvailable = false; }

    document.querySelectorAll('form[data-editor-kind]').forEach(form => {
        const key = `nx-editor:${config.userId}:${config.base}:${form.dataset.editorKind}:${form.dataset.editorId || 0}`;
        const names = ['title', 'slug', 'content', 'excerpt', 'status', 'category_id', 'featured_media_id'];
        const snapshot = () => {
            const data = {};
            const formData = new FormData(form);
            names.forEach(name => { if (formData.has(name)) data[name] = String(formData.get(name)); });
            return data;
        };
        const initial = JSON.stringify(snapshot());
        const originallyUnsaved = form.dataset.editorUnsaved === '1';
        let lastSaved = originallyUnsaved ? '' : initial;
        let submitting = false;
        let recoveryPending = false;
        let timer;
        const token = document.createElement('input');
        token.type = 'hidden'; token.name = '_editor_draft_key'; token.value = key;
        form.append(token);
        const panel = document.createElement('div');
        panel.className = 'editor-draft-status';
        panel.setAttribute('role', 'status');
        form.prepend(panel);
        const status = document.createElement('p');
        panel.append(status);
        status.textContent = '編集中の内容をこのブラウザに自動保存します。共有端末では保存・破棄後にログアウトしてください。';
        if (!storageAvailable) status.textContent = 'ブラウザの保存領域を利用できません。こまめに「保存」を押してください。';
        let saved;
        try { saved = JSON.parse(localStorage.getItem(key)); } catch (_) { saved = null; }
        const valid = saved && saved.data && typeof saved.data === 'object' && Number.isFinite(saved.time);
        if (valid && Date.now() - saved.time < 7 * 86400000 && JSON.stringify(saved.data) !== initial) {
            recoveryPending = true;
            status.textContent = `${new Date(saved.time).toLocaleString()} の未保存の入力があります。復元する内容を選んでください。`;
            const controls = document.createElement('div');
            controls.className = 'core-actions';
            const restore = document.createElement('button');
            restore.type = 'button'; restore.textContent = '入力を復元';
            const discard = document.createElement('button');
            discard.type = 'button'; discard.textContent = '保存済みの内容を使う';
            restore.addEventListener('click', () => {
                names.forEach(name => {
                    if (typeof saved.data[name] !== 'string') return;
                    form.querySelectorAll(`[name="${name}"]`).forEach(field => {
                        if (field.type === 'radio') field.checked = field.value === saved.data[name];
                        else field.value = saved.data[name];
                    });
                });
                // Classic Editor keeps a separate visual surface; use textContent via a
                // template only after the editor's sanitizer has processed the HTML.
                const visual = form.querySelector('.classic-editor-content');
                const textarea = form.querySelector('[name="content"]');
                const editor = window.NexaPressClassicEditor;
                if (visual && textarea && editor && typeof editor.sanitizeHtml === 'function') {
                    visual.innerHTML = editor.sanitizeHtml(textarea.value);
                }
                recoveryPending = false;
                controls.remove();
                status.textContent = '入力を復元しました。内容を確認して「保存」を押してください。';
                lastSaved = JSON.stringify(snapshot());
            });
            discard.addEventListener('click', () => {
                try { localStorage.removeItem(key); } catch (_) { /* Warning remains available. */ }
                recoveryPending = false; controls.remove();
                status.textContent = '未保存の入力を破棄しました。';
            });
            controls.append(restore, discard); panel.append(controls);
        } else if (valid && !originallyUnsaved) {
            try { localStorage.removeItem(key); } catch (_) { /* Ignore stale entries. */ }
        }
        const persist = () => {
            if (recoveryPending) return;
            const data = snapshot();
            const serialized = JSON.stringify(data);
            if (serialized === lastSaved) return;
            try {
                if (serialized === initial && !originallyUnsaved) localStorage.removeItem(key);
                else localStorage.setItem(key, JSON.stringify({ time: Date.now(), data }));
                lastSaved = serialized;
                status.textContent = serialized === initial && !originallyUnsaved ? '保存済みの内容と同じです。' :
                    `このブラウザに自動保存しました（${new Date().toLocaleTimeString()}）。サイトへの反映には「保存」が必要です。`;
            } catch (_) {
                status.textContent = '自動保存できませんでした。内容を保護するため「保存」を押してください。';
            }
        };
        const changed = () => { clearTimeout(timer); timer = setTimeout(persist, 800); };
        form.addEventListener('input', changed);
        form.addEventListener('change', changed);
        // Formatting controls can update textarea.value without firing input events.
        setInterval(persist, 5000);
        form.addEventListener('submit', () => { persist(); submitting = true; });
        window.addEventListener('beforeunload', event => {
            persist();
            if (!submitting && (originallyUnsaved || JSON.stringify(snapshot()) !== initial)) {
                event.preventDefault(); event.returnValue = '';
            }
        });
        document.addEventListener('visibilitychange', () => { if (document.hidden) persist(); });
        if (originallyUnsaved) persist();
    });
});
