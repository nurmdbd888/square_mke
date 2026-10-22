/**
 * In-browser OCR (tesseract.js) + confirm-before-apply for receipts/invoices.
 * Heuristics mirror App\Domain\Ocr\OcrTextParser.
 */
(function () {
    function normalizeDigits(text) {
        var bn = '০১২৩৪৫৬৭৮৯';
        return String(text || '').replace(/[০-৯]/g, function (ch) {
            return String(bn.indexOf(ch));
        });
    }

    function normalizeMoney(value) {
        var clean = String(value || '').replace(/,/g, '').trim();
        var n = parseFloat(clean);
        if (isNaN(n)) return clean;
        return n.toFixed(2);
    }

    function extractAmount(text) {
        var patterns = [
            /(?:amount|total|received|taka|tk|৳)\s*[:\-]?\s*((?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?)/i,
            /((?:\d{1,3}(?:,\d{3})+|\d+)\.\d{2})\b/,
        ];
        for (var i = 0; i < patterns.length; i++) {
            var m = text.match(patterns[i]);
            if (m) return normalizeMoney(m[1]);
        }
        return '';
    }

    function extractDate(text) {
        var m = text.match(/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/);
        if (!m) return '';
        var d = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        var y = parseInt(m[3], 10);
        if (y < 100) y += 2000;
        if (d < 1 || d > 31 || mo < 1 || mo > 12) return '';
        return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }

    function extractReference(text) {
        var m = text.match(/(?:ref(?:erence)?|cheque|chq|trx|txn)\s*[#.:]?\s*([A-Z0-9\-\/]{3,40})/i);
        return m ? m[1].toUpperCase() : '';
    }

    function extractInvoiceNumber(text) {
        var m = text.match(/(?:invoice|inv|bill|challan)\s*(?:no\.?|number|#)?\s*[:.]?\s*([A-Z0-9\-\/]{2,40})/i);
        return m ? m[1].toUpperCase() : '';
    }

    function extractParty(text) {
        var m = text.match(/(?:retailer|customer|party|supplier|sold to|bill to)\s*[:\-]?\s*([^\n\r]{3,80})/i);
        return m ? m[1].trim() : '';
    }

    function parseOcrText(text, mode) {
        var normalized = normalizeDigits(text);
        var flat = normalized.replace(/[ \t]+/g, ' ');
        return {
            amount: extractAmount(flat),
            date: extractDate(flat),
            reference: extractReference(flat),
            invoice_number: extractInvoiceNumber(flat),
            party: mode === 'invoice' ? extractParty(flat) : '',
            raw: normalized.trim(),
        };
    }

    function setInputValue(selector, value) {
        if (!value) return;
        var el = document.querySelector(selector);
        if (!el) return;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    window.parseOcrText = parseOcrText;

    window.ocrScanPanel = function (config) {
        config = config || {};
        return {
            mode: config.mode || 'receipt',
            busy: false,
            progress: 0,
            status: '',
            rawText: '',
            preview: { amount: '', date: '', reference: '', invoice_number: '', party: '', raw: '' },
            confirmOpen: false,
            error: '',
            fileName: '',

            onFile(event) {
                var file = event.target.files && event.target.files[0];
                if (!file) return;
                this.fileName = file.name;
                this.scanFile(file);
            },

            async scanFile(file) {
                this.error = '';
                this.busy = true;
                this.progress = 0;
                this.status = 'Loading OCR…';
                this.confirmOpen = false;
                try {
                    if (typeof Tesseract === 'undefined') {
                        throw new Error('OCR library failed to load. Check your network and reload.');
                    }
                    var self = this;
                    var result = await Tesseract.recognize(file, 'eng', {
                        logger: function (m) {
                            if (m.status === 'recognizing text' && m.progress != null) {
                                self.progress = Math.round(m.progress * 100);
                                self.status = 'Reading text… ' + self.progress + '%';
                            } else if (m.status) {
                                self.status = m.status;
                            }
                        },
                    });
                    this.rawText = (result && result.data && result.data.text) || '';
                    this.preview = parseOcrText(this.rawText, this.mode);
                    this.confirmOpen = true;
                    this.status = 'Review fields, then apply.';
                } catch (e) {
                    this.error = (e && e.message) ? e.message : 'OCR failed.';
                    this.status = '';
                } finally {
                    this.busy = false;
                }
            },

            applyToForm() {
                if (this.mode === 'receipt') {
                    setInputValue('[data-testid="payment-amount"]', this.preview.amount);
                    setInputValue('input[name="payment_date"]', this.preview.date);
                    setInputValue('input[name="reference"]', this.preview.reference || this.preview.invoice_number);
                    var alpineRoot = document.querySelector('form[enctype="multipart/form-data"][x-data]');
                    if (alpineRoot && alpineRoot._x_dataStack && alpineRoot._x_dataStack[0]) {
                        var data = alpineRoot._x_dataStack[0];
                        if (this.preview.amount && data.paymentAmount !== undefined) {
                            data.paymentAmount = this.preview.amount;
                        }
                        if (typeof data.recalc === 'function') {
                            data.recalc();
                        }
                    }
                } else {
                    setInputValue('input[name="number"]', this.preview.invoice_number);
                    setInputValue('input[name="doc_date"]', this.preview.date);
                    if (this.preview.party) {
                        var notes = document.querySelector('textarea[name="notes"]');
                        if (notes && !notes.value) {
                            notes.value = 'OCR party: ' + this.preview.party;
                            notes.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                }
                this.confirmOpen = false;
                this.status = 'Applied to form — review before saving.';
            },

            dismiss() {
                this.confirmOpen = false;
            },
        };
    };
})();
