import { clone, h, icon, promptDialog, uid } from './util';

const DEVICE_ICONS = { desktop: 'fa-desktop', tablet: 'fa-tablet-screen-button', mobile: 'fa-mobile-screen-button' };
const PALETTE = ['#0b3b60', '#b99146', '#101820', '#475467', '#667085', '#e4e7ec', '#f7f8fa', '#ffffff', '#12b76a', '#f79009', '#f04438', '#2e90fa'];

/** Remember which groups are open per element type + tab. */
const openGroups = new Map();

export function conditionMet(control, settings) {
    for (const [field, allowed] of Object.entries(control.condition || {})) {
        let value = settings?.[field] ?? '';
        if (typeof value === 'boolean') value = value ? '1' : '';
        if (value && typeof value === 'object' && !Array.isArray(value)) value = value.desktop ?? '';
        if (!allowed.map(String).includes(String(value))) return false;
    }
    return true;
}

/**
 * Render the controls of one tab.
 *
 * ctx = {
 *   key: string (element type, used to remember open groups),
 *   settings: () => object,
 *   set: (name, value) => void,
 *   device: () => 'desktop'|'tablet'|'mobile',
 *   cycleDevice: () => void,
 *   openMedia: (callback) => void,
 * }
 */
export function renderControls(container, controls, tab, ctx) {
    container.replaceChildren();
    const tabControls = controls.filter((c) => (c.tab || 'content') === tab);
    if (!tabControls.length) {
        container.append(h('p.ffbe-empty-note', {}, 'No settings in this tab.'));
        return () => {};
    }

    const groups = [];
    let current = null;
    for (const control of tabControls) {
        if (control.type === 'heading' || !current) {
            current = { heading: control.type === 'heading' ? control : { label: tab === 'content' ? 'Content' : tab === 'style' ? 'Style' : 'Advanced', name: `_default_${tab}` }, controls: [] };
            groups.push(current);
            if (control.type === 'heading') continue;
        }
        current.controls.push(control);
    }

    const rows = [];
    groups.forEach((group, index) => {
        if (!group.controls.length) return;
        const memoryKey = `${ctx.key}:${tab}:${group.heading.label}`;
        const open = openGroups.has(memoryKey) ? openGroups.get(memoryKey) : index === 0;
        const body = h('div.ffbe-group__body', { hidden: !open });
        const toggle = h('button.ffbe-group__toggle', {
            type: 'button',
            'aria-expanded': String(open),
            onclick: () => {
                const isOpen = body.hidden;
                body.hidden = !isOpen;
                toggle.setAttribute('aria-expanded', String(isOpen));
                openGroups.set(memoryKey, isOpen);
            },
        }, h('span', {}, group.heading.label), icon('fa-chevron-down'));
        const groupNode = h('section.ffbe-group', {}, toggle, body);
        container.append(groupNode);

        group.controls.forEach((control) => {
            const row = controlRow(control, ctx);
            rows.push({ row, control, group: groupNode });
            body.append(row);
        });
    });

    const refreshVisibility = () => {
        const settings = ctx.settings();
        const visibleByGroup = new Map();
        rows.forEach(({ row, control, group }) => {
            const visible = conditionMet(control, settings);
            row.hidden = !visible;
            if (visible) visibleByGroup.set(group, true);
        });
        rows.forEach(({ group }) => { group.hidden = !visibleByGroup.get(group); });
    };
    refreshVisibility();

    return refreshVisibility;
}

