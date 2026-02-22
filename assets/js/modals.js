// Confirmation modal handler
// Intercepts forms with data-confirm="modal-id" attribute
// Opens the referenced modal instead of native confirm dialog

import { Modal } from 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    // Handle forms with data-confirm attribute
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const modalId = form.dataset.confirm;
            const modalEl = document.getElementById(modalId);
            if (modalEl) {
                const modal = new Modal(modalEl);
                modal.show();
            }
        });
    });
});
