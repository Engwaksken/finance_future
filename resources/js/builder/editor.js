import { Store } from './store';
import { renderControls } from './controls';
import { BLOCKS, STRUCTURES } from './blocks';
import { clone, confirmDialog, debounce, h, icon, modal, promptDialog, request, storage, toast } from './util';

const config = JSON.parse(document.getElementById('ffb-config').textContent);
const { schema, urls } = config;
const store = new Store(schema, config.layout);

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const ui = {
    app: $('#ffbe'),
    panelTitle: $('#ffbe-panel-title'),
    panelBody: $('#ffbe-panel-body'),
    metaForm: $('#ffbe-meta'),
    frame: $('#ffbe-frame'),
    frameWrap: $('#ffbe-frame-wrap'),
    navigator: $('#ffbe-navigator'),
    navigatorTree: $('#ffbe-navigator-tree'),
    saveState: $('#ffbe-save-state'),
    publishBtn: $('#ffbe-publish'),
    topTitle: $('#ffbe-top-title'),
    topStatus: $('#ffbe-top-status'),
    undoBtn: $('[data-action="undo"]'),
    redoBtn: $('[data-action="redo"]'),
    previewForm: $('#ffbe-preview-form'),
};

const state = {
    article: config.article,
    selected: null,
    device: 'desktop',
    view: 'widgets',
    tab: 'content',
    dirty: false,
    saving: false,
    inline: null,
    pendingRender: false,
    scrollTo: null,
    drag: null,
    clipboard: storage.get('ffb-clipboard'),
};

let doc = null; // canvas document
let root = null; // canvas content root
let refreshVisibility = () => {};

const INLINE = {
    heading: { field: 'text', selector: '.ffb-heading' },
    text: { field: 'content', selector: '.ffb-text', rich: true },
    button: { field: 'text', selector: '.ffb-btn > span', single: true },
    quote: { field: 'text', selector: '.ffb-quote > p' },
    icon_box: { field: 'title', selector: '.ffb-iconbox__title', single: true },
    image_box: { field: 'title', selector: '.ffb-imagebox__title', single: true },
    testimonial: { field: 'content', selector: '.ffb-testimonial__content' },
    cta: { field: 'title', selector: '.ffb-cta__title', single: true },
};

const DEVICE_WIDTHS = { desktop: '100%', tablet: '768px', mobile: '375px' };

// ===========================================================================
// Rendering
// ===========================================================================

let renderController = null;
let renderSeq = 0;

async function renderNow() {
    if (!doc) return;
    if (state.inline) {
        state.pendingRender = true;
        return;
    }
    renderController?.abort();
    renderController = new AbortController();
    const seq = ++renderSeq;
    ui.frameWrap.classList.add('is-rendering');
    try {
        const data = await request(urls.render, {
            method: 'POST',
            body: { layout: JSON.stringify(store.layout), title: metaValue('title') },
            signal: renderController.signal,
        });
        if (seq !== renderSeq) return;
        paintCanvas(data.html, data.css);
    } catch (error) {
        if (error.name === 'AbortError') return;
        toast(error.status === 419 ? 'Your session expired. Reload the page to continue.' : `Preview failed: ${error.message}`, 'error', 6000);
    } finally {
        if (seq === renderSeq) ui.frameWrap.classList.remove('is-rendering');
    }
}

const scheduleRender = debounce(renderNow, 140);

function paintCanvas(html, css) {
    if (state.inline) {
        state.pendingRender = true;
        return;
    }
    doc.getElementById('ffb-css').textContent = css;
    root.innerHTML = html;
    decorateCanvas();
    if (state.scrollTo) {
        canvasNode(state.scrollTo)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        state.scrollTo = null;
    }
}

function decorateCanvas() {
    $$('[data-ffb-el]', root).forEach((el) => {
        const kind = el.dataset.ffbEl;
        const id = el.dataset.ffbId;
        el.prepend(handleFor(kind, id, el));
    });
    markSelection();
}

function handleFor(kind, id, el) {
    const b = (action, iconName, title, extra = {}) => h(`button.ffbe-h-btn`, { type: 'button', title, 'aria-label': title, dataset: { action, id }, ...extra }, icon(iconName));
    let handle;
    if (kind === 'section') {
        handle = h('div.ffbe-handle.ffbe-handle--section', {},
            b('add-section-before', 'fa-plus', 'Add section above'),
            b('drag', 'fa-grip', 'Drag to move section', { draggable: 'true', dataset: { action: 'drag', id, kind: 'section' } }),
            b('edit', 'fa-table-cells-large', 'Edit section'),
            b('duplicate', 'fa-clone', 'Duplicate section'),
            b('delete', 'fa-xmark', 'Delete section'));
    } else if (kind === 'column') {
        handle = h('div.ffbe-handle.ffbe-handle--column', {},
            b('drag', 'fa-grip-vertical', 'Drag to reorder column', { draggable: 'true', dataset: { action: 'drag', id, kind: 'column' } }),
            b('edit', 'fa-table-columns', 'Edit column'),
            b('add-column', 'fa-plus', 'Add column after'),
            b('delete', 'fa-xmark', 'Delete column'));
    } else {
        const title = schema.widgets[el.dataset.ffbType]?.title ?? 'Widget';
        handle = h('div.ffbe-handle.ffbe-handle--widget', {},
            b('drag', 'fa-up-down-left-right', `Drag ${title}`, { draggable: 'true', dataset: { action: 'drag', id, kind: 'widget' } }),
            b('edit', 'fa-pencil', `Edit ${title}`),
            b('duplicate', 'fa-clone', `Duplicate ${title}`),
            b('delete', 'fa-trash', `Delete ${title}`));
    }
    handle.setAttribute('contenteditable', 'false');
    return handle;
}

function markSelection() {
    if (!root) return;
    $$('.ffbe-selected', root).forEach((el) => el.classList.remove('ffbe-selected'));
    if (state.selected) root.querySelector(`[data-ffb-id="${state.selected}"]`)?.classList.add('ffbe-selected');
}

function canvasNode(id) {
    return root?.querySelector(`[data-ffb-id="${id}"]`);
}

// ===========================================================================
// Selection & panel
// ===========================================================================

function select(id, { openPanel = true, scroll = false } = {}) {
    if (state.inline && state.inline.id !== id) endInline();
    state.selected = id && store.find(id) ? id : null;
    markSelection();
    renderNavigator();
    if (scroll && state.selected) {
        const node = canvasNode(state.selected);
        if (node) node.scrollIntoView({ behavior: 'smooth', block: 'center' });
        else state.scrollTo = state.selected; // not rendered yet: scroll after the next paint
    }
    if (openPanel && state.selected) showView('element');
    else if (!state.selected && state.view === 'element') showView('widgets');
}

function showView(view) {
    state.view = view;
    const isPage = view === 'page';
    ui.metaForm.hidden = !isPage;
    ui.panelBody.hidden = isPage;
    $$('[data-view-btn]').forEach((b) => b.classList.toggle('is-active', b.dataset.viewBtn === view));
    if (isPage) {
        ui.panelTitle.textContent = 'Article settings';
        return;
    }
    if (view === 'widgets') renderWidgetsView();
    else if (view === 'element') renderElementView();
    else if (view === 'history') renderHistoryView();
}

