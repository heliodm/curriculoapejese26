'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const toggle   = document.getElementById('sidebarToggle');
    const sidebar  = document.getElementById('adminSidebar');
    const overlay  = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggle?.addEventListener('click', function () {
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            const a = bootstrap.Alert.getOrCreateInstance(el);
            if (a) a.close();
        }, 5000);
    });

    // Tooltip init
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});

function toggleField(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (!f) return;
    if (f.type === 'password') { f.type = 'text';     if (i) i.className = 'bi bi-eye-slash'; }
    else                       { f.type = 'password'; if (i) i.className = 'bi bi-eye';       }
}
