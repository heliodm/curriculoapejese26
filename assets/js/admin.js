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

function makeTableSortable(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    table.querySelectorAll('thead th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            const colIdx = Array.from(th.parentElement.children).indexOf(th);
            const asc    = !th.classList.contains('sort-asc');

            table.querySelectorAll('thead th').forEach(function (h) {
                h.classList.remove('sort-asc', 'sort-desc');
                const ic = h.querySelector('.sort-icon');
                if (ic) ic.className = 'bi bi-arrow-down-up sort-icon';
            });

            th.classList.add(asc ? 'sort-asc' : 'sort-desc');
            const ic = th.querySelector('.sort-icon');
            if (ic) ic.className = 'bi bi-arrow-' + (asc ? 'up' : 'down') + ' sort-icon';

            const isNumeric = th.dataset.sortType === 'numeric';
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort(function (a, b) {
                const aCell = a.cells[colIdx];
                const bCell = b.cells[colIdx];
                if (!aCell || !bCell) return 0;
                let aVal = (aCell.dataset.sort || aCell.textContent).trim();
                let bVal = (bCell.dataset.sort || bCell.textContent).trim();
                if (isNumeric) return asc ? (+aVal - +bVal) : (+bVal - +aVal);
                return asc ? aVal.localeCompare(bVal, 'pt-BR') : bVal.localeCompare(aVal, 'pt-BR');
            });

            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });
}

function adminLiveFilter(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    input.addEventListener('input', function () {
        const q    = this.value.toLowerCase().trim();
        const rows = tbody.querySelectorAll('tr[data-search]');
        rows.forEach(function (row) {
            row.style.display = (!q || row.dataset.search.includes(q)) ? '' : 'none';
        });
    });
}
