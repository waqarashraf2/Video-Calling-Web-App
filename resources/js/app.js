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
    files: [],
    nextId: 1,
};

const pageSizes = {
    a4: [210, 297],
    letter: [215.9, 279.4],
};

async function getPdfDocument() {
    const { PDFDocument } = await import('pdf-lib');

    return PDFDocument;
}

async function getJsPdf() {
    const { jsPDF } = await import('jspdf');

    return jsPDF;
}

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

function isPdf(file) {
    return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
}

async function imageDetails(file) {
    const url = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.decoding = 'async';
        image.src = url;
        await image.decode();

        return {
            type: 'image',
            width: image.naturalWidth,
            height: image.naturalHeight,
            previewUrl: url,
        };
    } catch {
        URL.revokeObjectURL(url);
        throw new Error(`${file.name} could not be read as an image.`);
    }
}

async function pdfDetails(file) {
    const PDFDocument = await getPdfDocument();
    const bytes = await file.arrayBuffer();
    const document = await PDFDocument.load(bytes, { ignoreEncryption: true });

    return {
        type: 'pdf',
        bytes,
        pageCount: document.getPageCount(),
        width: null,
        height: null,
        previewUrl: null,
    };
}

async function addFiles(fileList) {
    const files = [...fileList].filter((file) => isImage(file) || isPdf(file));

    if (files.length === 0) {
        setStatus('Please choose image or PDF files only.');

        return;
    }

    setStatus('Reading files...');

    for (const file of files) {
        try {
            const details = isPdf(file) ? await pdfDetails(file) : await imageDetails(file);
            state.files.push({
                id: state.nextId++,
                file,
                ...details,
            });
        } catch (error) {
            setStatus(error.message);
        }
    }

    render();
    setStatus(`${state.files.length} file${state.files.length === 1 ? '' : 's'} ready.`);
}

function moveFile(id, direction) {
    const index = state.files.findIndex((file) => file.id === id);
    const nextIndex = index + direction;

    if (index < 0 || nextIndex < 0 || nextIndex >= state.files.length) {
        return;
    }

    const [file] = state.files.splice(index, 1);
    state.files.splice(nextIndex, 0, file);
    render();
}

function removeFile(id) {
    const index = state.files.findIndex((file) => file.id === id);

    if (index < 0) {
        return;
    }

    if (state.files[index].previewUrl) {
        URL.revokeObjectURL(state.files[index].previewUrl);
    }

    state.files.splice(index, 1);
    render();
    setStatus(state.files.length ? `${state.files.length} file${state.files.length === 1 ? '' : 's'} ready.` : 'Select images or PDFs to begin.');
}

function clearFiles() {
    for (const file of state.files) {
        if (file.previewUrl) {
            URL.revokeObjectURL(file.previewUrl);
        }
    }

    state.files = [];
    elements.input.value = '';
    render();
    setStatus('Select images or PDFs to begin.');
}

function render() {
    elements.fileCount.textContent = String(state.files.length);
    elements.emptyState.hidden = state.files.length > 0;
    elements.downloadButton.disabled = state.files.length === 0;
    elements.imageList.replaceChildren();

    state.files.forEach((item, index) => {
        const article = document.createElement('article');
        article.className = 'grid gap-3 rounded-lg border border-white/10 bg-zinc-900 p-3';

        const preview = item.type === 'image' ? document.createElement('img') : document.createElement('div');
        preview.className = 'grid aspect-[4/3] w-full place-items-center rounded-lg bg-zinc-950 object-contain text-3xl font-black text-emerald-200';

        if (item.type === 'image') {
            preview.src = item.previewUrl;
            preview.alt = item.file.name;
        } else {
            preview.textContent = 'PDF';
        }

        const title = document.createElement('div');
        title.className = 'min-w-0';

        const name = document.createElement('strong');
        name.className = 'block truncate text-sm text-white';
        name.textContent = `${index + 1}. ${item.file.name}`;

        const meta = document.createElement('span');
        meta.className = 'block text-xs text-zinc-400';
        meta.textContent = item.type === 'image'
            ? `${item.width} x ${item.height} px, ${bytesToLabel(item.file.size)}`
            : `${item.pageCount} page${item.pageCount === 1 ? '' : 's'}, ${bytesToLabel(item.file.size)}`;

        const controls = document.createElement('div');
        controls.className = 'grid grid-cols-3 gap-2';

        const up = createControl('Up', () => moveFile(item.id, -1), index === 0);
        const down = createControl('Down', () => moveFile(item.id, 1), index === state.files.length - 1);
        const remove = createControl('Remove', () => removeFile(item.id), false);
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

async function createImagePdf(imageItems) {
    const jsPDF = await getJsPdf();
    const margin = Number(elements.margin.value);
    const quality = Number(elements.quality.value) / 100;
    let pdf = null;

    for (const image of imageItems) {
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
    }

    return pdf.output('arraybuffer');
}

async function appendPdfBytes(targetDocument, bytes) {
    const PDFDocument = await getPdfDocument();
    const sourceDocument = await PDFDocument.load(bytes, { ignoreEncryption: true });
    const copiedPages = await targetDocument.copyPages(sourceDocument, sourceDocument.getPageIndices());

    copiedPages.forEach((page) => targetDocument.addPage(page));
}

async function appendImageAsPdf(targetDocument, imageItem) {
    const bytes = await createImagePdf([imageItem]);
    await appendPdfBytes(targetDocument, bytes);
}

async function buildPdf(event) {
    event.preventDefault();

    if (state.files.length === 0) {
        setStatus('Please add at least one image or PDF.');

        return;
    }

    elements.downloadButton.disabled = true;
    setStatus('Building merged PDF...');

    try {
        const PDFDocument = await getPdfDocument();
        const mergedDocument = await PDFDocument.create();

        for (const [index, item] of state.files.entries()) {
            if (item.type === 'pdf') {
                await appendPdfBytes(mergedDocument, item.bytes);
            } else {
                await appendImageAsPdf(mergedDocument, item);
            }

            setStatus(`Merged file ${index + 1} of ${state.files.length}...`);
        }

        const mergedBytes = await mergedDocument.save();
        const blob = new Blob([mergedBytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = normalizeFileName(elements.fileName.value);
        link.click();
        URL.revokeObjectURL(url);
        setStatus('PDF downloaded.');
    } catch (error) {
        setStatus(error?.message || 'PDF could not be generated.');
    } finally {
        elements.downloadButton.disabled = state.files.length === 0;
    }
}

elements.input.addEventListener('change', (event) => addFiles(event.target.files));
elements.form.addEventListener('submit', buildPdf);
elements.clearButton.addEventListener('click', clearFiles);

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
