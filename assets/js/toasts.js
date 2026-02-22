// ToastManager using Bootstrap 5 Toast component
// Features:
// - Shows toast notifications (success, danger, warning, info types)
// - Auto-dismiss after 5 seconds
// - Fixed position top-right, below topbar
// - On page load, converts any elements with data-flash-type and data-flash-message into toasts
// - Listens for 'mercure:update' CustomEvent and shows appropriate toast
// - Queue system: max 5 visible toasts, older ones get dismissed

import { Toast } from 'bootstrap';

class ToastManager {
    constructor() {
        this.container = document.getElementById('toast-container');
        this.maxVisible = 5;
        this.init();
    }

    init() {
        // Convert flash messages to toasts on load
        document.querySelectorAll('[data-flash-type]').forEach(el => {
            this.show(el.dataset.flashMessage, el.dataset.flashType);
            el.remove();
        });

        // Listen for Mercure updates
        document.addEventListener('mercure:update', (e) => {
            const { event, id } = e.detail;
            const label = event.replace('GiftCard', '');
            this.show(`Gift Card ${id.substring(0, 8)}... - ${label}`, 'info');
        });
    }

    show(message, type = 'info') {
        // type: success, danger, warning, info
        const icons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
        const colors = { success: '#10b981', danger: '#ef4444', warning: '#f59e0b', info: '#23c1cd' };

        const toastEl = document.createElement('div');
        toastEl.className = 'toast show toast-notification';
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi ${icons[type] || icons.info}" style="color:${colors[type] || colors.info};font-size:1.1rem;"></i>
                <span class="flex-grow-1">${message}</span>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast"></button>
            </div>
        `;

        this.container.appendChild(toastEl);
        const bsToast = new Toast(toastEl, { delay: 5000 });
        bsToast.show();

        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        this.trimToasts();
    }

    trimToasts() {
        const toasts = this.container.querySelectorAll('.toast');
        if (toasts.length > this.maxVisible) {
            for (let i = 0; i < toasts.length - this.maxVisible; i++) {
                Toast.getInstance(toasts[i])?.hide();
            }
        }
    }
}

new ToastManager();