function renderWidgetsView() {
    ui.panelTitle.textContent = 'Elements';
    const search = h('input.ffbe-input.ffbe-search', { type: 'search', placeholder: 'Search widgets…', 'aria-label': 'Search widgets' });
    const lists = h('div');
    const paint = () => {
        const term = search.value.trim().toLowerCase();
        lists.replaceChildren();
        Object.entries(schema.categories).forEach(([category, label]) => {
            const items = Object.entries(schema.widgets).filter(([type, w]) => w.category === category && (!term || w.title.toLowerCase().includes(term) || type.includes(term)));
            if (!items.length) return;
            lists.append(h('h3.ffbe-cat-title', {}, label), h('div.ffbe-tiles', {}, items.map(([type, w]) => h('button.ffbe-tile', {
                type: 'button',
                draggable: 'true',
                title: `Drag into the page, or click to add ${w.title}`,
                ondragstart: (e) => {
                    state.drag = { kind: 'new-widget', type };
                    e.dataTransfer.effectAllowed = 'copy';
                    e.dataTransfer.setData('text/plain', `ffb:${type}`);
                },
                ondragend: endDrag,
                onclick: () => addWidgetSmart(type),
            }, icon(w.icon), h('span', {}, w.title)))));
        });
        if (!lists.children.length) lists.append(h('p.ffbe-empty-note', {}, 'No widgets match your search.'));
    };
    search.addEventListener('input', paint);
    paint();
    ui.panelBody.replaceChildren(
        search,
        lists,
        h('div.ffbe-panel-tip', {}, icon('fa-lightbulb'), h('span', {}, 'Tip: right-click any element for more actions. Press ? for keyboard shortcuts.')),
    );
}

function renderElementView() {
    const found = state.selected && store.find(state.selected);
    if (!found) return showView('widgets');
    const title = store.title(found);
    ui.panelTitle.textContent = `Edit ${title}`;

    const crumbs = [found.section && ['Section', found.section.id], found.column && ['Column', found.column.id], found.widget && [title, found.widget.id]].filter(Boolean);
    const breadcrumb = h('nav.ffbe-breadcrumb', { 'aria-label': 'Element path' }, crumbs.map(([label, id], i) => [
        i ? h('span.ffbe-breadcrumb__sep', { 'aria-hidden': 'true' }, '›') : null,
        h('button', { type: 'button', class: id === state.selected ? 'is-current' : '', onclick: () => select(id) }, label),
    ]));

    const controls = store.controlsFor(found.kind, found.node.type);
    const tabsWithControls = ['content', 'style', 'advanced'].filter((t) => controls.some((c) => (c.tab || 'content') === t && c.type !== 'heading'));
    if (!tabsWithControls.includes(state.tab)) state.tab = tabsWithControls[0] || 'content';
    const body = h('div.ffbe-controls');
    const tabs = h('div.ffbe-tabs', { role: 'tablist' }, tabsWithControls.map((t) => h('button.ffbe-tab', {
        type: 'button', role: 'tab', 'aria-selected': String(t === state.tab), class: t === state.tab ? 'is-active' : '',
        onclick: () => { state.tab = t; renderElementView(); },
    }, icon({ content: 'fa-pen', style: 'fa-palette', advanced: 'fa-gear' }[t]), ' ', t[0].toUpperCase() + t.slice(1))));

    const id = found.node.id;
    refreshVisibility = renderControls(body, controls, state.tab, {
        key: found.kind === 'widget' ? found.node.type : found.kind,
        settings: () => store.find(id)?.node.settings || {},
        set: (name, value) => store.setSetting(id, name, value),
        device: () => state.device,
        cycleDevice: () => setDevice({ desktop: 'tablet', tablet: 'mobile', mobile: 'desktop' }[state.device]),
        openMedia,
    });

    const actions = h('div.ffbe-element-actions');
    if (found.kind === 'section') {
        actions.append(
            h('h4.ffbe-subtitle', {}, 'Structure'),
            structurePicker((structure) => store.setStructure(id, structure), found.node.columns.length),
            h('div.ffbe-btn-row', {},
                h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => { const c = store.addColumn(id); if (c) select(c); } }, icon('fa-plus'), ' Add column'),
                h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => saveAsTemplate('section', id) }, icon('fa-floppy-disk'), ' Save as template')));
    } else if (found.kind === 'column') {
        actions.append(h('div.ffbe-btn-row', {},
            h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => { const c = store.addColumn(found.section.id, found.index); if (c) select(c); } }, icon('fa-plus'), ' Add column'),
            h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => select(found.section.id) }, icon('fa-arrow-up'), ' Edit section')));
    }
    actions.append(h('div.ffbe-btn-row', {},
        h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', title: 'Ctrl+D', onclick: () => runAction('duplicate', id) }, icon('fa-clone'), ' Duplicate'),
        h('button.ffbe-btn.ffbe-btn--danger-light.ffbe-btn--sm', { type: 'button', title: 'Delete', onclick: () => runAction('delete', id) }, icon('fa-trash'), ' Delete')));

    ui.panelBody.replaceChildren(breadcrumb, tabs, body, actions);
}

function structurePicker(onPick, current = 0) {
    return h('div.ffbe-structures', {}, STRUCTURES.map((structure) => h('button.ffbe-structure', {
        type: 'button',
        title: structure.map((w) => `${Math.round(w)}%`).join(' / '),
        'aria-label': `${structure.length} columns: ${structure.map((w) => `${Math.round(w)}%`).join(', ')}`,
        class: current && structure.length === current && structure.every((w) => Math.abs(w - structure[0]) < 0.5) ? 'is-current' : '',
        onclick: () => onPick(structure),
    }, structure.map((w) => h('span', { style: { flexGrow: String(w) } })))));
}

function renderHistoryView() {
    ui.panelTitle.textContent = 'History';
    const actions = h('div');
    const revisions = h('div');
    let tab = 'actions';
    const tabs = h('div.ffbe-tabs', { role: 'tablist' });
    const paintTabs = () => {
        tabs.replaceChildren(...[['actions', 'Actions'], ['revisions', 'Revisions']].map(([key, label]) => h('button.ffbe-tab', {
            type: 'button', role: 'tab', 'aria-selected': String(tab === key), class: tab === key ? 'is-active' : '',
            onclick: () => { tab = key; paintTabs(); },
        }, label)));
        actions.hidden = tab !== 'actions';
        revisions.hidden = tab !== 'revisions';
        if (tab === 'revisions') loadRevisions(revisions);
    };

    const list = h('ol.ffbe-history');
    store.undoStack.forEach((entry, index) => list.append(h('li', {}, h('button', {
        type: 'button', title: 'Go back to before this action', onclick: () => { store.jumpTo(index); renderHistoryView(); },
    }, icon('fa-rotate-left'), ' ', entry.label))));
    list.append(h('li.is-current', {}, h('span', {}, icon('fa-circle-dot'), ' Current version')));
    [...store.redoStack].reverse().forEach((entry) => list.append(h('li.is-redo', {}, h('span', {}, icon('fa-rotate-right'), ' ', entry.label))));
    actions.append(store.undoStack.length ? list : h('p.ffbe-empty-note', {}, 'Your changes in this session will appear here.'), h('p.ffbe-control__help', {}, 'Ctrl+Z to undo, Ctrl+Shift+Z or Ctrl+Y to redo.'));

    ui.panelBody.replaceChildren(tabs, actions, revisions);
    paintTabs();
}

async function loadRevisions(container) {
    if (!state.article.revisions_url) {
        container.replaceChildren(h('p.ffbe-empty-note', {}, 'Revisions are created every time you save. Save the article to start its history.'));
        return;
    }
    container.replaceChildren(h('p.ffbe-empty-note', {}, 'Loading revisions…'));
    try {
        const items = await request(state.article.revisions_url);
        container.replaceChildren(items.length ? h('ul.ffbe-revisions', {}, items.map((rev, i) => h('li', {},
            h('div', {}, h('strong', {}, i === 0 ? 'Latest save' : rev.created_human), h('span', {}, `${rev.user} · ${rev.status ?? ''} · ${new Date(rev.created_at).toLocaleString()}`)),
            i === 0 ? null : h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => restoreRevision(rev) }, 'Restore')))) : h('p.ffbe-empty-note', {}, 'No revisions yet.'));
    } catch (error) {
        container.replaceChildren(h('p.ffbe-empty-note', {}, `Could not load revisions: ${error.message}`));
    }
}