function controlRow(control, ctx) {
    const responsive = !!control.responsive;
    const read = () => {
        const raw = ctx.settings()?.[control.name];
        if (!responsive) return raw;
        if (raw === undefined || raw === null || typeof raw !== 'object' || Array.isArray(raw)) return ctx.device() === 'desktop' ? raw : undefined;
        return raw[ctx.device()];
    };
    const inherited = () => {
        if (!responsive) return undefined;
        const raw = ctx.settings()?.[control.name];
        if (!raw || typeof raw !== 'object') return undefined;
        const device = ctx.device();
        if (device === 'mobile') return raw.tablet ?? raw.desktop;
        if (device === 'tablet') return raw.desktop;
        return undefined;
    };
    const write = (value) => {
        if (!responsive) {
            ctx.set(control.name, value === '' && control.type !== 'text' && control.type !== 'textarea' && control.type !== 'richtext' && control.type !== 'code' ? undefined : value);
            return;
        }
        const raw = ctx.settings()?.[control.name];
        const next = raw && typeof raw === 'object' && !Array.isArray(raw) ? { ...raw } : {};
        if (value === '' || value === null || value === undefined) delete next[ctx.device()];
        else next[ctx.device()] = value;
        ctx.set(control.name, Object.keys(next).length ? next : undefined);
    };

    const api = { control, read, write, inherited, ctx };
    const input = (EDITORS[control.type] || EDITORS.text)(api);
    const id = `ffbe-c-${uid()}`;
    if (input.querySelector) {
        const focusable = input.matches?.('input,select,textarea') ? input : input.querySelector('input:not([type=color]),select,textarea');
        if (focusable && !focusable.id) focusable.id = id;
    }

    const label = h('div.ffbe-control__label', {},
        h('label', { for: id }, control.label),
        responsive ? h('button.ffbe-device-btn', {
            type: 'button',
            title: `Editing ${ctx.device()} value. Click to switch device.`,
            'aria-label': `Responsive setting, editing ${ctx.device()}. Switch device`,
            onclick: () => ctx.cycleDevice(),
        }, icon(DEVICE_ICONS[ctx.device()])) : null,
    );

    const inline = ['switch'].includes(control.type);
    return h(`div.ffbe-control.ffbe-control--${control.type}${inline ? '.ffbe-control--inline' : ''}`, { dataset: { name: control.name } },
        label,
        h('div.ffbe-control__input', {}, input),
        control.help ? h('p.ffbe-control__help', {}, control.help) : null,
    );
}

// ---------------------------------------------------------------------------
// Editors per control type
// ---------------------------------------------------------------------------

