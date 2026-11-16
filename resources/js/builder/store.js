import { clone, uid } from './util';

/**
 * PHP encodes empty settings maps as [] (a JS array). Properties set on an
 * array are dropped by JSON.stringify, so coerce every settings value to a
 * plain object whenever a layout enters the store.
 */
export function coerceLayout(layout) {
    const fix = (node) => {
        if (!node.settings || Array.isArray(node.settings) || typeof node.settings !== 'object') node.settings = {};
    };
    layout.sections = Array.isArray(layout.sections) ? layout.sections : [];
    layout.sections.forEach((section) => {
        fix(section);
        section.columns = Array.isArray(section.columns) ? section.columns : [];
        section.columns.forEach((column) => {
            fix(column);
            column.widgets = Array.isArray(column.widgets) ? column.widgets : [];
            column.widgets.forEach(fix);
        });
    });
    return layout;
}

/**
 * Layout document model + undo/redo history.
 *
 * layout = { version, sections: [ { id, settings, columns: [ { id, settings, widgets: [ { id, type, settings } ] } ] } ] }
 */
export class Store {
    constructor(schema, layout) {
        this.schema = schema;
        this.layout = coerceLayout(layout && Array.isArray(layout.sections) ? clone(layout) : { version: 1, sections: [] });
        this.undoStack = [];
        this.redoStack = [];
        this.listeners = new Set();
        this.lastCoalesce = null;
    }

    subscribe(fn) {
        this.listeners.add(fn);
        return () => this.listeners.delete(fn);
    }

    emit(reason) {
        this.listeners.forEach((fn) => fn(reason));
    }

    // ---------------------------------------------------------------- lookup
    find(id) {
        for (const [si, section] of this.layout.sections.entries()) {
            if (section.id === id) return { kind: 'section', node: section, list: this.layout.sections, index: si, section };
            for (const [ci, column] of section.columns.entries()) {
                if (column.id === id) return { kind: 'column', node: column, list: section.columns, index: ci, section, column };
                for (const [wi, widget] of column.widgets.entries()) {
                    if (widget.id === id) return { kind: 'widget', node: widget, list: column.widgets, index: wi, section, column, widget };
                }
            }
        }
        return null;
    }

    controlsFor(kind, type) {
        if (kind === 'section') return this.schema.section;
        if (kind === 'column') return this.schema.column;
        return this.schema.widgets[type]?.controls ?? [];
    }

    title(found) {
        if (!found) return '';
        if (found.kind === 'widget') return this.schema.widgets[found.node.type]?.title ?? found.node.type;
        return found.kind === 'section' ? 'Section' : 'Column';
    }

    // ---------------------------------------------------------------- factories
    defaults(controls) {
        const settings = {};
        for (const control of controls) {
            if (control.type === 'heading' || control.default === undefined || control.default === null) continue;
            settings[control.name] = control.type === 'repeater'
                ? clone(control.default).map((item) => ({ ...item, _id: uid() }))
                : clone(control.default);
        }
        return settings;
    }

    makeWidget(type, settings = null) {
        return { id: uid(), type, settings: settings ? clone(settings) : this.defaults(this.controlsFor('widget', type)) };
    }

    makeColumn(width = null, widgets = []) {
        const settings = width ? { width: { desktop: width } } : {};
        return { id: uid(), settings, widgets };
    }

    makeSection(structure = [100], settings = {}) {
        const equal = structure.every((w) => Math.abs(w - structure[0]) < 0.5);
        return {
            id: uid(),
            settings: clone(settings),
            columns: structure.map((w) => this.makeColumn(equal ? null : w)),
        };
    }

    /** Deep copy with fresh ids so a node can be inserted again. */
    reId(node) {
        const copy = clone(node);
        if (copy.columns) coerceLayout({ sections: [copy] });
        else if (copy.widgets) coerceLayout({ sections: [{ columns: [copy] }] });
        else if (!copy.settings || Array.isArray(copy.settings)) copy.settings = {};
        const walk = (n) => {
            if (!n || typeof n !== 'object') return;
            if (Array.isArray(n)) return n.forEach(walk);
            if (typeof n.id === 'string') n.id = uid();
            if (typeof n._id === 'string') n._id = uid();
            Object.values(n).forEach(walk);
        };
        walk(copy);
        return copy;
    }

