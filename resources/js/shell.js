import Choices from 'choices.js';
import 'choices.js/public/assets/styles/choices.min.css';
import Swal from 'sweetalert2';
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

const employeeModal = document.querySelector('#employee-modal');
const roleSelect = employeeModal?.querySelector('[data-role-multiselect]');
let roleChoices;

if (employeeModal) {
    if (roleSelect) {
        roleChoices = new Choices(roleSelect, {
            removeItemButton: true,
            shouldSort: false,
            searchEnabled: true,
            itemSelectText: '',
            placeholder: true,
            placeholderValue: 'Choose employee roles',
        });

        roleSelect.addEventListener('change', () => {
            const componentId = employeeModal.closest('[wire\\:id]')?.getAttribute('wire:id');
            const selectedRoleIds = roleChoices.getValue(true);

            if (componentId) {
                window.Livewire.find(componentId).set(
                    'selectedRoleIds',
                    Array.isArray(selectedRoleIds) ? selectedRoleIds : [selectedRoleIds],
                );
            }
        });
    }

    const closeEmployeeModal = () => {
        employeeModal.close();
        const componentId = employeeModal.closest('[wire\\:id]')?.getAttribute('wire:id');
        if (componentId) window.Livewire.find(componentId).call('resetForm');
    };

    employeeModal.querySelectorAll('[data-close-employee-modal]').forEach((button) => {
        button.addEventListener('click', closeEmployeeModal);
    });

    employeeModal.addEventListener('click', (event) => {
        if (event.target === employeeModal) closeEmployeeModal();
    });

    employeeModal.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeEmployeeModal();
    });


    employeeModal.addEventListener('close', () => {
        if (roleChoices) roleChoices.removeActiveItems();
    });

    const registerEmployeeEvents = () => {
        window.Livewire.on('employee-modal-open', (event) => {
            if (roleChoices) {
                roleChoices.removeActiveItems();
                roleChoices.setChoiceByValue((event.roleIds || []).map(String));
            }

            if (!employeeModal.open) employeeModal.showModal();
        });

        window.Livewire.on('employee-saved', (event) => {
            if (employeeModal.open) employeeModal.close();

            Swal.fire({
                icon: 'success',
                title: event.message,
                timer: 1800,
                showConfirmButton: false,
            });
        });

        window.Livewire.on('employee-deleted', () => {
            Swal.fire({
                icon: 'success',
                title: 'Employee deleted.',
                timer: 1800,
                showConfirmButton: false,
            });
        });
    };

    if (window.Livewire) {
        registerEmployeeEvents();
    } else {
        document.addEventListener('livewire:init', registerEmployeeEvents, { once: true });
    }
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-delete-employee]');
    if (!button) return;

    const result = await Swal.fire({
        icon: 'warning',
        title: 'Delete employee?',
        text: `${button.dataset.employeeName} will be soft deleted.`,
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#b91c1c',
    });

    if (!result.isConfirmed) return;

    const componentId = button.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (componentId) window.Livewire.find(componentId).call('delete', button.dataset.deleteEmployee);
});
const roleSetup = document.querySelector('[data-role-setup]');

if (roleSetup) {
    const toast = (message, type = 'success') => {
        let element = document.querySelector('[data-role-toast]');
        if (!element) {
            element = document.createElement('div');
            element.className = 'role-toast';
            element.setAttribute('role', 'status');
            element.dataset.roleToast = '';
            document.body.append(element);
        }
        element.dataset.type = type;
        element.textContent = message;
        element.classList.add('visible');
        clearTimeout(element.toastTimer);
        element.toastTimer = setTimeout(() => element.classList.remove('visible'), 2800);
    };
    const registerRoleEvents = () => {
        if (!window.Livewire) return;
        window.Livewire.on('role-saved', (event) => toast(event.message));
        window.Livewire.on('role-assignment-saved', (event) => toast(event.message));
        window.Livewire.on('role-error', (event) => toast(event.message || 'The change could not be saved.', 'error'));
    };

    if (window.Livewire) registerRoleEvents();
    else document.addEventListener('livewire:init', registerRoleEvents, { once: true });

    roleSetup.addEventListener('input', (event) => {
        if (!event.target.matches('[data-module-search]')) return;
        const query = event.target.value.toLowerCase().trim();
        const rows = roleSetup.querySelectorAll('[data-module-row]');
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = !row.dataset.moduleRow.toLowerCase().includes(query);
            if (!row.hidden) visible++;
        });
        const empty = roleSetup.querySelector('[data-empty-filter]');
        if (empty) empty.hidden = visible > 0;
    });
    roleSetup.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            roleSetup.querySelector('[data-module-search]')?.focus();
        }
    });
}
