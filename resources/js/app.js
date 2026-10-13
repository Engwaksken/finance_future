import './bootstrap';
import './password-toggle';
import './page-slides';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const mainNav = document.querySelector('.nav');
const mainNavToggle = document.getElementById('mainNavToggle');
const mainNavLabel = mainNavToggle?.querySelector('.nav-toggle-label');

if (mainNav && mainNavToggle) {
    const closeMainNav = () => {
        mainNav.classList.remove('menu-open');
        mainNavToggle.setAttribute('aria-expanded', 'false');
        if (mainNavLabel) mainNavLabel.textContent = 'Menu';
    };

    mainNavToggle.addEventListener('click', () => {
        const isOpen = mainNav.classList.toggle('menu-open');
        mainNavToggle.setAttribute('aria-expanded', String(isOpen));
        if (mainNavLabel) mainNavLabel.textContent = isOpen ? 'Close menu' : 'Menu';
    });

    mainNav.querySelectorAll('.nav-links a').forEach((link) => {
        link.addEventListener('click', closeMainNav);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMainNav();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 700) closeMainNav();
    });
}

const failedModal = document.querySelector('[data-reopen-modal]');
if (failedModal) {
    const modal = document.getElementById(failedModal.dataset.reopenModal);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}