    // ---------------------------------------------------------------- history
    snapshot() {
        return JSON.stringify(this.layout);
    }

    /**
     * Run a mutation with undo support. `coalesceKey` merges rapid edits of the
     * same control (e.g. typing) into a single undo step.
     */
    mutate(label, fn, { coalesceKey = null, reason = 'change' } = {}) {
        const now = Date.now();
        const coalesce = coalesceKey && this.lastCoalesce && this.lastCoalesce.key === coalesceKey && now - this.lastCoalesce.at < 1200;
        if (!coalesce) {
            this.undoStack.push({ label, state: this.snapshot() });
            if (this.undoStack.length > 100) this.undoStack.shift();
        }
        this.lastCoalesce = coalesceKey ? { key: coalesceKey, at: now } : null;
        this.redoStack = [];
        const result = fn(this.layout);
        this.emit(reason);
        return result;
    }

    undo() {
        const entry = this.undoStack.pop();
        if (!entry) return null;
        this.redoStack.push({ label: entry.label, state: this.snapshot() });
        this.layout = JSON.parse(entry.state);
        this.lastCoalesce = null;
        this.emit('history');
        return entry.label;
    }

    redo() {
        const entry = this.redoStack.pop();
        if (!entry) return null;
        this.undoStack.push({ label: entry.label, state: this.snapshot() });
        this.layout = JSON.parse(entry.state);
        this.lastCoalesce = null;
        this.emit('history');
        return entry.label;
    }

    /** Jump to a point in the undo stack (History panel). */
    jumpTo(index) {
        // index refers to undoStack position; restore the state saved *before* that action.
        while (this.undoStack.length > index) this.undo();
    }

    replace(layout, label = 'Replaced content') {
        this.mutate(label, () => {
            this.layout = coerceLayout(clone(layout));
        }, { reason: 'structure' });
    }

    // ---------------------------------------------------------------- settings
    getSetting(id, name) {
        return this.find(id)?.node.settings?.[name];
    }

    setSetting(id, name, value, label) {
        const found = this.find(id);
        if (!found) return;
        this.mutate(label || `Edited ${this.title(found)}`, () => {
            const settings = found.node.settings || (found.node.settings = {});
            if (value === undefined || value === null || (typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length === 0)) {
                delete settings[name];
            } else {
                settings[name] = value;
            }
        }, { coalesceKey: `${id}:${name}` });
    }

    // ---------------------------------------------------------------- structure ops
    insertSection(section, index = this.layout.sections.length) {
        this.mutate('Added section', (layout) => layout.sections.splice(index, 0, section), { reason: 'structure' });
        return section.id;
    }

    addWidget(type, target) {
        const widget = this.makeWidget(type);
        this.insertWidget(widget, target, `Added ${this.schema.widgets[type]?.title ?? type}`);
        return widget.id;
    }

    /**
     * target: { columnId, index } | { sectionIndex } (creates a new section)
     */
    insertWidget(widget, target, label = 'Added widget') {
        this.mutate(label, (layout) => {
            if (target.columnId) {
                const column = this.find(target.columnId)?.node;
                if (column) {
                    column.widgets.splice(target.index ?? column.widgets.length, 0, widget);
                    return;
                }
            }
            const section = this.makeSection([100]);
            section.columns[0].widgets.push(widget);
            layout.sections.splice(target.sectionIndex ?? layout.sections.length, 0, section);
        }, { reason: 'structure' });
    }

    moveWidget(id, target) {
        const from = this.find(id);
        if (!from || from.kind !== 'widget') return;
        this.mutate('Moved widget', (layout) => {
            const [widget] = from.list.splice(from.index, 1);
            if (target.columnId) {
                const column = this.find(target.columnId)?.node;
                let index = target.index ?? column.widgets.length;
                if (column === from.column && from.index < index) index--;
                column.widgets.splice(index, 0, widget);
            } else {
                const section = this.makeSection([100]);
                section.columns[0].widgets.push(widget);
                layout.sections.splice(target.sectionIndex ?? layout.sections.length, 0, section);
            }
        }, { reason: 'structure' });
    }

    moveSection(id, toIndex) {
        const from = this.find(id);
        if (!from || from.kind !== 'section') return;
        this.mutate('Moved section', (layout) => {
            const [section] = layout.sections.splice(from.index, 1);
            layout.sections.splice(from.index < toIndex ? toIndex - 1 : toIndex, 0, section);
        }, { reason: 'structure' });
    }

