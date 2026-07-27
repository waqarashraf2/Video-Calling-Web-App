import { jsPDF } from 'jspdf';

const $ = (id) => document.getElementById(id);

const elements = {
    input: $('imageInput'),
    dropZone: $('dropZone'),
    imageList: $('imageList'),
    emptyState: $('emptyState'),
    fileCount: $('fileCount'),
    form: $('pdfForm'),
    fileName: $('fileName'),
    pageSize: $('pageSize'),
    orientation: $('orientation'),
    margin: $('margin'),
    marginValue: $('marginValue'),
    quality: $('quality'),
    qualityValue: $('qualityValue'),
    background: $('background'),
    clearButton: $('clearButton'),
    downloadButton: $('downloadButton'),
    status: $('statusText'),
};

const state = {
    images: [],
    nextId: 1,
};

const pageSizes = {
    a4: [210, 297],
    letter: [215.9, 279.4],
};

function setStatus(message) {
    elements.status.textContent = message;
}

function normalizeFileName(value) {
    const cleaned = value.trim().replace(/[\\/:*?"<>|]+/g, '-');

    if (!cleaned) {
        return 'converted-images.pdf';
    }

    return cleaned.toLowerCase().endsWith('.pdf') ? cleaned : `${cleaned}.pdf`;
}

function bytesToLabel(bytes) {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function isImage(file) {
    return file.type.startsWith('image/');
}

async function imageDimensions(file) {
    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.decoding = 'async';
        image.src = url;
        await image.decode();

        return {
            width: image.naturalWidth,
            height: image.naturalHeight,
            previewUrl: url,
        };
    } catch {
        URL.revokeObjectURL(url);
        throw new Error(`${file.name} could not be read as an image.`);
    }
}

async function addFiles(fileList) {
    const files = [...fileList].filter(isImage);

    if (files.length === 0) {
        setStatus('Please choose image files only.');

        return;
    }

    setStatus('Reading images...');

    for (const file of files) {
        try {
            const dimensions = await imageDimensions(file);
            state.images.push({
                id: state.nextId++,
                file,
                ...dimensions,
            });
        } catch (error) {
            setStatus(error.message);
        }
    }

    render();
    setStatus(`${state.images.length} image${state.images.length === 1 ? '' : 's'} ready.`);
}

function moveImage(id, direction) {
    const index = state.images.findIndex((image) => image.id === id);
    const nextIndex = index + direction;

    if (index < 0 || nextIndex < 0 || nextIndex >= state.images.length) {
        return;
    }

    const [image] = state.images.splice(index, 1);
    state.images.splice(nextIndex, 0, image);
    render();
}

function removeImage(id) {
    const index = state.images.findIndex((image) => image.id === id);

    if (index < 0) {
        return;
    }

    URL.revokeObjectURL(state.images[index].previewUrl);
    state.images.splice(index, 1);
    render();
    setStatus(state.images.length ? `${state.images.length} image${state.images.length === 1 ? '' : 's'} ready.` : 'Select images to begin.');
}

function clearImages() {
    for (const image of state.images) {
        URL.revokeObjectURL(image.previewUrl);
    }

    state.images = [];
    elements.input.value = '';
    render();
    setStatus('Select images to begin.');
}

function render() {
    elements.fileCount.textContent = String(state.images.length);
    elements.emptyState.hidden = state.images.length > 0;
    elements.downloadButton.disabled = state.images.length === 0;
    elements.imageList.replaceChildren();

    state.images.forEach((image, index) => {
        const article = document.createElement('article');
        article.className = 'grid gap-3 rounded-lg border border-white/10 bg-zinc-900 p-3';

        const preview = document.createElement('img');
        preview.className = 'aspect-[4/3] w-full rounded-lg bg-zinc-950 object-contain';
        preview.src = image.previewUrl;
        preview.alt = image.file.name;

        const title = document.createElement('div');
        title.className = 'min-w-0';

        const name = document.createElement('strong');
        name.className = 'block truncate text-sm text-white';
        name.textContent = `${index + 1}. ${image.file.name}`;

        const meta = document.createElement('span');
        meta.className = 'block text-xs text-zinc-400';
        meta.textContent = `${image.width} x ${image.height} px, ${bytesToLabel(image.file.size)}`;

        const controls = document.createElement('div');
        controls.className = 'grid grid-cols-3 gap-2';

        const up = createControl('Up', () => moveImage(image.id, -1), index === 0);
        const down = createControl('Down', () => moveImage(image.id, 1), index === state.images.length - 1);
        const remove = createControl('Remove', () => removeImage(image.id), false);
        remove.className = `${remove.className} border-rose-300/20 bg-rose-500/10 text-rose-100 hover:border-rose-200`;

        title.append(name, meta);
        controls.append(up, down, remove);
        article.append(preview, title, controls);
        elements.imageList.append(article);
    });
}

function createControl(label, handler, disabled) {
    const button = document.createElement('button');
    button.className = 'min-h-10 rounded-lg border border-white/10 bg-zinc-950 px-3 text-sm font-bold text-zinc-100 transition hover:border-emerald-300 disabled:cursor-not-allowed disabled:opacity-40';
    button.type = 'button';
    button.textContent = label;
    button.disabled = disabled;
    button.addEventListener('click', handler);

    return button;
}

function getPageSpec(image) {
    if (elements.pageSize.value === 'fit') {
        const width = image.width * 0.264583;
        const height = image.height * 0.264583;

        return {
            format: [width, height],
            width,
            height,
            orientation: width > height ? 'landscape' : 'portrait',
        };
    }

    const base = pageSizes[elements.pageSize.value] || pageSizes.a4;
    const selectedOrientation = elements.orientation.value === 'auto'
        ? (image.width > image.height ? 'landscape' : 'portrait')
        : elements.orientation.value;
    const isLandscape = selectedOrientation === 'landscape';
    const width = isLandscape ? Math.max(...base) : Math.min(...base);
    const height = isLandscape ? Math.min(...base) : Math.max(...base);

    return {
        format: [width, height],
        width,
        height,
        orientation: selectedOrientation,
    };
}

async function drawImageToDataUrl(image, quality) {
    const bitmap = await createImageBitmap(image.file);
    const canvas = document.createElement('canvas');
    const maxSide = 2400;
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));

    const context = canvas.getContext('2d', { alpha: elements.background.value === 'transparent' });

    if (elements.background.value !== 'transparent') {
        context.fillStyle = elements.background.value === 'black' ? '#000000' : '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
    }

    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    return canvas.toDataURL('image/jpeg', quality);
}

