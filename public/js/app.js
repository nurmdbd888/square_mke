// Square Distributor Manager: small helpers on top of HTMX and Alpine.
(function () {
    'use strict';

    var token = document.querySelector('meta[name="csrf-token"]');
    var BANGLA = '০১২৩৪৫৬৭৮৯';
    var ASCII = '0123456789';

    // Every HTMX request carries the CSRF token, like every form.
    document.addEventListener('htmx:configRequest', function (event) {
        if (token) {
            event.detail.headers['X-CSRF-TOKEN'] = token.getAttribute('content');
        }
    });

    // If the session ran out, HTMX would swap the login page into a table:
    // reload the whole page instead.
    document.addEventListener('htmx:beforeSwap', function (event) {
        var xhr = event.detail.xhr;
        var url = xhr.responseURL || '';

        if (xhr.status === 419 || xhr.status === 401 || /\/login(\?|$)/.test(url)) {
            event.detail.shouldSwap = false;
            window.location.reload();
        }
    });

    // Alpine components inside HTMX swaps need a second pass.
    document.addEventListener('htmx:afterSwap', function (event) {
        if (window.Alpine && event.detail && event.detail.elt) {
            window.Alpine.initTree(event.detail.elt);
        }
    });

    // <form data-confirm="…"> or submitter data-confirm asks before submitting.
    document.addEventListener('submit', function (event) {
        // Header bulk Delete already confirmed in its click handler.
        if (event.submitter && event.submitter.getAttribute('data-bulk-delete-form')) {
            return;
        }

        var message = event.target.getAttribute('data-confirm');
        if (!message && event.submitter) {
            message = event.submitter.getAttribute('data-confirm');
        }

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    }, true);

    // Header "Delete" on list pages: confirm, then let the native submit POST the form (CSRF + action=void).
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-bulk-delete-form]');
        if (!btn) {
            return;
        }

        var form = document.getElementById(btn.getAttribute('data-bulk-delete-form'));
        if (!form) {
            event.preventDefault();
            return;
        }

        var selected = form.querySelectorAll('input[name="ids[]"]:checked');
        if (selected.length === 0) {
            event.preventDefault();
            window.alert(btn.getAttribute('data-empty') || 'Select at least one row.');
            return;
        }

        var message = btn.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }

        var action = btn.getAttribute('data-bulk-delete-action') || 'void';
        var select = form.querySelector('select[name="action"]');
        if (select) {
            select.value = action;
        }

        // Force POST + action URL so a degraded form never GETs /sales/bulk (404/405).
        form.method = 'post';
        form.setAttribute('method', 'post');
        var actionUrl = btn.getAttribute('data-bulk-delete-action-url');
        if (actionUrl) {
            form.setAttribute('action', actionUrl);
        }

        // type=submit + form= already posts; only requestSubmit when the button is type=button.
        if (btn.getAttribute('type') === 'button') {
            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });

    function toAsciiDigits(text) {
        return String(text).replace(/[০-৯]/g, function (ch) {
            return ASCII[BANGLA.indexOf(ch)] || ch;
        });
    }

    function pad2(n) {
        n = String(n);

        return n.length === 1 ? '0' + n : n;
    }

    function formatIso(iso, phpFormat) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return '';
        }
        var parts = iso.split('-');
        var map = { Y: parts[0], m: parts[1], d: parts[2] };

        return phpFormat.replace(/Y|m|d/g, function (token) {
            return map[token];
        });
    }

    function parseDisplay(display, phpFormat) {
        display = toAsciiDigits(String(display || '').trim());
        if (display === '') {
            return '';
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(display)) {
            return display;
        }

        var sepMatch = phpFormat.match(/[/\-.]/);
        var sep = sepMatch ? sepMatch[0] : '/';
        var tokens = phpFormat.split(sep);
        var bits = display.split(sep);
        if (tokens.length !== 3 || bits.length !== 3) {
            return null;
        }

        var values = {};
        for (var i = 0; i < 3; i++) {
            values[tokens[i]] = bits[i];
        }
        if (!values.Y || !values.m || !values.d) {
            return null;
        }

        var y = parseInt(values.Y, 10);
        var m = parseInt(values.m, 10);
        var d = parseInt(values.d, 10);
        if (!y || !m || !d || m < 1 || m > 12 || d < 1 || d > 31) {
            return null;
        }

        var iso = y + '-' + pad2(m) + '-' + pad2(d);
        var dt = new Date(y, m - 1, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) {
            return null;
        }

        return iso;
    }

    /**
     * Alpine factory for <x-date-input>. Posts ISO (Y-m-d) via hidden input;
     * visible text uses the Settings date format.
     */
    window.appDateInput = function (config) {
        config = config || {};

        return {
            iso: config.iso || '',
            display: config.display || '',
            format: config.format || 'd/m/Y',
            placeholder: config.placeholder || 'dd/mm/yyyy',
            required: !!config.required,
            invalid: false,
            _onSubmit: null,

            init: function () {
                var self = this;
                var form = this.$el.closest('form');
                if (!form) {
                    return;
                }
                this._onSubmit = function (event) {
                    self.syncFromDisplay();
                    if (self.invalid || (self.required && !self.iso)) {
                        event.preventDefault();
                        self.invalid = true;
                        self.$nextTick(function () {
                            self.$refs.textInput && self.$refs.textInput.focus();
                        });
                    }
                };
                form.addEventListener('submit', this._onSubmit);
            },

            destroy: function () {
                var form = this.$el.closest('form');
                if (form && this._onSubmit) {
                    form.removeEventListener('submit', this._onSubmit);
                }
            },

            syncFromDisplay: function () {
                var trimmed = String(this.display || '').trim();
                if (trimmed === '') {
                    this.iso = '';
                    this.display = '';
                    this.invalid = false;

                    return;
                }
                var parsed = parseDisplay(trimmed, this.format);
                if (!parsed) {
                    this.iso = '';
                    this.invalid = true;

                    return;
                }
                this.invalid = false;
                this.iso = parsed;
                this.display = formatIso(parsed, this.format);
            },

            fromPicker: function (event) {
                var value = event.target.value || '';
                this.iso = value;
                this.display = value ? formatIso(value, this.format) : '';
                this.invalid = false;
            },

            /**
             * Keep the native control in sync before opening the UI.
             * The calendar chrome is a full-size role=button hit target. The
             * type=date input stays sized (not h-0/w-0) so showPicker() is
             * allowed, but has pointer-events:none — Chromium only activates a
             * tiny glyph inside opacity-0 date overlays, which felt broken.
             */
            preparePicker: function () {
                var picker = this.$refs.picker;
                if (!picker) {
                    return;
                }
                if (this.iso && picker.value !== this.iso) {
                    picker.value = this.iso;
                }
            },

            openPicker: function () {
                var picker = this.$refs.picker;
                if (!picker) {
                    return;
                }
                this.preparePicker();
                if (typeof picker.showPicker === 'function') {
                    try {
                        picker.showPicker();

                        return;
                    } catch (e) {
                        // Element must be attached with a non-zero layout box.
                    }
                }
                // Fallback: briefly enable pointer events and synthesize a click.
                picker.style.pointerEvents = 'auto';
                try {
                    picker.focus();
                    picker.click();
                } finally {
                    picker.style.pointerEvents = 'none';
                }
            },
        };
    };

    /**
     * Stock index: physical stock check (IN STORE Available / Not Available).
     */
    window.stockCheckPage = function (config) {
        config = config || {};

        return {
            checking: !!config.checking,
            exportCheckId: config.exportCheckId || null,
            confirmUrl: config.confirmUrl,
            exportBase: config.exportBase,
            startUrl: config.startUrl,
            cancelUrl: config.cancelUrl,
            csrf: config.csrf,
            productIds: config.productIds || [],
            marks: {},
            submitting: false,

            get markedCount() {
                return Object.keys(this.marks).length;
            },

            get canConfirm() {
                return this.productIds.length > 0 && this.markedCount === this.productIds.length;
            },

            get exportUrl() {
                return this.exportCheckId ? (this.exportBase + '/' + this.exportCheckId + '/export') : '#';
            },

            start: function () {
                if (this.checking) {
                    return;
                }
                window.location = this.startUrl;
            },

            cancel: function () {
                window.location = this.cancelUrl;
            },

            setMark: function (productId, value) {
                this.marks = Object.assign({}, this.marks, { [productId]: value });
            },

            confirmStock: function () {
                if (!this.canConfirm || this.submitting) {
                    return;
                }
                this.submitting = true;
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = this.confirmUrl;
                form.style.display = 'none';

                var token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = this.csrf;
                form.appendChild(token);

                var self = this;
                this.productIds.forEach(function (id, index) {
                    var pid = document.createElement('input');
                    pid.type = 'hidden';
                    pid.name = 'items[' + index + '][product_id]';
                    pid.value = String(id);
                    form.appendChild(pid);

                    var store = document.createElement('input');
                    store.type = 'hidden';
                    store.name = 'items[' + index + '][in_store]';
                    store.value = self.marks[id];
                    form.appendChild(store);
                });

                document.body.appendChild(form);
                form.submit();
            },
        };
    };
})();