    moveColumn(id, toIndex) {
        const from = this.find(id);
        if (!from || from.kind !== 'column') return;
        this.mutate('Moved column', () => {
            const [column] = from.list.splice(from.index, 1);
            from.list.splice(from.index < toIndex ? toIndex - 1 : toIndex, 0, column);
        }, { reason: 'structure' });
    }

    nudge(id, delta) {
        const found = this.find(id);
        if (!found) return;
        const to = found.index + delta;
        if (to < 0 || to >= found.list.length) return;
        this.mutate(`Moved ${this.title(found)}`, () => {
            const [node] = found.list.splice(found.index, 1);
            found.list.splice(to, 0, node);
        }, { reason: 'structure' });
    }

    duplicate(id) {
        const found = this.find(id);
        if (!found) return null;
        const copy = this.reId(found.node);
        this.mutate(`Duplicated ${this.title(found)}`, () => found.list.splice(found.index + 1, 0, copy), { reason: 'structure' });
        return copy.id;
    }

    remove(id) {
        const found = this.find(id);
        if (!found) return;
        this.mutate(`Deleted ${this.title(found)}`, () => {
            found.list.splice(found.index, 1);
            // A section always keeps at least one column.
            if (found.kind === 'column' && found.list.length === 0) found.list.push(this.makeColumn());
        }, { reason: 'structure' });
    }

    addColumn(sectionId, afterIndex = null) {
        const found = this.find(sectionId);
        if (!found || found.node.columns.length >= 6) return null;
        const column = this.makeColumn();
        this.mutate('Added column', () => {
            found.node.columns.splice(afterIndex === null ? found.node.columns.length : afterIndex + 1, 0, column);
            // Reset desktop widths so the new column shares the row equally.
            found.node.columns.forEach((c) => {
                if (c.settings?.width) delete c.settings.width.desktop;
                if (c.settings?.width && !Object.keys(c.settings.width).length) delete c.settings.width;
            });
        }, { reason: 'structure' });
        return column.id;
    }

    setStructure(sectionId, structure) {
        const found = this.find(sectionId);
        if (!found) return;
        this.mutate('Changed structure', () => {
            const columns = found.node.columns;
            const equal = structure.every((w) => Math.abs(w - structure[0]) < 0.5);
            while (columns.length < structure.length) columns.push(this.makeColumn());
            if (columns.length > structure.length) {
                const extra = columns.splice(structure.length);
                extra.forEach((c) => columns[columns.length - 1].widgets.push(...c.widgets));
            }
            columns.forEach((column, i) => {
                column.settings ||= {};
                if (equal) {
                    if (column.settings.width) delete column.settings.width.desktop;
                } else {
                    column.settings.width = { ...(column.settings.width || {}), desktop: structure[i] };
                }
                if (column.settings.width && !Object.keys(column.settings.width).length) delete column.settings.width;
            });
        }, { reason: 'structure' });
    }

    /** Copy the non-content (style/advanced) settings from one element to another. */
    pasteStyle(id, sourceSettings, sourceKind, sourceType) {
        const found = this.find(id);
        if (!found) return false;
        const sameType = found.kind === sourceKind && (found.kind !== 'widget' || found.node.type === sourceType);
        const controls = this.controlsFor(found.kind, found.node.type);
        const styleNames = new Set(controls.filter((c) => c.tab !== 'content' && (sameType || c.name.startsWith('_'))).map((c) => c.name));
        this.mutate(`Pasted style on ${this.title(found)}`, () => {
            const settings = found.node.settings || (found.node.settings = {});
            for (const name of styleNames) {
                if (name in sourceSettings) settings[name] = clone(sourceSettings[name]);
                else delete settings[name];
            }
        });
        return true;
    }

    resetStyle(id) {
        const found = this.find(id);
        if (!found) return;
        const controls = this.controlsFor(found.kind, found.node.type);
        this.mutate(`Reset style of ${this.title(found)}`, () => {
            const defaults = this.defaults(controls);
            controls.filter((c) => c.tab !== 'content').forEach((c) => {
                if (c.name in defaults) found.node.settings[c.name] = defaults[c.name];
                else delete found.node.settings[c.name];
            });
        });
    }
}
