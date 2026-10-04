import './dashboard.js';
const sidebar = document.querySelector('#app-sidebar');

if (sidebar) {
    const sidebarToggle = sidebar.querySelector('[data-sidebar-toggle]');

    sidebarToggle?.addEventListener('click', () => {
        const collapsed = sidebar.classList.toggle('collapsed');

        sidebarToggle.innerHTML = collapsed ? '&#10095;' : '&#10094;';
        sidebarToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        sidebarToggle.setAttribute('aria-label', sidebarToggle.title);
        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
    });

    sidebar.querySelectorAll('[data-dropdown-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const menu = document.getElementById(toggle.getAttribute('aria-controls'));

            if (!menu) return;

            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                sidebarToggle?.setAttribute('aria-expanded', 'true');
                if (sidebarToggle) {
                    sidebarToggle.innerHTML = '&#10094;';
                    sidebarToggle.title = 'Collapse sidebar';
                    sidebarToggle.setAttribute('aria-label', sidebarToggle.title);
                }
            }

            const open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    });
}
