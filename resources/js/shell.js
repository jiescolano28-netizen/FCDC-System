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