const EDITORS = {
    text({ read, write, control }) {
        return h('input.ffbe-input', {
            type: 'text',
            value: read() ?? '',
            maxlength: control.max || 1000,
            oninput: (e) => write(e.target.value),
        });
    },

    url({ read, write }) {
        return h('div.ffbe-input-group', {},
            h('span.ffbe-input-group__addon', {}, icon('fa-link')),
            h('input.ffbe-input', {
                type: 'text',
                inputmode: 'url',
                value: read() ?? '',
                placeholder: 'https://… or /path or #anchor',
                oninput: (e) => write(e.target.value.trim()),
            }));
    },

    textarea({ read, write, control }) {
        return h('textarea.ffbe-input', { rows: 3, maxlength: control.max || 5000, value: read() ?? '', oninput: (e) => write(e.target.value) });
    },

    code({ read, write, control }) {
        const area = h('textarea.ffbe-input.ffbe-input--code', {
            rows: 9, spellcheck: 'false', maxlength: control.max || 50000, value: read() ?? '',
            oninput: (e) => write(e.target.value),
            onkeydown: (e) => {
                if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault();
                    area.setRangeText('  ', area.selectionStart, area.selectionEnd, 'end');
                    write(area.value);
                }
            },
        });
        return area;
    },

    number({ read, write, control, inherited }) {
        const inh = inherited();
        return h('input.ffbe-input.ffbe-input--number', {
            type: 'number',
            value: read() ?? '',
            min: control.min, max: control.max, step: control.step || 1,
            placeholder: inh !== undefined ? String(inh) : '',
            oninput: (e) => write(e.target.value === '' ? '' : Number(e.target.value)),
        });
    },

    slider({ read, write, control, inherited }) {
        const value = read();
        const inh = inherited();
        const number = h('input.ffbe-input.ffbe-input--number', {
            type: 'number', value: value ?? '', min: control.min, max: control.max, step: control.step || 1,
            placeholder: inh !== undefined ? String(inh) : '',
            oninput: (e) => {
                range.value = e.target.value;
                write(e.target.value === '' ? '' : Number(e.target.value));
            },
        });
        const range = h('input.ffbe-range', {
            type: 'range', min: control.min ?? 0, max: control.max ?? 100, step: control.step || 1, value: value ?? inh ?? control.min ?? 0,
            'aria-label': control.label,
            oninput: (e) => {
                number.value = e.target.value;
                write(Number(e.target.value));
            },
        });
        return h('div.ffbe-slider', {}, range, number);
    },

    select({ read, write, control, inherited }) {
        const value = read() ?? '';
        const inh = inherited();
        const select = h('select.ffbe-input', { onchange: (e) => write(e.target.value) },
            control.options.map(([v, label]) => h('option', { value: v, selected: String(value) === v }, v === '' && inh ? `${label} (inherits ${inh})` : label)));
        return select;
    },

    choose({ read, write, control }) {
        const group = h('div.ffbe-choose', { role: 'radiogroup', 'aria-label': control.label });
        const paint = () => {
            const value = read() ?? '';
            group.querySelectorAll('button').forEach((b) => {
                const active = b.dataset.value === String(value);
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-checked', String(active));
            });
        };
        control.options.forEach(([v, label]) => {
            const iconName = control.icons?.[v];
            group.append(h('button.ffbe-choose__btn', {
                type: 'button', role: 'radio', title: label, 'aria-label': label, dataset: { value: v },
                onclick: () => {
                    const current = String(read() ?? '');
                    write(current === v && v !== '' ? '' : v);
                    paint();
                },
            }, iconName ? icon(iconName) : label));
        });
        paint();
        return group;
    },

    switch({ read, write, control }) {
        const value = read();
        const checked = value === undefined ? !!control.default : !!value;
        return h('label.ffbe-switch', {},
            h('input', { type: 'checkbox', checked, onchange: (e) => write(e.target.checked) }),
            h('span.ffbe-switch__track', { 'aria-hidden': 'true' }, h('span.ffbe-switch__on', {}, 'Yes'), h('span.ffbe-switch__off', {}, 'No')));
    },

    multiselect({ read, write, control }) {
        const current = () => new Set(read() ?? []);
        return h('div.ffbe-checklist', {}, control.options.map(([v, label]) => h('label.ffbe-check', {},
            h('input', {
                type: 'checkbox', value: v, checked: current().has(v),
                onchange: (e) => {
                    const set = current();
                    if (e.target.checked) set.add(v); else set.delete(v);
                    write(control.options.map(([o]) => o).filter((o) => set.has(o)));
                },
            }), label)));
    },

    color({ read, write, inherited }) {
        const value = read() ?? '';
        const inh = inherited();
        const swatch = h('input.ffbe-color__swatch', {
            type: 'color',
            value: /^#[0-9a-f]{6}$/i.test(value) ? value : /^#[0-9a-f]{6}$/i.test(inh || '') ? inh : '#000000',
            'aria-label': 'Pick color',
            oninput: (e) => { text.value = e.target.value; write(e.target.value); paint(); },
        });
        const text = h('input.ffbe-input.ffbe-color__text', {
            type: 'text', value, placeholder: inh || 'e.g. #0b3b60 or rgba(0,0,0,.5)', spellcheck: 'false',
            oninput: (e) => { write(e.target.value.trim()); paint(); },
        });
        const preview = h('span.ffbe-color__preview', { 'aria-hidden': 'true' });
        const paint = () => {
            preview.style.background = text.value || inh || 'transparent';
            preview.classList.toggle('is-empty', !text.value && !inh);
        };
        paint();
        const clear = h('button.ffbe-icon-btn.ffbe-icon-btn--sm', { type: 'button', title: 'Clear', 'aria-label': 'Clear color', onclick: () => { text.value = ''; write(''); paint(); } }, icon('fa-xmark'));
        const palette = h('div.ffbe-palette', {}, PALETTE.map((c) => h('button.ffbe-palette__chip', {
            type: 'button', title: c, 'aria-label': `Use ${c}`, style: { background: c },
            onclick: () => { text.value = c; swatch.value = c; write(c); paint(); },
        })));
        return h('div.ffbe-color', {}, h('div.ffbe-color__row', {}, h('label.ffbe-color__picker', {}, preview, swatch), text, clear), palette);
    },

    image({ read, write, ctx }) {
        const wrap = h('div.ffbe-image');
        const paint = () => {
            const value = read() ?? '';
            wrap.replaceChildren(
                h('button.ffbe-image__preview', {
                    type: 'button',
                    'aria-label': value ? 'Change image' : 'Choose image',
                    style: value ? { backgroundImage: `url("${value.replace(/"/g, '')}")` } : {},
                    onclick: () => ctx.openMedia((url) => { write(url); paint(); }),
                }, value ? null : h('span', {}, icon('fa-image'), ' Choose image')),
                h('div.ffbe-image__actions', {},
                    h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => ctx.openMedia((url) => { write(url); paint(); }) }, icon('fa-photo-film'), ' Media library'),
                    value ? h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => { write(''); paint(); } }, icon('fa-trash'), ' Remove') : null),
                h('input.ffbe-input', { type: 'text', placeholder: 'or paste an image URL', value, 'aria-label': 'Image URL', onchange: (e) => { write(e.target.value.trim()); paint(); } }),
            );
        };
        paint();
        return wrap;
    },

    dimensions({ read, write, control, inherited }) {
        const sides = ['top', 'right', 'bottom', 'left'];
        const value = read() || {};
        const inh = inherited() || {};
        let linked = sides.every((s) => value[s] === value.top) && value.top !== undefined;
        const inputs = {};
        const update = (side, raw) => {
            const next = { ...(read() || {}) };
            const targets = linked ? sides : [side];
            targets.forEach((s) => {
                if (raw === '') delete next[s]; else next[s] = Number(raw);
                if (s !== side) inputs[s].value = raw;
            });
            write(Object.keys(next).length ? next : '');
        };
        const linkBtn = h('button.ffbe-icon-btn.ffbe-dim__link', {
            type: 'button', title: 'Link values together', 'aria-pressed': String(linked),
            onclick: () => {
                linked = !linked;
                linkBtn.setAttribute('aria-pressed', String(linked));
                linkBtn.classList.toggle('is-active', linked);
            },
        }, icon('fa-link'));
        linkBtn.classList.toggle('is-active', linked);
        return h('div.ffbe-dim', {},
            sides.map((side) => h('label.ffbe-dim__cell', {},
                inputs[side] = h('input.ffbe-input.ffbe-input--number', {
                    type: 'number', min: control.min, max: control.max, value: value[side] ?? '', placeholder: inh[side] ?? '',
                    'aria-label': `${control.label} ${side}`,
                    oninput: (e) => update(side, e.target.value),
                }),
                h('span', {}, side[0].toUpperCase() + side.slice(1)))),
            linkBtn);
    },

    richtext({ read, write }) {
        return richEditor(read() ?? '', write);
    },

    repeater({ read, write, control, ctx }) {
        const wrap = h('div.ffbe-repeater');
        const open = new Set();
        const items = () => clone(read() || []);
        const paint = () => {
            const list = items();
            wrap.replaceChildren();
            list.forEach((item, index) => {
                const isOpen = open.has(item._id);
                const title = item[control.title_field] ? String(item[control.title_field]).slice(0, 60) : `Item #${index + 1}`;
                const body = h('div.ffbe-repeater__body', { hidden: !isOpen });
                const itemCtx = {
                    ...ctx,
                    key: `${ctx.key}:${control.name}`,
                    settings: () => (read() || [])[index] || {},
                    set: (name, value) => {
                        const next = items();
                        if (!next[index]) return;
                        if (value === undefined || value === '') delete next[index][name]; else next[index][name] = value;
                        write(next);
                        if (name === control.title_field) head.querySelector('.ffbe-repeater__title').textContent = value || `Item #${index + 1}`;
                    },
                };
                control.fields.forEach((field) => body.append(controlRow(field, itemCtx)));
                const move = (delta) => {
                    const next = items();
                    const to = index + delta;
                    if (to < 0 || to >= next.length) return;
                    [next[index], next[to]] = [next[to], next[index]];
                    write(next);
                    paint();
                };
                const head = h('div.ffbe-repeater__head', {},
                    h('button.ffbe-repeater__title', {
                        type: 'button', 'aria-expanded': String(isOpen),
                        onclick: () => { if (open.has(item._id)) open.delete(item._id); else open.add(item._id); paint(); },
                    }, title),
                    h('div.ffbe-repeater__tools', {},
                        h('button.ffbe-icon-btn.ffbe-icon-btn--sm', { type: 'button', title: 'Move up', 'aria-label': 'Move item up', disabled: index === 0, onclick: () => move(-1) }, icon('fa-arrow-up')),
                        h('button.ffbe-icon-btn.ffbe-icon-btn--sm', { type: 'button', title: 'Move down', 'aria-label': 'Move item down', disabled: index === list.length - 1, onclick: () => move(1) }, icon('fa-arrow-down')),
                        h('button.ffbe-icon-btn.ffbe-icon-btn--sm', {
                            type: 'button', title: 'Duplicate', 'aria-label': 'Duplicate item',
                            onclick: () => { const next = items(); next.splice(index + 1, 0, { ...clone(next[index]), _id: uid() }); write(next); paint(); },
                        }, icon('fa-copy')),
                        h('button.ffbe-icon-btn.ffbe-icon-btn--sm.ffbe-icon-btn--danger', {
                            type: 'button', title: 'Remove', 'aria-label': 'Remove item',
                            onclick: () => { const next = items(); next.splice(index, 1); write(next); paint(); },
                        }, icon('fa-trash'))));
                wrap.append(h('div.ffbe-repeater__item', {}, head, body));
            });
            wrap.append(h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--block', {
                type: 'button',
                onclick: () => {
                    const next = items();
                    const item = { _id: uid() };
                    control.fields.forEach((f) => { if (f.default !== undefined) item[f.name] = clone(f.default); });
                    next.push(item);
                    open.add(item._id);
                    write(next);
                    paint();
                },
            }, icon('fa-plus'), ' Add item'));
        };
        paint();
        return wrap;
    },
};