function imagePlacement(page, image, margin) {
    const availableWidth = Math.max(1, page.width - (margin * 2));
    const availableHeight = Math.max(1, page.height - (margin * 2));
    const ratio = Math.min(availableWidth / image.width, availableHeight / image.height);
    const width = image.width * ratio;
    const height = image.height * ratio;

    return {
        x: (page.width - width) / 2,
        y: (page.height - height) / 2,
        width,
        height,
    };
}

async function buildPdf(event) {
    event.preventDefault();

    if (state.images.length === 0) {
        setStatus('Please add at least one image.');

        return;
    }

    elements.downloadButton.disabled = true;
    setStatus('Building PDF...');

    try {
        const margin = Number(elements.margin.value);
        const quality = Number(elements.quality.value) / 100;
        let pdf = null;

        for (const [index, image] of state.images.entries()) {
            const page = getPageSpec(image);

            if (!pdf) {
                pdf = new jsPDF({
                    unit: 'mm',
                    format: page.format,
                    orientation: page.orientation,
                    compress: true,
                });
            } else {
                pdf.addPage(page.format, page.orientation);
            }

            const dataUrl = await drawImageToDataUrl(image, quality);
            const placement = imagePlacement(page, image, margin);
            pdf.addImage(dataUrl, 'JPEG', placement.x, placement.y, placement.width, placement.height, undefined, 'FAST');
            setStatus(`Added page ${index + 1} of ${state.images.length}...`);
        }

        pdf.save(normalizeFileName(elements.fileName.value));
        setStatus('PDF downloaded.');
    } catch (error) {
        setStatus(error?.message || 'PDF could not be generated.');
    } finally {
        elements.downloadButton.disabled = state.images.length === 0;
    }
}

elements.input.addEventListener('change', (event) => addFiles(event.target.files));
elements.form.addEventListener('submit', buildPdf);
elements.clearButton.addEventListener('click', clearImages);

elements.margin.addEventListener('input', () => {
    elements.marginValue.textContent = elements.margin.value;
});

elements.quality.addEventListener('input', () => {
    elements.qualityValue.textContent = elements.quality.value;
});

['dragenter', 'dragover'].forEach((eventName) => {
    elements.dropZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        elements.dropZone.classList.add('border-emerald-300', 'bg-emerald-300/10');
    });
});

['dragleave', 'drop'].forEach((eventName) => {
    elements.dropZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        elements.dropZone.classList.remove('border-emerald-300', 'bg-emerald-300/10');
    });
});

elements.dropZone.addEventListener('drop', (event) => {
    addFiles(event.dataTransfer.files);
});

render();
