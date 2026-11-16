export const uid = () => {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let id = chars[Math.floor(Math.random() * 26)];
    const bytes = crypto.getRandomValues(new Uint8Array(7));
    bytes.forEach((b) => { id += chars[b % chars.length]; });
    return id;
};

export const clone = (value) => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)));

export const debounce = (fn, wait) => {
    let timer;
    const debounced = (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
    debounced.flush = (...args) => { clearTimeout(timer); fn(...args); };
    debounced.cancel = () => clearTimeout(timer);
    return debounced;
};

/**
 * Tiny element factory: h('button.btn.primary', { type: 'button', onclick }, 'Save')
 */
export function h(tag, attrs = {}, ...children) {
    const [name, ...classes] = tag.split('.');
    const node = document.createElement(name || 'div');
    if (classes.length) node.className = classes.join(' ');
    let initialValue;
    for (const [key, value] of Object.entries(attrs || {})) {
        if (value === undefined || value === null || value === false) continue;
        if (key === 'value') { initialValue = value; continue; }
        if (key.startsWith('on') && typeof value === 'function') node.addEventListener(key.slice(2), value);
        else if (key === 'class') node.className += (node.className ? ' ' : '') + value;
        else if (key === 'dataset') Object.assign(node.dataset, value);
        else if (key === 'style' && typeof value === 'object') Object.assign(node.style, value);
        else if (key === 'html') node.innerHTML = value;
        else if (key in node && typeof value !== 'string') node[key] = value;
        else node.setAttribute(key, value === true ? '' : value);
    }
    append(node, children);
    if (initialValue !== undefined) node.value = initialValue;
    return node;
}

function append(node, children) {
    for (const child of children.flat(Infinity)) {
        if (child === null || child === undefined || child === false) continue;
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }
}

export const icon = (name) => h('i', { class: name.includes(' ') ? name : `fa-solid ${name}`, 'aria-hidden': 'true' });

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Toasts -----------------------------------------------------------------
let toastHost;
export function toast(message, type = 'info', timeout = 3200) {
    toastHost ??= document.body.appendChild(h('div.ffbe-toasts', { role: 'status', 'aria-live': 'polite' }));
    const node = h(`div.ffbe-toast.ffbe-toast--${type}`, {},
        icon(type === 'error' ? 'fa-circle-exclamation' : type === 'success' ? 'fa-circle-check' : 'fa-circle-info'),
        h('span', {}, message));
    toastHost.append(node);
    setTimeout(() => {
        node.classList.add('is-leaving');
        setTimeout(() => node.remove(), 300);
    }, timeout);
}

// Modals -----------------------------------------------------------------
export function modal({ title, body, footer = [], size = 'md', onClose }) {
    const previous = document.activeElement;
    const close = () => {
        backdrop.remove();
        document.removeEventListener('keydown', onKey, true);
        onClose?.();
        previous?.focus?.();
    };
    const onKey = (event) => {
        if (event.key === 'Escape') {
            event.stopPropagation();
            close();
        }
    };
    const dialog = h(`div.ffbe-modal.ffbe-modal--${size}`, { role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
        h('div.ffbe-modal__head', {}, h('h2', {}, title), h('button.ffbe-icon-btn', { type: 'button', 'aria-label': 'Close', onclick: close }, icon('fa-xmark'))),
        h('div.ffbe-modal__body', {}, body),
        footer.length ? h('div.ffbe-modal__foot', {}, footer) : null,
    );
    const backdrop = h('div.ffbe-modal-backdrop', { onmousedown: (event) => { if (event.target === backdrop) close(); } }, dialog);
    document.body.append(backdrop);
    document.addEventListener('keydown', onKey, true);
    requestAnimationFrame(() => (dialog.querySelector('[autofocus], input, select, textarea, button:not(.ffbe-icon-btn)') || dialog).focus?.());
    return { close, dialog, body: dialog.querySelector('.ffbe-modal__body') };
}

export function confirmDialog(message, { title = 'Are you sure?', confirmLabel = 'Confirm', danger = false } = {}) {
    return new Promise((resolve) => {
        let answered = false;
        const finish = (value) => {
            if (answered) return;
            answered = true;
            m.close();
            resolve(value);
        };
        const m = modal({
            title,
            size: 'sm',
            body: h('p', {}, message),
            footer: [
                h('button.ffbe-btn.ffbe-btn--light', { type: 'button', onclick: () => finish(false) }, 'Cancel'),
                h(`button.ffbe-btn.${danger ? 'ffbe-btn--danger' : 'ffbe-btn--primary'}`, { type: 'button', autofocus: true, onclick: () => finish(true) }, confirmLabel),
            ],
            onClose: () => finish(false),
        });
    });
}

export function promptDialog(label, { title = label, value = '', confirmLabel = 'Save', placeholder = '' } = {}) {
    return new Promise((resolve) => {
        let answered = false;
        const input = h('input.ffbe-input', { type: 'text', value, placeholder, autofocus: true, maxlength: 120 });
        const finish = (result) => {
            if (answered) return;
            answered = true;
            m.close();
            resolve(result);
        };
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                finish(input.value.trim() || null);
            }
        });
        const m = modal({
            title,
            size: 'sm',
            body: h('label.ffbe-field', {}, h('span.ffbe-label', {}, label), input),
            footer: [
                h('button.ffbe-btn.ffbe-btn--light', { type: 'button', onclick: () => finish(null) }, 'Cancel'),
                h('button.ffbe-btn.ffbe-btn--primary', { type: 'button', onclick: () => finish(input.value.trim() || null) }, confirmLabel),
            ],
            onClose: () => finish(null),
        });
    });
}

// HTTP -------------------------------------------------------------------
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function request(url, { method = 'GET', body, signal } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
    let payload = body;
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }
    const response = await fetch(url, { method, headers, body: payload, signal, credentials: 'same-origin' });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(data.message || `Request failed (${response.status})`);
        error.status = response.status;
        error.errors = data.errors || {};
        throw error;
    }
    return data;
}

export const storage = {
    get(key) {
        try { return JSON.parse(localStorage.getItem(key)); } catch { return null; }
    },
    set(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* storage unavailable */ }
    },
    remove(key) {
        try { localStorage.removeItem(key); } catch { /* storage unavailable */ }
    },
};
