'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // Tooltip init
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Hide broken images instead of showing a broken-icon (replaces inline onerror)
    document.querySelectorAll('img[data-hide-on-error]').forEach(function (img) {
        img.addEventListener('error', function () { this.style.display = 'none'; });
    });
});