async function restoreRevision(rev) {
    if (!(await confirmDialog(`Load the version saved ${rev.created_human} by ${rev.user}? Your current content stays in the undo history until you save.`, { title: 'Restore revision', confirmLabel: 'Load revision' }))) return;
    try {
        const data = await request(`${state.article.revisions_url}/${rev.id}`);
        store.replace(data.layout, `Restored revision from ${rev.created_human}`);
        select(null, { openPanel: false });
        toast('Revision loaded. Save to keep it.', 'success');
    } catch (error) {
        toast(error.message, 'error');
    }
}

// ===========================================================================
// Actions
// ===========================================================================

function insertionTarget() {
    const found = state.selected && store.find(state.selected);
    if (found?.kind === 'widget') return { columnId: found.column.id, index: found.index + 1 };
    if (found?.kind === 'column') return { columnId: found.node.id };
    if (found?.kind === 'section') return { columnId: found.node.columns[found.node.columns.length - 1].id };
    return { sectionIndex: store.layout.sections.length };
}

function addWidgetSmart(type) {
    const id = store.addWidget(type, insertionTarget());
    select(id, { scroll: true });
}

function runAction(action, id) {
    const found = id && store.find(id);
    switch (action) {
        case 'edit':
            select(id);
            break;
        case 'duplicate': {
            if (!found) return;
            const copy = store.duplicate(id);
            select(copy);
            break;
        }
        case 'delete': {
            if (!found) return;
            const next = found.kind === 'widget' ? (found.list[found.index + 1] || found.list[found.index - 1])?.id ?? null : null;
            store.remove(id);
            select(next, { openPanel: !!next });
            toast(`${store.title(found)} deleted. Press Ctrl+Z to undo.`);
            break;
        }
        case 'add-section-before':
            if (found) openStructureModal(found.index);
            break;
        case 'add-column': {
            if (!found) return;
            const column = store.addColumn(found.section.id, found.index);
            if (column) select(column); else toast('A section can have up to 6 columns.', 'error');
            break;
        }
        case 'copy':
            if (!found) return;
            state.clipboard = { kind: found.kind, type: found.node.type, node: clone(found.node) };
            storage.set('ffb-clipboard', state.clipboard);
            toast(`${store.title(found)} copied.`);
            break;
        case 'paste':
            paste();
            break;
        case 'paste-style':
            if (found && state.clipboard && store.pasteStyle(id, state.clipboard.node.settings || {}, state.clipboard.kind, state.clipboard.type)) {
                toast('Style pasted.');
                if (state.view === 'element') renderElementView();
            }
            break;
        case 'reset-style':
            if (found) {
                store.resetStyle(id);
                if (state.view === 'element') renderElementView();
            }
            break;
        case 'save-template':
            if (found) saveAsTemplate('section', found.section.id);
            break;
        case 'navigator':
            toggleNavigator(true);
            break;
        default:
    }
}

function paste() {
    const clip = state.clipboard || storage.get('ffb-clipboard');
    if (!clip) return toast('Nothing to paste yet. Copy an element first (Ctrl+C).');
    const node = store.reId(clip.node);
    const found = state.selected && store.find(state.selected);
    if (clip.kind === 'section') {
        const index = found ? store.layout.sections.indexOf(found.section) + 1 : store.layout.sections.length;
        select(store.insertSection(node, index));
    } else if (clip.kind === 'widget') {
        store.insertWidget(node, insertionTarget(), `Pasted ${store.title({ kind: 'widget', node })}`);
        select(node.id);
    } else if (clip.kind === 'column') {
        const section = found?.section;
        if (!section || section.columns.length >= 6) return toast('Select a section with fewer than 6 columns to paste a column.', 'error');
        store.mutate('Pasted column', () => section.columns.splice(found.kind === 'column' ? found.index + 1 : section.columns.length, 0, node), { reason: 'structure' });
        select(node.id);
    }
}

function openStructureModal(index = store.layout.sections.length) {
    const m = modal({
        title: 'Select your structure',
        size: 'sm',
        body: structurePicker((structure) => {
            m.close();
            const id = store.insertSection(store.makeSection(structure), index);
            select(id, { scroll: true });
        }),
    });
}

async function saveAsTemplate(type, sectionId = null) {
    const name = await promptDialog('Template name', { title: type === 'section' ? 'Save section as template' : 'Save page as template', placeholder: 'e.g. Market update intro' });
    if (!name) return;
    const layout = type === 'section'
        ? { version: 1, sections: [store.find(sectionId)?.node].filter(Boolean) }
        : store.layout;
    try {
        await request(urls.templates, { method: 'POST', body: { name, type, layout: JSON.stringify(layout) } });
        toast('Template saved.', 'success');
    } catch (error) {
        toast(error.message, 'error');
    }
}

function insertSections(sections, label) {
    const fresh = sections.map((s) => store.reId(s));
    const found = state.selected && store.find(state.selected);
    const index = found ? store.layout.sections.indexOf(found.section) + 1 : store.layout.sections.length;
    store.mutate(label, (layout) => layout.sections.splice(index, 0, ...fresh), { reason: 'structure' });
    select(fresh[0]?.id, { scroll: true });
}

// ===========================================================================
// Templates library
// ===========================================================================

function openTemplates() {
    let tab = 'blocks';
    const body = h('div.ffbe-library');
    const tabs = h('div.ffbe-tabs');
    const content = h('div.ffbe-library__content');
    body.append(tabs, content);
    const m = modal({
        title: 'Templates library',
        size: 'lg',
        body,
        footer: [h('button.ffbe-btn.ffbe-btn--light', { type: 'button', onclick: () => { m.close(); saveAsTemplate('page'); } }, icon('fa-floppy-disk'), ' Save this page as a template')],
    });

    const paint = async () => {
        tabs.replaceChildren(...[['blocks', 'Blocks'], ['mine', 'My templates']].map(([key, label]) => h('button.ffbe-tab', {
            type: 'button', class: tab === key ? 'is-active' : '', 'aria-selected': String(tab === key), onclick: () => { tab = key; paint(); },
        }, label)));
        if (tab === 'blocks') {
            content.replaceChildren(h('div.ffbe-block-grid', {}, BLOCKS.map((block) => h('button.ffbe-block-card', {
                type: 'button',
                onclick: () => { m.close(); insertSections(block.sections, `Inserted block “${block.name}”`); },
            }, icon(block.icon), h('strong', {}, block.name), h('span', {}, 'Insert')))));
            return;
        }
        content.replaceChildren(h('p.ffbe-empty-note', {}, 'Loading…'));
        try {
            const items = await request(urls.templates);
            if (!items.length) {
                content.replaceChildren(h('p.ffbe-empty-note', {}, 'No saved templates yet. Save a section (right-click › Save as template) or this whole page.'));
                return;
            }
            content.replaceChildren(h('table.ffbe-table', {},
                h('thead', {}, h('tr', {}, h('th', {}, 'Name'), h('th', {}, 'Type'), h('th', {}, 'Created'), h('th', {}, h('span.sr-only', {}, 'Actions')))),
                h('tbody', {}, items.map((t) => h('tr', {},
                    h('td', {}, t.name), h('td', {}, t.type === 'section' ? 'Section' : 'Page'), h('td', {}, `${t.created_human}${t.author ? ` · ${t.author}` : ''}`),
                    h('td.ffbe-table__actions', {},
                        h('button.ffbe-btn.ffbe-btn--primary.ffbe-btn--sm', {
                            type: 'button',
                            onclick: async () => {
                                const data = await request(`${urls.templates}/${t.id}`);
                                m.close();
                                insertSections(data.layout.sections || [], `Inserted template “${t.name}”`);
                            },
                        }, 'Insert'),
                        h('button.ffbe-icon-btn.ffbe-icon-btn--danger', {
                            type: 'button', title: 'Delete template', 'aria-label': `Delete template ${t.name}`,
                            onclick: async () => {
                                if (!(await confirmDialog(`Delete the template “${t.name}”? Articles already using it are not affected.`, { danger: true, confirmLabel: 'Delete' }))) return;
                                await request(`${urls.templates}/${t.id}`, { method: 'DELETE' });
                                toast('Template deleted.');
                                paint();
                            },
                        }, icon('fa-trash'))))))));
        } catch (error) {
            content.replaceChildren(h('p.ffbe-empty-note', {}, error.message));
        }
    };
    paint();
}

