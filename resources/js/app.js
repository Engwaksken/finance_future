import './bootstrap';
import './password-toggle';
import './page-slides';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const failedModal = document.querySelector('[data-reopen-modal]');
if (failedModal) {
    const modal = document.getElementById(failedModal.dataset.reopenModal);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
