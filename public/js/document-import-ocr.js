(function () {
    const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
    const TESSERACT_WORKER_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/worker.min.js';
    const TESSDATA_URL = 'https://tessdata.projectnaptha.com/4.0.0';
    const PDFJS_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/legacy/build/pdf.min.js';
    const PDFJS_WORKER_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/legacy/build/pdf.worker.min.js';
    const MAX_PAGES = 100;

    function loadScript(url, ready) {
        if (ready()) return Promise.resolve();

        return new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[data-import-library="' + url + '"]');
            const script = existing || document.createElement('script');
            script.src = url;
            script.async = true;
            script.dataset.importLibrary = url;
            script.onload = function () {
                if (ready()) resolve();
                else reject(new Error('The required file-processing library did not load.'));
            };
            script.onerror = function () {
                reject(new Error('Could not load a file-processing library. Check your internet connection and try again.'));
            };
            if (!existing) document.head.appendChild(script);
        });
    }

    function isPdf(file) {
        return file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
    }

    function supportedImage(file) {
        return /^image\/(jpeg|png|webp)$/i.test(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name);
    }

    function makeCanvas(width, height) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        return canvas;
    }

    function imageCanvas(file) {
        return createImageBitmap(file).then(function (image) {
            const scale = Math.min(1, 2400 / Math.max(image.width, image.height));
            const canvas = makeCanvas(Math.max(1, Math.round(image.width * scale)), Math.max(1, Math.round(image.height * scale)));
            canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
            image.close();
            return canvas;
        });
    }

    async function createPageList(files) {
        await loadScript(PDFJS_URL, function () { return !!window.pdfjsLib; });
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
        const pageList = [];

        for (const file of files) {
            if (isPdf(file)) {
                const pdf = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
                if (pageList.length + pdf.numPages > MAX_PAGES) {
                    await pdf.destroy();
                    throw new Error('A batch can contain up to 100 pages. Split this PDF and try again.');
                }
                for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
                    pageList.push({ kind: 'pdf', file: file, pdf: pdf, pageNumber: pageNumber });
                }
            } else if (supportedImage(file)) {
                pageList.push({ kind: 'image', file: file });
            } else {
                throw new Error('Use PDF, JPG, PNG, or WebP files for scanned invoices.');
            }
        }

        if (pageList.length > MAX_PAGES) {
            throw new Error('A batch can contain up to 100 pages. Split the selected files and try again.');
        }
        return pageList;
    }

    async function pageCanvas(page) {
        if (page.kind === 'image') return imageCanvas(page.file);

        const pdfPage = await page.pdf.getPage(page.pageNumber);
        const base = pdfPage.getViewport({ scale: 1 });
        const viewport = pdfPage.getViewport({ scale: Math.min(2.5, 2200 / Math.max(base.width, base.height)) });
        const canvas = makeCanvas(Math.ceil(viewport.width), Math.ceil(viewport.height));
        await pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
        return canvas;
    }

    function setStatus(form, message) {
        const status = form.querySelector('[data-invoice-ocr-status]');
        status.textContent = message;
        status.classList.remove('hidden');
    }

    function setError(form, message) {
        const error = form.querySelector('[data-invoice-ocr-error]');
        error.textContent = message;
        error.classList.remove('hidden');
    }

    function setBusy(form, busy) {
        const button = form.querySelector('[data-invoice-ocr-submit]');
        button.disabled = busy;
        button.textContent = busy ? 'Reading invoices…' : button.dataset.originalLabel;
    }

    document.querySelectorAll('[data-invoice-ocr-form]').forEach(function (form) {
        const button = form.querySelector('[data-invoice-ocr-submit]');
        button.dataset.originalLabel = button.textContent;
        let readyToSubmit = false;

        form.addEventListener('submit', async function (event) {
            if (readyToSubmit) return;
            const scanInput = form.querySelector('[data-invoice-scans]');
            const spreadsheetInput = form.querySelector('[data-invoice-spreadsheet]');
            const files = Array.from(scanInput.files || []);
            if (files.length === 0) return;

            event.preventDefault();
            const error = form.querySelector('[data-invoice-ocr-error]');
            error.textContent = '';
            error.classList.add('hidden');
            if (spreadsheetInput.files.length > 0) {
                setError(form, 'Upload a spreadsheet or scanned files in one batch, not both.');
                return;
            }
            if (files.length > 20 || files.some(function (file) { return file.size > 50 * 1024 * 1024; })) {
                setError(form, 'Choose up to 20 files, with each file no larger than 50 MB.');
                return;
            }

            setBusy(form, true);
            let worker = null;
            let pages = [];
            let pageList = [];
            try {
                setStatus(form, 'Preparing selected files…');
                pageList = await createPageList(files);
                if (pageList.length === 0 || pageList.length > MAX_PAGES) {
                    throw new Error('Select between 1 and 100 invoice pages.');
                }
                await loadScript(TESSERACT_URL, function () { return !!window.Tesseract; });
                worker = await window.Tesseract.createWorker('eng', 1, {
                    workerPath: TESSERACT_WORKER_URL,
                    langPath: TESSDATA_URL,
                    logger: function (message) {
                        if (message.status === 'recognizing text') {
                            setStatus(form, 'Reading invoices… ' + Math.round(message.progress * 100) + '%');
                        }
                    }
                });

                for (let index = 0; index < pageList.length; index += 1) {
                    const page = pageList[index];
                    setStatus(form, 'Reading page ' + (index + 1) + ' of ' + pageList.length + '…');
                    const canvas = await pageCanvas(page);
                    const result = await worker.recognize(canvas);
                    pages.push({ text: result.data.text || '' });
                    canvas.width = 0;
                    canvas.height = 0;
                }

                form.querySelector('[data-invoice-ocr-pages]').value = JSON.stringify(pages);
                readyToSubmit = true;
                setStatus(form, 'Text extraction complete. Uploading for review…');
                setBusy(form, false);
                form.requestSubmit(button);
            } catch (scanError) {
                readyToSubmit = false;
                setError(form, scanError && scanError.message ? scanError.message : 'Could not read the selected files.');
                setBusy(form, false);
            } finally {
                if (worker) await worker.terminate();
                await Promise.all(pageList.filter(function (page) { return page.pdf; }).map(function (page) {
                    return page.pdf.destroy();
                }));
            }
        });
    });
}());