// ===========================================================================
// Media library
// ===========================================================================

function openMedia(onSelect) {
    const grid = h('div.ffbe-media-grid');
    const search = h('input.ffbe-input.ffbe-search', { type: 'search', placeholder: 'Search files…', 'aria-label': 'Search media' });
    const fileInput = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, hidden: true });
    const urlInput = h('input.ffbe-input', { type: 'text', placeholder: 'https://example.com/image.jpg', 'aria-label': 'Image URL' });
    let files = [];

    const paint = () => {
        const term = search.value.trim().toLowerCase();
        const shown = files.filter((f) => !term || f.name.toLowerCase().includes(term));
        grid.replaceChildren(...(shown.length ? shown.map((file) => h('button.ffbe-media-item', {
            type: 'button', title: file.name,
            onclick: () => { m.close(); onSelect(file.url); },
        }, h('img', { src: file.url, alt: '', loading: 'lazy' }), h('span', {}, file.name))) : [h('p.ffbe-empty-note', {}, files.length ? 'No files match.' : 'No images uploaded yet. Upload one to get started.')]));
    };
    const load = async () => {
        grid.replaceChildren(h('p.ffbe-empty-note', {}, 'Loading…'));
        try { files = await request(urls.media); } catch (error) { files = []; toast(error.message, 'error'); }
        paint();
    };
    const upload = async (list) => {
        const images = [...list].filter((f) => /^image\//.test(f.type));
        if (!images.length) return;
        drop.classList.add('is-uploading');
        let last = null;
        for (const file of images) {
            const fd = new FormData();
            fd.append('file', file);
            try {
                last = await request(urls.upload, { method: 'POST', body: fd });
                files.unshift(last);
            } catch (error) {
                toast(`${file.name}: ${Object.values(error.errors)[0]?.[0] || error.message}`, 'error', 6000);
            }
        }
        drop.classList.remove('is-uploading');
        paint();
        if (last && images.length === 1) { m.close(); onSelect(last.url); }
    };
    fileInput.addEventListener('change', () => upload(fileInput.files));
    search.addEventListener('input', paint);

    const drop = h('div.ffbe-dropzone', {
        ondragover: (e) => { e.preventDefault(); drop.classList.add('is-over'); },
        ondragleave: () => drop.classList.remove('is-over'),
        ondrop: (e) => { e.preventDefault(); drop.classList.remove('is-over'); upload(e.dataTransfer.files); },
    }, icon('fa-cloud-arrow-up'), h('span', {}, 'Drop images here or '), h('button.ffbe-btn.ffbe-btn--primary.ffbe-btn--sm', { type: 'button', onclick: () => fileInput.click() }, 'Upload files'), h('small', {}, 'JPG, PNG, WebP or GIF up to 5 MB'), fileInput);

    const m = modal({
        title: 'Media library',
        size: 'lg',
        body: h('div', {}, drop, h('div.ffbe-media-tools', {}, search,
            h('div.ffbe-input-group', {}, urlInput, h('button.ffbe-btn.ffbe-btn--light', { type: 'button', onclick: () => { const url = urlInput.value.trim(); if (url) { m.close(); onSelect(url); } } }, 'Use URL'))), grid),
    });
    load();
}

// ===========================================================================
// Navigator
// ===========================================================================

function toggleNavigator(force) {
    const show = force ?? ui.navigator.hidden;
    ui.navigator.hidden = !show;
    $('[data-action="navigator"]')?.classList.toggle('is-active', show);
    if (show) renderNavigator();
}

function widgetLabel(widget) {
    const s = widget.settings || {};
    const text = s.text || s.title || s.name || s.button_text || (s.content ? s.content.replace(/<[^>]+>/g, ' ') : '') || s.address || s.url || '';
    return String(text).replace(/\s+/g, ' ').trim().slice(0, 34);
}

function renderNavigator() {
    if (ui.navigator.hidden) return;
    const row = (kind, node, label, iconName, depth, extra = '') => {
        const item = h(`li.ffbe-nav-item.ffbe-nav-item--${kind}`, { dataset: { id: node.id, kind } },
            h('button.ffbe-nav-row', {
                type: 'button',
                draggable: kind === 'column' ? 'false' : 'true',
                class: node.id === state.selected ? 'is-selected' : '',
                style: { paddingLeft: `${8 + depth * 14}px` },
                onclick: () => select(node.id, { scroll: true }),
                ondragstart: (e) => {
                    state.drag = { kind: kind === 'section' ? 'move-section' : 'move-widget', id: node.id };
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', node.id);
                },
                ondragend: endDrag,
            }, icon(iconName), h('span', {}, label), extra ? h('em', {}, extra) : null));
        return item;
    };

    const tree = h('ul.ffbe-nav-tree');
    store.layout.sections.forEach((section, si) => {
        tree.append(row('section', section, `Section ${si + 1}`, 'fa-table-cells-large', 0));
        section.columns.forEach((column, ci) => {
            tree.append(row('column', column, `Column ${ci + 1}`, 'fa-table-columns', 1));
            column.widgets.forEach((widget) => {
                const w = schema.widgets[widget.type];
                tree.append(row('widget', widget, w?.title ?? widget.type, w?.icon ?? 'fa-cube', 2, widgetLabel(widget)));
            });
        });
    });
    if (!store.layout.sections.length) tree.append(h('li.ffbe-empty-note', {}, 'The page is empty.'));
    ui.navigatorTree.replaceChildren(tree);
}

function bindNavigatorDrop() {
    let marker = null;
    const clear = () => { marker?.classList.remove('drop-before', 'drop-after', 'drop-into'); marker = null; };
    ui.navigatorTree.addEventListener('dragover', (e) => {
        if (!state.drag) return;
        const item = e.target.closest('.ffbe-nav-item');
        if (!item) return;
        const kind = item.dataset.kind;
        const dragKind = state.drag.kind;
        const valid = dragKind === 'move-section' ? kind === 'section' : kind !== 'section' || dragKind === 'new-widget';
        if (!valid) return;
        e.preventDefault();
        clear();
        marker = item;
        const rect = item.getBoundingClientRect();
        item.classList.add(kind === 'column' ? 'drop-into' : e.clientY < rect.top + rect.height / 2 ? 'drop-before' : 'drop-after');
    });
    ui.navigatorTree.addEventListener('dragleave', (e) => { if (!ui.navigatorTree.contains(e.relatedTarget)) clear(); });
    ui.navigatorTree.addEventListener('drop', (e) => {
        if (!state.drag || !marker) return;
        e.preventDefault();
        const id = marker.dataset.id;
        const after = marker.classList.contains('drop-after');
        const found = store.find(id);
        clear();
        if (!found) return;
        if (state.drag.kind === 'move-section') {
            store.moveSection(state.drag.id, found.index + (after ? 1 : 0));
        } else {
            let target;
            if (found.kind === 'column') target = { columnId: id };
            else if (found.kind === 'widget') target = { columnId: found.column.id, index: found.index + (after ? 1 : 0) };
            else target = { columnId: found.node.columns[0].id, index: 0 };
            performWidgetDrop(target);
        }
        endDrag();
    });
}

// ===========================================================================
// Drag and drop in the canvas
// ===========================================================================

let indicator = null;

function endDrag() {
    state.drag = null;
    indicator?.remove();
    indicator = null;
    root?.querySelectorAll('.ffbe-drop-into').forEach((el) => el.classList.remove('ffbe-drop-into'));
}

