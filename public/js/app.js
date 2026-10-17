// Square Distributor Manager: small helpers on top of HTMX and Alpine.
(function () {
    'use strict';

    var token = document.querySelector('meta[name="csrf-token"]');

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

    // <form data-confirm="Are you sure?"> asks before submitting.
    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-confirm');

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    }, true);
})();
