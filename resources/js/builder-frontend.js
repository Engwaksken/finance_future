/**
 * Runtime behaviour for content built with the visual article builder:
 * tabs, animated counters, entrance animations and an image lightbox.
 * Uses event delegation so it also works in the editor canvas, whose
 * content is replaced on every change.
 */
const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function selectTab(tab, focus = false) {
    const tabs = tab.closest('[data-ffb-tabs]');
    if (!tabs) return;
    tabs.querySelectorAll(':scope > .ffb-tabs__list > [role=tab]').forEach((other) => {
        const active = other === tab;
        other.setAttribute('aria-selected', String(active));
        other.tabIndex = active ? 0 : -1;
        const panel = tabs.querySelector('#' + CSS.escape(other.getAttribute('aria-controls')));
        if (panel) panel.hidden = !active;
    });
    if (focus) tab.focus();
}

document.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-ffb-tabs] [role=tab]');
    if (tab) selectTab(tab);
});

document.addEventListener('keydown', (event) => {
    const tab = event.target.closest?.('[data-ffb-tabs] [role=tab]');
    if (!tab || !['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    const list = [...tab.parentElement.querySelectorAll('[role=tab]')];
    let index = list.indexOf(tab);
    if (event.key === 'Home') index = 0;
    else if (event.key === 'End') index = list.length - 1;
    else index = (index + (['ArrowRight', 'ArrowDown'].includes(event.key) ? 1 : -1) + list.length) % list.length;
    event.preventDefault();
    selectTab(list[index], true);
});

function formatNumber(value, decimals, separator) {
    const fixed = Number(value).toFixed(decimals);
    if (!separator) return fixed;
    const [int, frac] = fixed.split('.');
    return int.replace(/\B(?=(\d{3})+(?!\d))/g, separator) + (frac ? '.' + frac : '');
}

function runCounter(el) {
    const from = parseFloat(el.dataset.from) || 0;
    const to = parseFloat(el.dataset.to) || 0;
    const decimals = parseInt(el.dataset.decimals, 10) || 0;
    const duration = parseInt(el.dataset.duration, 10) || 0;
    const separator = el.dataset.separator || '';
    if (reduceMotion || duration <= 0) {
        el.textContent = formatNumber(to, decimals, separator);
        return;
    }
    const start = performance.now();
    const tick = (now) => {
        const progress = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = formatNumber(from + (to - from) * eased, decimals, separator);
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

function initVisibility(root = document) {
    const targets = root.querySelectorAll('.ffb-anim:not(.is-visible), [data-ffb-counter]:not([data-ffb-counted])');
    if (!targets.length) return;
    if (!('IntersectionObserver' in window)) {
        targets.forEach((el) => el.classList.add('is-visible'));
        return;
    }
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            observer.unobserve(el);
            if (el.matches('[data-ffb-counter]')) {
                el.dataset.ffbCounted = '1';
                runCounter(el);
            } else {
                el.classList.add('is-visible');
            }
        });
    }, { threshold: 0.15 });
    targets.forEach((el) => observer.observe(el));
}

// Lightbox -------------------------------------------------------------
let lightbox;
let group = [];
let current = 0;

function showLightbox(index) {
    current = (index + group.length) % group.length;
    const link = group[current];
    lightbox.querySelector('img').src = link.getAttribute('href');
    lightbox.querySelector('img').alt = link.querySelector('img')?.alt || '';
    lightbox.querySelector('figcaption').textContent = link.dataset.caption || '';
    lightbox.querySelectorAll('.ffb-lightbox__prev, .ffb-lightbox__next').forEach((b) => { b.hidden = group.length < 2; });
}

function closeLightbox() {
    if (!lightbox || lightbox.hidden) return;
    lightbox.hidden = true;
    document.body.style.overflow = '';
    group[current]?.focus();
}

function openLightbox(link) {
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.className = 'ffb-lightbox';
        lightbox.hidden = true;
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Image viewer');
        lightbox.innerHTML = '<figure><img alt=""><figcaption></figcaption></figure>'
            + '<button type="button" class="ffb-lightbox__close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>'
            + '<button type="button" class="ffb-lightbox__prev" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>'
            + '<button type="button" class="ffb-lightbox__next" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>';
        document.body.appendChild(lightbox);
        lightbox.addEventListener('click', (event) => {
            if (event.target.closest('.ffb-lightbox__prev')) showLightbox(current - 1);
            else if (event.target.closest('.ffb-lightbox__next')) showLightbox(current + 1);
            else if (event.target.closest('.ffb-lightbox__close') || event.target === lightbox) closeLightbox();
        });
        document.addEventListener('keydown', (event) => {
            if (lightbox.hidden) return;
            if (event.key === 'Escape') closeLightbox();
            if (event.key === 'ArrowLeft') showLightbox(current - 1);
            if (event.key === 'ArrowRight') showLightbox(current + 1);
        });
    }
    group = [...document.querySelectorAll(`[data-ffb-lightbox="${CSS.escape(link.dataset.ffbLightbox)}"]`)];
    showLightbox(group.indexOf(link));
    lightbox.hidden = false;
    document.body.style.overflow = 'hidden';
    lightbox.querySelector('.ffb-lightbox__close').focus();
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-ffb-lightbox]');
    if (!link || link.closest('.ffb-editor')) return;
    event.preventDefault();
    openLightbox(link);
});

if (document.querySelector('.ffb') && !document.querySelector('.ffb-editor')) {
    document.documentElement.classList.add('ffb-js');
    initVisibility();
}

window.ffbInitVisibility = initVisibility;