function computeDrop(event) {
    const drag = state.drag;
    const target = event.target instanceof doc.defaultView.Element ? event.target : event.target.parentElement;
    if (!target) return null;
    const y = event.clientY;
    const sections = $$(':scope > .ffb > [data-ffb-el="section"]', root);

    const sectionSlot = () => {
        const sectionEl = target.closest('[data-ffb-el="section"]');
        if (sectionEl) {
            const rect = sectionEl.getBoundingClientRect();
            const after = y > rect.top + rect.height / 2;
            const index = sections.indexOf(sectionEl) + (after ? 1 : 0);
            return { sectionIndex: index, line: { rect, edge: after ? 'bottom' : 'top' } };
        }
        let index = sections.findIndex((s) => y < s.getBoundingClientRect().top + s.getBoundingClientRect().height / 2);
        if (index === -1) index = sections.length;
        const ref = sections[index] || sections[sections.length - 1];
        const rect = ref?.getBoundingClientRect() ?? doc.getElementById('ffb-add-zone').getBoundingClientRect();
        return { sectionIndex: index, line: { rect, edge: sections[index] ? 'top' : 'bottom' } };
    };

    if (drag.kind === 'move-section') return sectionSlot();

    if (drag.kind === 'move-column') {
        const colEl = target.closest('[data-ffb-el="column"]');
        const from = store.find(drag.id);
        if (!colEl || !from) return null;
        const found = store.find(colEl.dataset.ffbId);
        if (!found || found.section !== from.section) return null;
        const rect = colEl.getBoundingClientRect();
        const after = event.clientX > rect.left + rect.width / 2;
        return { columnIndex: found.index + (after ? 1 : 0), line: { rect, edge: after ? 'right' : 'left' } };
    }

    // Widgets (new or moved)
    const widgetEl = target.closest('[data-ffb-el="widget"]');
    if (widgetEl && widgetEl.dataset.ffbId !== drag.id) {
        const found = store.find(widgetEl.dataset.ffbId);
        if (found) {
            const rect = widgetEl.getBoundingClientRect();
            const after = y > rect.top + rect.height / 2;
            return { columnId: found.column.id, index: found.index + (after ? 1 : 0), line: { rect, edge: after ? 'bottom' : 'top' } };
        }
    }
    let colEl = target.closest('[data-ffb-el="column"]');
    if (!colEl) {
        const sectionEl = target.closest('[data-ffb-el="section"]');
        if (sectionEl) {
            const cols = $$(':scope > .ffb-container > [data-ffb-el="column"]', sectionEl);
            colEl = cols.find((c) => { const r = c.getBoundingClientRect(); return event.clientX >= r.left && event.clientX <= r.right; }) || cols[cols.length - 1];
        }
    }
    if (colEl) {
        const widgets = $$(':scope > .ffb-column-inner > [data-ffb-el="widget"]', colEl);
        if (!widgets.length) return { columnId: colEl.dataset.ffbId, index: 0, into: colEl };
        let index = widgets.findIndex((w) => y < w.getBoundingClientRect().top + w.getBoundingClientRect().height / 2);
        if (index === -1) index = widgets.length;
        const ref = widgets[Math.min(index, widgets.length - 1)];
        const found = store.find(ref.dataset.ffbId);
        return { columnId: colEl.dataset.ffbId, index, line: { rect: ref.getBoundingClientRect(), edge: index >= widgets.length ? 'bottom' : 'top' }, refIndex: found?.index };
    }
    return sectionSlot();
}

function showIndicator(drop) {
    root.querySelectorAll('.ffbe-drop-into').forEach((el) => el.classList.remove('ffbe-drop-into'));
    if (!drop) { indicator?.remove(); indicator = null; return; }
    if (drop.into) {
        indicator?.remove();
        indicator = null;
        drop.into.classList.add('ffbe-drop-into');
        return;
    }
    indicator ??= doc.body.appendChild(doc.createElement('div'));
    indicator.className = 'ffbe-drop-line';
    const { rect, edge } = drop.line;
    const scrollX = doc.defaultView.scrollX;
    const scrollY = doc.defaultView.scrollY;
    const vertical = edge === 'left' || edge === 'right';
    Object.assign(indicator.style, vertical ? {
        left: `${(edge === 'left' ? rect.left : rect.right) + scrollX - 2}px`, top: `${rect.top + scrollY}px`, width: '4px', height: `${rect.height}px`,
    } : {
        left: `${rect.left + scrollX}px`, top: `${(edge === 'top' ? rect.top : rect.bottom) + scrollY - 2}px`, width: `${rect.width}px`, height: '4px',
    });
}

function performWidgetDrop(target) {
    const drag = state.drag;
    if (!drag) return;
    if (drag.kind === 'new-widget') {
        const id = store.addWidget(drag.type, target);
        select(id);
    } else if (drag.kind === 'move-widget') {
        store.moveWidget(drag.id, target);
        select(drag.id, { openPanel: state.view === 'element' });
    }
}

function performDrop(drop) {
    const drag = state.drag;
    if (!drag || !drop) return;
    if (drag.kind === 'move-section') {
        store.moveSection(drag.id, drop.sectionIndex);
    } else if (drag.kind === 'move-column') {
        store.moveColumn(drag.id, drop.columnIndex);
    } else if (drop.columnId) {
        performWidgetDrop({ columnId: drop.columnId, index: drop.index });
    } else {
        performWidgetDrop({ sectionIndex: drop.sectionIndex });
    }
}

// ===========================================================================
// Inline editing
// ===========================================================================

function startInline(widgetEl) {
    const def = INLINE[widgetEl.dataset.ffbType];
    const target = def && widgetEl.querySelector(def.selector);
    if (!target) return false;
    const id = widgetEl.dataset.ffbId;
    state.inline = { id, field: def.field, rich: !!def.rich, target, widgetEl };
    widgetEl.classList.add('ffbe-inline-editing');
    try {
        target.contentEditable = def.rich ? 'true' : 'plaintext-only';
    } catch {
        target.contentEditable = 'true'; // browsers without plaintext-only support
    }
    target.focus();
    const range = doc.createRange();
    range.selectNodeContents(target);
    range.collapse(false);
    const selection = doc.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);

    const onInput = () => {
        const value = def.rich ? target.innerHTML : target.innerText.replace(/\n$/, '');
        store.setSetting(id, def.field, value, `Edited ${store.title(store.find(id))}`);
    };
    const onPaste = (event) => {
        if (def.rich) return;
        event.preventDefault();
        doc.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
    };
    const onKey = (event) => {
        if (event.key === 'Escape' || (event.key === 'Enter' && def.single)) {
            event.preventDefault();
            target.blur();
        }
    };
    target.addEventListener('input', onInput);
    target.addEventListener('paste', onPaste);
    target.addEventListener('keydown', onKey);
    target.addEventListener('blur', () => {
        target.removeEventListener('input', onInput);
        target.removeEventListener('paste', onPaste);
        target.removeEventListener('keydown', onKey);
        endInline();
    }, { once: true });
    return true;
}

function endInline() {
    if (!state.inline) return;
    const { target, widgetEl, id } = state.inline;
    target.contentEditable = 'false';
    target.removeAttribute('contenteditable');
    widgetEl.classList.remove('ffbe-inline-editing');
    state.inline = null;
    if (state.pendingRender) {
        state.pendingRender = false;
        renderNow();
    }
    if (state.view === 'element' && state.selected === id && state.tab === 'content') renderElementView();
}

// ===========================================================================
// Context menu
// ===========================================================================

let contextMenu = null;

function closeContextMenu() {
    contextMenu?.remove();
    contextMenu = null;
}