// ---------------------------------------------------------------------------
// Rich text editor (contenteditable + HTML source view)
// ---------------------------------------------------------------------------

export function richEditor(initial, onChange) {
    const area = h('div.ffbe-rich__area', { contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Content', html: initial });
    const source = h('textarea.ffbe-input.ffbe-input--code.ffbe-rich__source', { rows: 10, hidden: true, spellcheck: 'false', 'aria-label': 'HTML source' });
    let savedRange = null;

    const emit = () => onChange(area.innerHTML);
    const saveRange = () => {
        const selection = document.getSelection();
        if (selection.rangeCount && area.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
    };
    const restoreRange = () => {
        area.focus();
        if (savedRange) {
            const selection = document.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
        }
    };
    const exec = (command, value = null) => {
        restoreRange();
        document.execCommand(command, false, value);
        emit();
        saveRange();
    };

    area.addEventListener('input', emit);
    ['keyup', 'mouseup', 'blur'].forEach((ev) => area.addEventListener(ev, saveRange));
    area.addEventListener('paste', (event) => {
        // Paste as plain text paragraphs to avoid foreign markup and styles.
        const text = event.clipboardData?.getData('text/plain');
        if (text === undefined) return;
        event.preventDefault();
        const html = text.split(/\r?\n\r?\n/).map((p) => `<p>${p.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])).replace(/\r?\n/g, '<br>')}</p>`).join('');
        document.execCommand('insertHTML', false, html);
    });

    const button = (name, title, onclick) => h('button.ffbe-rich__btn', {
        type: 'button', title, 'aria-label': title,
        onmousedown: (e) => e.preventDefault(),
        onclick,
    }, icon(name));

    const formatSelect = h('select.ffbe-rich__format', {
        'aria-label': 'Paragraph format',
        onchange: (e) => { exec('formatBlock', e.target.value); e.target.value = ''; },
    }, h('option', { value: '' }, 'Format'), ['p', 'h2', 'h3', 'h4', 'blockquote', 'pre'].map((t) => h('option', { value: t }, { p: 'Paragraph', h2: 'Heading 2', h3: 'Heading 3', h4: 'Heading 4', blockquote: 'Quote', pre: 'Preformatted' }[t])));

    const toolbar = h('div.ffbe-rich__toolbar', { role: 'toolbar', 'aria-label': 'Text formatting' },
        formatSelect,
        button('fa-bold', 'Bold (Ctrl+B)', () => exec('bold')),
        button('fa-italic', 'Italic (Ctrl+I)', () => exec('italic')),
        button('fa-underline', 'Underline (Ctrl+U)', () => exec('underline')),
        button('fa-strikethrough', 'Strikethrough', () => exec('strikeThrough')),
        button('fa-list-ul', 'Bulleted list', () => exec('insertUnorderedList')),
        button('fa-list-ol', 'Numbered list', () => exec('insertOrderedList')),
        button('fa-link', 'Insert link', async () => {
            saveRange();
            const url = await promptDialog('Link URL', { title: 'Insert link', placeholder: 'https://example.com', confirmLabel: 'Insert link' });
            if (url) exec('createLink', url);
        }),
        button('fa-link-slash', 'Remove link', () => exec('unlink')),
        button('fa-superscript', 'Superscript', () => exec('superscript')),
        button('fa-minus', 'Horizontal line', () => exec('insertHorizontalRule')),
        button('fa-eraser', 'Clear formatting', () => exec('removeFormat')),
        button('fa-code', 'Edit HTML', () => {
            const showSource = source.hidden;
            if (showSource) source.value = area.innerHTML;
            else { area.innerHTML = source.value; emit(); }
            source.hidden = !showSource;
            area.hidden = showSource;
        }),
    );
    source.addEventListener('input', () => onChange(source.value));

    const wrap = h('div.ffbe-rich', {}, toolbar, area, source);
    wrap.setValue = (html) => {
        if (document.activeElement !== area) area.innerHTML = html;
    };
    return wrap;
}
