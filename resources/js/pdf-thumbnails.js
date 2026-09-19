// Renders a first-page thumbnail for PDFs in FilePond, which only previews images.
import workerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

import '../css/pdf-thumbnails.css';

const MAX_WIDTH = 400;

let pdfjsPromise = null;

function loadPdfJs() {
    if (! pdfjsPromise) {
        pdfjsPromise = import('pdfjs-dist').then((lib) => {
            lib.GlobalWorkerOptions.workerSrc = workerSrc;

            return lib;
        });
    }

    return pdfjsPromise;
}

async function renderThumbnail(bytes) {
    const pdfjs = await loadPdfJs();
    const task = pdfjs.getDocument({ data: bytes });

    try {
        const doc = await task.promise;
        const page = await doc.getPage(1);

        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: Math.min(MAX_WIDTH / base.width, 2) });

        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

        return canvas;
    } finally {
        task.destroy();
    }
}

async function bytesFor(item) {
    const root = item.closest('.filepond--root');
    const pond = root && window.FilePond ? window.FilePond.find(root) : null;

    if (! pond) {
        throw new Error('No FilePond instance');
    }

    const name = item.querySelector('.filepond--file-info-main').textContent.trim();
    const match = pond.getFiles().find((file) => file.filename === name);

    if (! match) {
        throw new Error('No matching file');
    }

    // Unsaved uploads still hold the bytes; saved ones only expose a signed URL.
    // FilePond's File comes from another realm, so `instanceof Blob` is unreliable.
    if (match.file && typeof match.file.arrayBuffer === 'function' && match.file.size) {
        return match.file.arrayBuffer();
    }

    if (typeof match.source !== 'string') {
        throw new Error('No readable source');
    }

    const response = await fetch(match.source, { credentials: 'same-origin' });

    if (! response.ok) {
        throw new Error('Receipt request failed');
    }

    return response.arrayBuffer();
}

async function decorate(item) {
    if (item.dataset.aurumPdfThumb) {
        return;
    }

    const name = item.querySelector('.filepond--file-info-main');

    if (! name || ! /\.pdf$/i.test(name.textContent.trim())) {
        return;
    }

    // Marked up front so a failed render is not retried by the observer.
    item.dataset.aurumPdfThumb = 'pending';

    try {
        const canvas = await renderThumbnail(await bytesFor(item));

        // Reusing FilePond's own wrapper keeps the filename and buttons aligned.
        const holder = document.createElement('div');
        holder.className = 'filepond--image-preview-wrapper aurum-pdf-thumb';
        holder.appendChild(canvas);

        item.querySelector('.filepond--file').appendChild(holder);
        item.classList.add('aurum-pdf-thumb-ready');
        item.dataset.aurumPdfThumb = 'done';
    } catch {
        item.dataset.aurumPdfThumb = 'failed';
    }
}

function scan() {
    document.querySelectorAll('.filepond--item').forEach(decorate);
}

let scheduled = null;

function schedule() {
    clearTimeout(scheduled);
    scheduled = setTimeout(scan, 200);
}

document.addEventListener('DOMContentLoaded', schedule);
document.addEventListener('livewire:navigated', schedule);

new MutationObserver(schedule).observe(document.documentElement, {
    childList: true,
    subtree: true,
});