function openContextMenu(id, x, y) {
    closeContextMenu();
    const found = store.find(id);
    if (!found) return;
    const item = (label, iconName, action, shortcut = '', disabled = false) => h('button.ffbe-menu__item', {
        type: 'button', role: 'menuitem', disabled,
        onclick: () => { closeContextMenu(); runAction(action, id); },
    }, icon(iconName), h('span', {}, label), shortcut ? h('kbd', {}, shortcut) : null);
    const hasClip = !!(state.clipboard || storage.get('ffb-clipboard'));
    contextMenu = h('div.ffbe-menu', { role: 'menu', 'aria-label': `${store.title(found)} actions` },
        h('div.ffbe-menu__title', {}, store.title(found)),
        item(`Edit ${store.title(found)}`, 'fa-pencil', 'edit'),
        item('Duplicate', 'fa-clone', 'duplicate', 'Ctrl+D'),
        h('hr'),
        item('Copy', 'fa-copy', 'copy', 'Ctrl+C'),
        item('Paste', 'fa-paste', 'paste', 'Ctrl+V', !hasClip),
        item('Paste style', 'fa-brush', 'paste-style', 'Ctrl+Shift+V', !hasClip),
        item('Reset style', 'fa-eraser', 'reset-style'),
        h('hr'),
        item('Save section as template', 'fa-floppy-disk', 'save-template'),
        item('Navigator', 'fa-layer-group', 'navigator'),
        h('hr'),
        item('Delete', 'fa-trash', 'delete', 'Del'));
    document.body.append(contextMenu);
    const rect = contextMenu.getBoundingClientRect();
    contextMenu.style.left = `${Math.min(x, window.innerWidth - rect.width - 8)}px`;
    contextMenu.style.top = `${Math.min(y, window.innerHeight - rect.height - 8)}px`;
    contextMenu.querySelector('button:not([disabled])')?.focus();
}

// ===========================================================================
// Canvas binding
// ===========================================================================

function bindCanvas() {
    doc = ui.frame.contentDocument;
    root = doc.getElementById('ffb-root');
    const frameOffset = () => ui.frame.getBoundingClientRect();

    doc.addEventListener('click', (event) => {
        closeContextMenu();
        const button = event.target.closest('[data-action]');
        if (button && button.closest('.ffbe-handle')) {
            event.preventDefault();
            event.stopPropagation();
            if (button.dataset.action !== 'drag') runAction(button.dataset.action, button.dataset.id);
            else select(button.dataset.id);
            return;
        }
        if (event.target.closest('#ffb-add-zone')) {
            const action = event.target.closest('[data-zone-action]')?.dataset.zoneAction;
            if (action === 'section') openStructureModal();
            if (action === 'template') openTemplates();
            return;
        }
        if (event.target.closest('#ffbc-header')) {
            showView('page');
            return;
        }
        if (state.inline && state.inline.target.contains(event.target)) return;
        if (event.target.closest('a')) event.preventDefault();
        const el = event.target.closest('[data-ffb-id]');
        if (!el) {
            select(null, { openPanel: false });
            return;
        }
        if (el.dataset.ffbId === state.selected && el.dataset.ffbEl === 'widget' && !state.inline) {
            startInline(el);
            return;
        }
        select(el.dataset.ffbId);
    }, true);

    doc.addEventListener('dblclick', (event) => {
        const el = event.target.closest('[data-ffb-el="widget"]');
        if (el && !event.target.closest('.ffbe-handle')) {
            select(el.dataset.ffbId);
            startInline(el);
        }
    });

    doc.addEventListener('submit', (event) => event.preventDefault(), true);

    doc.addEventListener('contextmenu', (event) => {
        const el = event.target.closest('[data-ffb-id]');
        if (!el || state.inline) return;
        event.preventDefault();
        select(el.dataset.ffbId);
        const offset = frameOffset();
        openContextMenu(el.dataset.ffbId, offset.left + event.clientX, offset.top + event.clientY);
    });

    doc.addEventListener('dragstart', (event) => {
        const handle = event.target.closest?.('[data-action="drag"]');
        if (!handle) {
            // Prevent dragging images/links out of the canvas.
            if (!state.drag) event.preventDefault();
            return;
        }
        const kind = handle.dataset.kind;
        state.drag = { kind: kind === 'section' ? 'move-section' : kind === 'column' ? 'move-column' : 'move-widget', id: handle.dataset.id };
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', handle.dataset.id);
        const el = handle.closest('[data-ffb-el]');
        if (el) event.dataTransfer.setDragImage(el, 20, 20);
        doc.body.classList.add('ffbe-dragging');
    });
    doc.addEventListener('dragover', (event) => {
        if (!state.drag) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = state.drag.kind === 'new-widget' ? 'copy' : 'move';
        showIndicator(computeDrop(event));
    });
    doc.addEventListener('dragleave', (event) => {
        if (!event.relatedTarget) showIndicator(null);
    });
    doc.addEventListener('drop', (event) => {
        if (!state.drag) return;
        event.preventDefault();
        const drop = computeDrop(event);
        performDrop(drop);
        endDrag();
        doc.body.classList.remove('ffbe-dragging');
    });
    doc.addEventListener('dragend', () => {
        endDrag();
        doc.body.classList.remove('ffbe-dragging');
    });

    doc.addEventListener('keydown', onKeydown);
    doc.addEventListener('scroll', closeContextMenu);

    applyTemplateToCanvas();
    syncCanvasHeader();
    renderNow();
}

// ===========================================================================
// Article settings (meta form) & canvas header
// ===========================================================================

function metaValue(name) {
    return ui.metaForm.elements[name]?.value ?? '';
}

function applyTemplateToCanvas() {
    if (!doc) return;
    const wrap = doc.getElementById('ffbc-wrap');
    const template = metaValue('page_template') || 'default';
    wrap.className = `article-wrap article-wrap--${template}`;
    doc.getElementById('ffbc-header').hidden = template === 'canvas';
}

function syncCanvasHeader() {
    if (!doc) return;
    const title = metaValue('title').trim();
    doc.getElementById('ffbc-title').textContent = title || 'Add a title in Article settings';
    doc.getElementById('ffbc-title').classList.toggle('is-placeholder', !title);
    const excerpt = metaValue('excerpt').trim();
    doc.getElementById('ffbc-excerpt').textContent = excerpt;
    doc.getElementById('ffbc-excerpt').hidden = !excerpt;
    const category = ui.metaForm.elements.category_id;
    doc.getElementById('ffbc-category').textContent = category?.value ? category.selectedOptions[0]?.textContent : '';
    const img = doc.getElementById('ffbc-featured');
    const preview = $('#ffbe-featured-preview');
    const src = preview && !preview.hidden ? preview.src : '';
    img.hidden = !src;
    if (src) img.src = src;
    ui.topTitle.textContent = title || 'Untitled article';
    document.title = `${title || 'New article'} | Builder`;
}

function bindMetaForm() {
    const form = ui.metaForm;
    form.addEventListener('submit', (e) => { e.preventDefault(); save(); });
    form.addEventListener('input', (event) => {
        markDirty();
        if (['title', 'excerpt'].includes(event.target.name)) syncCanvasHeader();
        if (event.target.name === 'title' && !state.article.id && !form.dataset.slugEdited) {
            form.elements.slug.placeholder = slugify(event.target.value);
        }
        if (event.target.name === 'slug') form.dataset.slugEdited = '1';
        updateCounters();
        updateSnippet();
    });
    form.addEventListener('change', (event) => {
        markDirty();
        if (event.target.name === 'page_template') applyTemplateToCanvas();
        if (event.target.name === 'category_id') syncCanvasHeader();
        if (event.target.name === 'status') {
            $('#ffbe-schedule-field').classList.toggle('is-required', event.target.value === 'scheduled');
            updatePublishLabel();
        }
    });

    // Featured image preview
    const file = form.elements.featured_image_file;
    const preview = $('#ffbe-featured-preview');
    file?.addEventListener('change', () => {
        const chosen = file.files?.[0];
        if (!chosen) return;
        if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
        preview.dataset.objectUrl = URL.createObjectURL(chosen);
        preview.src = preview.dataset.objectUrl;
        preview.hidden = false;
        form.elements.remove_featured_image.checked = false;
        $('#ffbe-featured-remove').hidden = false;
        syncCanvasHeader();
    });
    $('#ffbe-featured-remove')?.addEventListener('click', () => {
        file.value = '';
        preview.hidden = true;
        preview.removeAttribute('src');
        form.elements.remove_featured_image.checked = true;
        $('#ffbe-featured-remove').hidden = true;
        markDirty();
        syncCanvasHeader();
    });

    // Tag filter
    $('#ffbe-tag-search')?.addEventListener('input', (e) => {
        const term = e.target.value.trim().toLowerCase();
        $$('#ffbe-tag-list .ffbe-check').forEach((label) => { label.hidden = term && !label.textContent.toLowerCase().includes(term); });
    });

    updateCounters();
    updateSnippet();
}

