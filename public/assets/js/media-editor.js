(() => {
    const editor = document.querySelector('[data-media-editor]');
    if (!editor) return;

    const element = name => editor.querySelector('[data-editor-' + name + ']');
    const stage = element('stage');
    const canvas = element('canvas');
    const frame = element('frame');
    const controls = element('controls');
    const widthField = element('width');
    const ratioField = element('ratio');
    const qualityField = element('quality');
    const saveButton = element('save');
    const closeButton = element('close');
    const undoButton = element('undo');
    const maxDimension = Number(editor.dataset.maxDimension);
    const history = [];
    const cropRatios = {'1': 1, '16:9': 16 / 9};
    const compression = {standard: .82, light: .68};
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const rounded = (value, max) => clamp(Math.round(value), 1, max);
    const equal = (a, b) => JSON.stringify(a) === JSON.stringify(b);
    const bytesLabel = bytes => bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB'
        : bytes >= 1024 ? (bytes / 1024).toFixed(1) + ' KB' : bytes + ' B';
    const sizeLabel = (width, height) => width + ' × ' + height + ' px';
    const status = message => { element('status').textContent = message; };
    const fourCC = (view, offset) => String.fromCharCode(
        view.getUint8(offset), view.getUint8(offset + 1), view.getUint8(offset + 2), view.getUint8(offset + 3)
    );

    let bitmap = null;
    let state = null;
    let original = null;
    let opening = null;
    let mode = 'resize';
    let gesture = null;
    let geometry = null;
    let saving = false;
    let result = null;
    let scheduled = false;
    let previewKey = '';

    async function isAnimated(blob, mime) {
        if (mime === 'image/webp' && blob.size >= 21) {
            const head = new DataView(await blob.slice(0, 21).arrayBuffer());
            return fourCC(head, 12) === 'VP8X' && (head.getUint8(20) & 2) !== 0;
        }
        if (mime === 'image/png') {
            // An animated PNG declares its animation before the first image data chunk.
            let offset = 8;
            for (let chunks = 0; offset + 8 <= blob.size && chunks < 512; chunks++) {
                const header = new DataView(await blob.slice(offset, offset + 8).arrayBuffer());
                const name = fourCC(header, 4);
                if (name === 'acTL') return true;
                if (name === 'IDAT') return false;
                offset += header.getUint32(0) + 12;
            }
        }
        return false;
    }

    async function decodeImage(blob, url) {
        if (typeof createImageBitmap === 'function') return createImageBitmap(blob);
        const image = new Image();
        image.src = url;
        await image.decode();
        return image;
    }

    function imageSize(turns) {
        const {width, height} = original;
        return turns % 2 ? {width: height, height: width} : {width, height};
    }

    function drawImage(target, crop, turns) {
        const context = target.getContext('2d');
        const scaleX = target.width / crop.width;
        const scaleY = target.height / crop.height;
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.clearRect(0, 0, target.width, target.height);
        context.setTransform(scaleX, 0, 0, scaleY, -crop.x * scaleX, -crop.y * scaleY);
        if (turns === 1) context.transform(0, 1, -1, 0, original.height, 0);
        if (turns === 2) context.transform(-1, 0, 0, -1, original.width, original.height);
        if (turns === 3) context.transform(0, -1, 1, 0, 0, original.width);
        context.imageSmoothingQuality = target === canvas ? 'medium' : 'high';
        context.drawImage(bitmap, 0, 0);
        context.setTransform(1, 0, 0, 1, 0, 0);
    }

    function position(node, x, y, width, height) {
        node.style.left = x + 'px';
        node.style.top = y + 'px';
        node.style.width = width + 'px';
        node.style.height = height + 'px';
    }

    function refreshPreview() {
        if (!state || !editor.open || !stage.clientWidth) return;
        const editingCrop = mode === 'crop';
        const full = imageSize(state.turns);
        const image = editingCrop ? full : state;
        const scale = gesture?.geometry.scale ?? Math.min(stage.clientWidth / image.width, stage.clientHeight / image.height) * .84;
        const width = image.width * scale;
        const height = image.height * scale;
        let x = (stage.clientWidth - width) / 2;
        let y = (stage.clientHeight - height) / 2;
        if (gesture && !editingCrop) {
            const {geometry: start, directionX, directionY} = gesture;
            x = start.x + (directionX < 0 ? start.width - width : 0);
            y = start.y + (directionY < 0 ? start.height - height : 0);
        }
        geometry = {x, y, width, height, scale};

        const crop = editingCrop ? {x: 0, y: 0, ...full} : state.crop;
        // Keep the live preview bounded, even while dragging out a much larger output.
        const resolution = Math.min(1, 960 / width, 640 / height);
        const pixelsWide = Math.max(1, Math.round(width * resolution));
        const pixelsHigh = Math.max(1, Math.round(height * resolution));
        const key = [pixelsWide, pixelsHigh, state.turns, crop.x, crop.y, crop.width, crop.height].join('/');
        if (key !== previewKey) {
            canvas.width = pixelsWide;
            canvas.height = pixelsHigh;
            drawImage(canvas, crop, state.turns);
            previewKey = key;
        }
        position(canvas, x, y, width, height);
        if (editingCrop) {
            position(frame, x + state.crop.x * scale, y + state.crop.y * scale, state.crop.width * scale, state.crop.height * scale);
        } else {
            position(frame, x, y, width, height);
        }
        frame.hidden = false;

        const saved = result?.state === state ? result.data : null;
        element('output').textContent = sizeLabel(state.width, state.height)
            + (saved ? ' · ' + saved.format + ' · ' + bytesLabel(saved.bytes) : '');
    }

    function previewSoon() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => {
            scheduled = false;
            refreshPreview();
        });
    }

    function refreshControls() {
        controls.disabled = !state || saving;
        closeButton.disabled = saving;
        saveButton.disabled = saving || !state || result?.state === state;
        undoButton.disabled = saving || !history.length;
        stage.dataset.mode = mode;
        for (const button of editor.querySelectorAll('[data-editor-mode]')) {
            button.setAttribute('aria-pressed', String(button.dataset.editorMode === mode));
        }
        for (const panel of editor.querySelectorAll('[data-editor-options]')) {
            panel.hidden = panel.dataset.editorOptions !== mode;
        }
        element('hint').textContent = mode === 'crop' ? '枠を動かすか、角をドラッグして切り抜く範囲を指定します。'
            : '角や辺をドラッグしてサイズを調整します。Shiftを押しながら角を動かすと縦横比を保ちます。';
        if (state) {
            widthField.value = state.width;
            ratioField.value = state.ratio;
            qualityField.value = state.quality;
        }
        previewSoon();
    }

    function remember(previous) {
        history.push(previous);
        if (history.length > 32) history.shift();
    }

    function change(next) {
        if (gesture || saving) return;
        if (equal(state, next)) {
            refreshControls();
            return;
        }
        remember(state);
        state = next;
        status('');
        refreshControls();
    }

    function undo() {
        if (saving || gesture || !history.length) return false;
        state = history.pop();
        status('');
        refreshControls();
        return true;
    }

    function fitDimensions(width, height) {
        const scale = Math.min(1, maxDimension / width, maxDimension / height);
        return {width: rounded(width * scale, maxDimension), height: rounded(height * scale, maxDimension)};
    }

    function resizeWidth(width) {
        if (!Number.isFinite(width) || width < 1) {
            refreshControls();
            return;
        }
        change({...state, ...fitDimensions(width, width * state.height / state.width)});
    }

    function turn(direction) {
        const {x, y, width, height} = state.crop;
        const full = imageSize(state.turns);
        const crop = direction === 1 ? {x: full.height - y - height, y: x, width: height, height: width}
            : {x: y, y: full.width - x - width, width: height, height: width};
        change({...state, turns: (state.turns + direction + 4) % 4, crop,
            ratio: state.ratio === '16:9' ? 'free' : state.ratio, width: state.height, height: state.width});
    }

    function withCrop(base, crop) {
        const width = base.width * crop.width / base.crop.width;
        const height = base.ratio === 'free' ? base.height * crop.height / base.crop.height : width * crop.height / crop.width;
        return {...base, crop, ...fitDimensions(width, height)};
    }

    function selectRatio(ratio) {
        const aspect = cropRatios[ratio];
        let crop = state.crop;
        if (aspect) {
            const width = Math.max(1, Math.floor(Math.min(crop.width, crop.height * aspect)));
            const height = Math.max(1, Math.round(width / aspect));
            crop = {x: crop.x + Math.floor((crop.width - width) / 2),
                y: crop.y + Math.floor((crop.height - height) / 2), width, height};
        }
        change(withCrop({...state, ratio}, crop));
    }

    function makeGesture(handle) {
        return {start: state, geometry, directionX: handle.includes('e') ? 1 : handle.includes('w') ? -1 : 0,
            directionY: handle.includes('s') ? 1 : handle.includes('n') ? -1 : 0};
    }

    function resizeFromDrag(startWidth, startHeight, dx, dy, locked, maxWidth, maxHeight) {
        if (!locked) return {width: rounded(startWidth + dx, maxWidth), height: rounded(startHeight + dy, maxHeight)};
        const changeX = dx / startWidth;
        const changeY = dy / startHeight;
        const delta = Math.abs(changeX) >= Math.abs(changeY) ? changeX : changeY;
        const scale = Math.min(Math.max(1 / startWidth, 1 / startHeight, 1 + delta),
            maxWidth / startWidth, maxHeight / startHeight);
        return {width: rounded(startWidth * scale, maxWidth), height: rounded(startHeight * scale, maxHeight)};
    }

    function applyDrag(drag, dx, dy, shift) {
        const {start, directionX, directionY} = drag;
        if (mode === 'resize') {
            return {...start, ...resizeFromDrag(start.width, start.height, dx * directionX, dy * directionY,
                shift && directionX !== 0 && directionY !== 0, maxDimension, maxDimension)};
        }

        const crop = start.crop;
        const full = imageSize(start.turns);
        if (!directionX && !directionY) {
            return {...start, crop: {...crop, x: clamp(crop.x + Math.round(dx), 0, full.width - crop.width),
                y: clamp(crop.y + Math.round(dy), 0, full.height - crop.height)}};
        }

        const right = crop.x + crop.width;
        const bottom = crop.y + crop.height;
        const size = resizeFromDrag(crop.width, crop.height, dx * directionX, dy * directionY,
            start.ratio !== 'free', directionX < 0 ? right : full.width - crop.x,
            directionY < 0 ? bottom : full.height - crop.y);
        return withCrop(start, {...size, x: directionX < 0 ? right - size.width : crop.x,
            y: directionY < 0 ? bottom - size.height : crop.y});
    }

    function pointerMove(event) {
        if (gesture?.pointerId !== event.pointerId) return;
        const {geometry: start, x, y} = gesture;
        state = applyDrag(gesture, (event.clientX - x) / start.scale, (event.clientY - y) / start.scale, event.shiftKey);
        previewSoon();
    }

    function finishDrag(event, cancelled) {
        if (gesture?.pointerId !== event.pointerId) return;
        const previous = gesture.start;
        if (!cancelled) pointerMove(event);
        gesture = null;
        if (cancelled) state = previous;
        else if (!equal(previous, state)) {
            remember(previous);
            status('');
        } else state = previous;
        refreshControls();
    }

    frame.addEventListener('pointerdown', event => {
        if (!state || !geometry || saving || gesture || event.button !== 0) return;
        const handle = event.target.closest('[data-resize-handle]')?.dataset.resizeHandle || '';
        if (!handle && mode !== 'crop') return;
        event.preventDefault();
        gesture = {...makeGesture(handle), pointerId: event.pointerId, x: event.clientX, y: event.clientY};
        frame.setPointerCapture(event.pointerId);
    });
    frame.addEventListener('pointermove', pointerMove);
    frame.addEventListener('pointerup', event => finishDrag(event, false));
    frame.addEventListener('pointercancel', event => finishDrag(event, true));
    frame.addEventListener('lostpointercapture', event => finishDrag(event, true));
    frame.addEventListener('keydown', event => {
        const handle = event.target.dataset.resizeHandle;
        const delta = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]}[event.key];
        if (!handle || !delta || !state || !geometry || saving || gesture) return;
        event.preventDefault();
        change(applyDrag(makeGesture(handle), ...delta, event.shiftKey));
    });

    async function save() {
        if (!state || saving || gesture || result?.state === state) return;
        saving = true;
        refreshControls();
        status('画像を変換して保存しています。');
        const edited = state;
        const target = document.createElement('canvas');
        try {
            target.width = edited.width;
            target.height = edited.height;
            drawImage(target, edited.crop, edited.turns);
            const blob = await new Promise(resolve => target.toBlob(resolve, 'image/webp', compression[edited.quality]));
            if (!blob) {
                status('このサイズの画像を変換できませんでした。');
                return;
            }

            const data = new FormData();
            data.append('_csrf_token', editor.dataset.csrf);
            data.append('media_file', blob, blob.type === 'image/webp' ? 'edited.webp' : 'edited.png');
            const response = await fetch(original.saveUrl, {method: 'POST', body: data});
            const saved = await response.json().catch(() => null);
            if (!response.ok || !saved?.format) {
                status(saved?.error || '画像を保存できませんでした。');
                return;
            }
            result = {state: edited, data: saved};
            closeButton.textContent = '一覧に戻る';
            status('別の画像として保存しました。');
        } catch {
            status('画像を保存できませんでした。');
        } finally {
            target.width = target.height = 1;
            saving = false;
            refreshControls();
        }
    }

    async function open(button) {
        if (editor.open) return;
        const {imageUrl, imageMime, imageBytes, imageTitle, saveUrl} = button.dataset;
        const controller = new AbortController();
        opening = controller;
        element('title').textContent = imageTitle;
        closeButton.textContent = '閉じる';
        element('original').textContent = bytesLabel(Number(imageBytes));
        element('output').textContent = '';
        mode = 'resize';
        refreshControls();
        status('画像を読み込んでいます。');
        editor.showModal();

        try {
            const response = await fetch(imageUrl, {signal: controller.signal});
            if (!response.ok) throw new Error();
            const blob = await response.blob();
            if (await isAnimated(blob, imageMime)) {
                if (opening === controller) status('この画像はアニメーション形式です。静止画を選んでください。');
                return;
            }
            const loaded = await decodeImage(blob, imageUrl);
            if (opening !== controller || !editor.open) {
                loaded.close?.();
                return;
            }
            const width = loaded.width;
            const height = loaded.height;
            if (!width || !height) {
                loaded.close?.();
                throw new Error();
            }
            bitmap = loaded;
            original = {width, height, saveUrl};
            state = {turns: 0, crop: {x: 0, y: 0, width, height},
                ...fitDimensions(width, height), ratio: 'free', quality: 'standard'};
            element('original').textContent = sizeLabel(width, height) + ' · ' + bytesLabel(Number(imageBytes) || blob.size);
            status('');
            refreshControls();
        } catch {
            if (opening === controller && !controller.signal.aborted) status('画像を読み込めませんでした。');
        }
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-open-image-editor]');
        if (button) open(button);
    });
    for (const button of editor.querySelectorAll('[data-editor-mode]')) {
        button.addEventListener('click', () => {
            if (gesture) return;
            mode = button.dataset.editorMode;
            refreshControls();
        });
    }
    for (const button of editor.querySelectorAll('[data-editor-preset]')) {
        button.addEventListener('click', () => resizeWidth(Math.min(state.width, Number(button.dataset.editorPreset))));
    }
    for (const button of editor.querySelectorAll('[data-editor-turn]')) {
        button.addEventListener('click', () => turn(Number(button.dataset.editorTurn)));
    }
    widthField.addEventListener('change', () => resizeWidth(widthField.valueAsNumber));
    ratioField.addEventListener('change', () => selectRatio(ratioField.value));
    qualityField.addEventListener('change', () => change({...state, quality: qualityField.value}));
    undoButton.addEventListener('click', undo);
    saveButton.addEventListener('click', save);
    closeButton.addEventListener('click', () => editor.close());
    editor.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && !event.shiftKey && event.key.toLowerCase() === 'z' && undo()) {
            event.preventDefault();
        }
    });
    editor.addEventListener('cancel', event => {
        if (saving) event.preventDefault();
    });
    editor.addEventListener('close', () => {
        opening?.abort();
        opening = null;
        bitmap?.close?.();
        bitmap = state = original = gesture = geometry = null;
        history.length = 0;
        frame.hidden = true;
        canvas.width = canvas.height = 1;
        previewKey = '';
        if (result) window.location.reload();
    });
    new ResizeObserver(previewSoon).observe(stage);
})();