function slugify(value) {
    return value.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180);
}

function updateCounters() {
    $$('[data-counter-for]').forEach((counter) => {
        const field = ui.metaForm.elements[counter.dataset.counterFor];
        const max = Number(counter.dataset.max);
        const length = field.value.length;
        counter.textContent = `${length}/${max}`;
        counter.classList.toggle('is-over', length > Number(counter.dataset.ideal || max));
    });
}

function updateSnippet() {
    const snippet = $('#ffbe-snippet');
    if (!snippet) return;
    const title = metaValue('seo_title') || metaValue('title') || 'Article title';
    const description = metaValue('seo_description') || metaValue('excerpt') || 'Add an SEO description to control how this article appears in search results.';
    const slug = metaValue('slug') || ui.metaForm.elements.slug.placeholder || state.article.slug || 'article';
    snippet.querySelector('[data-snippet=url]').textContent = `${config.siteUrl.replace(/^https?:\/\//, '')} › articles › ${slugify(slug)}`;
    snippet.querySelector('[data-snippet=title]').textContent = title.slice(0, 70);
    snippet.querySelector('[data-snippet=desc]').textContent = description.slice(0, 160);
}

function updatePublishLabel() {
    const status = metaValue('status');
    const published = state.article.status === 'published';
    ui.publishBtn.querySelector('span').textContent = published ? 'Update' : status === 'scheduled' ? 'Schedule' : 'Publish';
    ui.topStatus.textContent = state.article.id ? state.article.status : 'new';
    ui.topStatus.dataset.status = state.article.id ? state.article.status : 'new';
}

// ===========================================================================
// Saving
// ===========================================================================

function markDirty() {
    if (!state.dirty) {
        state.dirty = true;
        ui.saveState.textContent = 'Unsaved changes';
        ui.saveState.dataset.state = 'dirty';
    }
    autosave();
}

const autosaveKey = () => `ffb-autosave:${state.article.id || 'new'}`;
const autosave = debounce(() => {
    storage.set(autosaveKey(), { layout: store.layout, title: metaValue('title'), at: Date.now() });
}, 1500);

async function save(status = null) {
    if (state.saving) return;
    if (state.inline) state.inline.target.blur();
    const form = ui.metaForm;
    if (status) form.elements.status.value = status;
    if (!metaValue('title').trim()) {
        showView('page');
        form.elements.title.focus();
        toast('Add a title before saving.', 'error');
        return;
    }
    if (metaValue('status') === 'scheduled' && !metaValue('scheduled_for')) {
        showView('page');
        form.elements.scheduled_for.focus();
        toast('Choose a date in “Scheduled for” to schedule this article.', 'error');
        return;
    }

    const fd = new FormData(form);
    fd.set('layout', JSON.stringify(store.layout));
    if (!form.elements.slug.value && !state.article.id) fd.delete('slug');
    if (state.article.id) fd.set('_method', 'PUT');

    state.saving = true;
    ui.app.classList.add('is-saving');
    ui.saveState.textContent = 'Saving…';
    ui.saveState.dataset.state = 'saving';
    $$('.ffbe-field-error', form).forEach((el) => el.remove());

    try {
        const data = await request(state.article.id ? state.article.update_url : urls.store, { method: 'POST', body: fd });
        const article = data.article;
        const wasNew = !state.article.id;
        Object.assign(state.article, article);
        if (wasNew) {
            storage.remove('ffb-autosave:new');
            history.replaceState({}, '', article.edit_url);
        }
        storage.remove(autosaveKey());
        form.elements.slug.value = article.slug;
        delete form.dataset.slugEdited;
        if (article.published_at && form.elements.published_at) form.elements.published_at.value = article.published_at;
        afterSaveFeatured(article);
        afterSaveTags(article);
        state.dirty = false;
        ui.saveState.textContent = `Saved at ${article.updated_human}`;
        ui.saveState.dataset.state = 'saved';
        $('#ffbe-view-live').hidden = article.status !== 'published';
        $('#ffbe-view-live').href = article.public_url;
        updatePublishLabel();
        toast(data.message || 'Saved.', 'success');
        if (state.view === 'history') renderHistoryView();
    } catch (error) {
        ui.saveState.textContent = 'Not saved';
        ui.saveState.dataset.state = 'error';
        if (error.status === 422) {
            showView('page');
            Object.entries(error.errors).forEach(([field, messages]) => {
                const input = form.elements[field] || form.elements[`${field}[]`] || form.elements[field.replace(/\..*$/, '')];
                const host = (input?.closest ? input : input?.[0])?.closest?.('.ffbe-field');
                host?.append(h('p.ffbe-field-error', {}, messages[0]));
            });
            toast(Object.values(error.errors)[0]?.[0] || 'Please fix the highlighted fields.', 'error', 6000);
        } else if (error.status === 419) {
            toast('Your session expired. Copy any unsaved text, then reload the page and sign in again.', 'error', 10000);
        } else {
            toast(`Save failed: ${error.message}`, 'error', 6000);
        }
    } finally {
        state.saving = false;
        ui.app.classList.remove('is-saving');
    }
}

function afterSaveFeatured(article) {
    const form = ui.metaForm;
    form.elements.featured_image_file.value = '';
    form.elements.remove_featured_image.checked = false;
    const preview = $('#ffbe-featured-preview');
    if (article.featured_image_url) {
        preview.src = article.featured_image_url;
        preview.hidden = false;
        $('#ffbe-featured-remove').hidden = false;
    } else {
        preview.hidden = true;
        $('#ffbe-featured-remove').hidden = true;
    }
}

function afterSaveTags(article) {
    const list = $('#ffbe-tag-list');
    const selected = new Set(article.tags.map((t) => String(t.id)));
    const existing = new Set($$('input[name="tags[]"]', list).map((i) => i.value));
    article.tags.filter((t) => !existing.has(String(t.id))).forEach((tag) => {
        list.prepend(h('label.ffbe-check', {}, h('input', { type: 'checkbox', name: 'tags[]', value: String(tag.id) }), tag.name));
    });
    $$('input[name="tags[]"]', list).forEach((input) => { input.checked = selected.has(input.value); });
    ui.metaForm.elements.new_tags.value = '';
}

function preview() {
    const form = ui.previewForm;
    form.elements.layout.value = JSON.stringify(store.layout);
    ['title', 'excerpt', 'category_id', 'page_template'].forEach((name) => { form.elements[name].value = metaValue(name); });
    form.elements.article_id.value = state.article.id || '';
    form.submit();
}

// ===========================================================================
// Devices, undo/redo, shortcuts
// ===========================================================================

function setDevice(device) {
    state.device = device;
    ui.frameWrap.style.width = DEVICE_WIDTHS[device];
    ui.frameWrap.dataset.device = device;
    $$('[data-device]').forEach((b) => {
        const active = b.dataset.device === device;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-pressed', String(active));
    });
    if (state.view === 'element') renderElementView();
}

function updateUndoButtons() {
    ui.undoBtn.disabled = !store.undoStack.length;
    ui.redoBtn.disabled = !store.redoStack.length;
    ui.undoBtn.title = store.undoStack.length ? `Undo: ${store.undoStack[store.undoStack.length - 1].label} (Ctrl+Z)` : 'Nothing to undo';
    ui.redoBtn.title = store.redoStack.length ? `Redo: ${store.redoStack[store.redoStack.length - 1].label} (Ctrl+Shift+Z)` : 'Nothing to redo';
}

function undo() {
    const label = store.undo();
    if (label) toast(`Undone: ${label}`, 'info', 1600);
}

function redo() {
    const label = store.redo();
    if (label) toast(`Redone: ${label}`, 'info', 1600);
}

function isTyping(event) {
    const t = event.target;
    return t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName);
}

function onKeydown(event) {
    const mod = event.ctrlKey || event.metaKey;
    const key = event.key.toLowerCase();
    if (mod && key === 's') {
        event.preventDefault();
        save();
        return;
    }
    if (event.key === 'Escape') {
        if (contextMenu) return closeContextMenu();
        if (!isTyping(event) && state.selected) select(null, { openPanel: false });
        return;
    }
    if (isTyping(event)) return;
    if (mod && key === 'z' && !event.shiftKey) { event.preventDefault(); undo(); }
    else if (mod && ((key === 'z' && event.shiftKey) || key === 'y')) { event.preventDefault(); redo(); }
    else if (mod && key === 'd' && state.selected) { event.preventDefault(); runAction('duplicate', state.selected); }
    else if (mod && key === 'c' && state.selected) { event.preventDefault(); runAction('copy', state.selected); }
    else if (mod && key === 'v' && event.shiftKey && state.selected) { event.preventDefault(); runAction('paste-style', state.selected); }
    else if (mod && key === 'v') { event.preventDefault(); paste(); }
    else if (mod && key === 'p') { event.preventDefault(); preview(); }
    else if (mod && key === 'i') { event.preventDefault(); toggleNavigator(); }
    else if (mod && event.shiftKey && key === 'm') { event.preventDefault(); setDevice({ desktop: 'tablet', tablet: 'mobile', mobile: 'desktop' }[state.device]); }
    else if ((event.key === 'Delete' || event.key === 'Backspace') && state.selected) { event.preventDefault(); runAction('delete', state.selected); }
    else if (event.key === 'ArrowUp' && event.altKey && state.selected) { event.preventDefault(); store.nudge(state.selected, -1); }
    else if (event.key === 'ArrowDown' && event.altKey && state.selected) { event.preventDefault(); store.nudge(state.selected, 1); }
    else if (event.key === '?') { event.preventDefault(); showShortcuts(); }
}

function showShortcuts() {
    const rows = [
        ['Ctrl + S', 'Save'], ['Ctrl + Z', 'Undo'], ['Ctrl + Shift + Z / Ctrl + Y', 'Redo'], ['Ctrl + D', 'Duplicate element'],
        ['Ctrl + C / Ctrl + V', 'Copy / paste element'], ['Ctrl + Shift + V', 'Paste style'], ['Delete', 'Delete element'],
        ['Alt + ↑ / ↓', 'Move element up / down'], ['Ctrl + P', 'Preview'], ['Ctrl + I', 'Navigator'], ['Ctrl + Shift + M', 'Switch device'],
        ['Double-click text', 'Edit text in place'], ['Right-click', 'Element actions'], ['Esc', 'Deselect'], ['?', 'This help'],
    ];
    modal({ title: 'Keyboard shortcuts', size: 'sm', body: h('table.ffbe-table', {}, h('tbody', {}, rows.map(([k, label]) => h('tr', {}, h('td', {}, h('kbd', {}, k)), h('td', {}, label))))) });
}

// ===========================================================================
// Autosave recovery
// ===========================================================================

function offerRecovery() {
    const saved = storage.get(autosaveKey());
    if (!saved?.layout || saved.at <= (state.article.updated_ts || 0) * 1000) return;
    if (JSON.stringify(saved.layout) === JSON.stringify(store.layout)) return;
    const bar = h('div.ffbe-recovery', { role: 'alert' },
        icon('fa-life-ring'),
        h('span', {}, `You have unsaved changes from ${new Date(saved.at).toLocaleString()}.`),
        h('button.ffbe-btn.ffbe-btn--primary.ffbe-btn--sm', { type: 'button', onclick: () => { store.replace(saved.layout, 'Recovered unsaved changes'); bar.remove(); toast('Unsaved changes restored. Save to keep them.', 'success'); } }, 'Restore'),
        h('button.ffbe-btn.ffbe-btn--light.ffbe-btn--sm', { type: 'button', onclick: () => { storage.remove(autosaveKey()); bar.remove(); } }, 'Discard'));
    ui.frameWrap.parentElement.prepend(bar);
}

// ===========================================================================
// Boot
// ===========================================================================

function boot() {
    store.subscribe((reason) => {
        markDirty();
        scheduleRender();
        updateUndoButtons();
        if (reason === 'change') {
            refreshVisibility();
            if (!ui.navigator.hidden) renderNavigatorDebounced();
            return;
        }
        if (state.selected && !store.find(state.selected)) state.selected = null;
        if (state.view === 'element') (state.selected ? renderElementView() : showView('widgets'));
        if (state.view === 'history') renderHistoryView();
        renderNavigator();
    });

    $$('[data-view-btn]').forEach((b) => b.addEventListener('click', () => {
        if (b.dataset.viewBtn === 'element' && !state.selected) return;
        showView(b.dataset.viewBtn);
    }));
    $$('[data-device]').forEach((b) => b.addEventListener('click', () => setDevice(b.dataset.device)));
    $('[data-action="undo"]').addEventListener('click', undo);
    $('[data-action="redo"]').addEventListener('click', redo);
    $('[data-action="navigator"]').addEventListener('click', () => toggleNavigator());
    $('[data-action="close-navigator"]').addEventListener('click', () => toggleNavigator(false));
    $('[data-action="templates"]').addEventListener('click', openTemplates);
    $('[data-action="preview"]').addEventListener('click', preview);
    $('[data-action="shortcuts"]').addEventListener('click', showShortcuts);
    $('[data-action="save-draft"]').addEventListener('click', () => save('draft'));
    $('[data-action="save-review"]').addEventListener('click', () => save('review'));
    $('[data-action="save-schedule"]').addEventListener('click', () => {
        ui.metaForm.elements.status.value = 'scheduled';
        ui.metaForm.elements.status.dispatchEvent(new Event('change', { bubbles: true }));
        showView('page');
        ui.metaForm.elements.scheduled_for.focus();
        toast('Pick a date and time, then click Schedule.');
    });
    ui.publishBtn.addEventListener('click', () => save(metaValue('status') === 'scheduled' ? 'scheduled' : 'published'));

    const menuToggle = $('#ffbe-publish-more');
    const menu = $('#ffbe-publish-menu');
    menuToggle.addEventListener('click', () => {
        menu.hidden = !menu.hidden;
        menuToggle.setAttribute('aria-expanded', String(!menu.hidden));
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.ffbe-split')) {
            menu.hidden = true;
            menuToggle.setAttribute('aria-expanded', 'false');
        }
        if (contextMenu && !contextMenu.contains(event.target)) closeContextMenu();
    });
    menu.addEventListener('click', () => { menu.hidden = true; });

    document.addEventListener('keydown', onKeydown);
    // Clicking anywhere in the editor chrome finishes an in-place text edit,
    // even if the canvas never fired a blur event.
    document.addEventListener('mousedown', () => {
        if (!state.inline) return;
        state.inline.target.blur();
        endInline();
    }, true);
    window.addEventListener('beforeunload', (event) => {
        if (state.dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    bindMetaForm();
    bindNavigatorDrop();
    updateUndoButtons();
    updatePublishLabel();
    showView(config.openSettings ? 'page' : 'widgets');

    if (ui.frame.contentDocument?.readyState === 'complete' && ui.frame.contentDocument.getElementById('ffb-root')) bindCanvas();
    else ui.frame.addEventListener('load', bindCanvas, { once: true });

    offerRecovery();
}

const renderNavigatorDebounced = debounce(renderNavigator, 300);

boot();

