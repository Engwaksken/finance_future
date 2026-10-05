import test from 'node:test';
import assert from 'node:assert/strict';

test('password buttons toggle independently without changing values or submitting forms', async () => {
    const inputs = new Map([
        ['password', { type: 'password', value: 'my-password' }],
        ['password_confirmation', { type: 'password', value: 'my-password' }],
    ]);
    const buttons = [...inputs.keys()].map((id) => ({
        hidden: true,
        dataset: { passwordToggle: id, showLabel: 'Show password', hideLabel: 'Hide password' },
        attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(event, callback) { this.click = callback; },
    }));

    globalThis.document = {
        querySelectorAll: () => buttons,
        getElementById: (id) => inputs.get(id),
    };

    try {
        await import('../resources/js/password-toggle.js');
        assert.equal(buttons[0].hidden, false);
        buttons[0].click();
        assert.equal(inputs.get('password').type, 'text');
        assert.equal(inputs.get('password_confirmation').type, 'password');
        assert.equal(buttons[0].attributes['aria-label'], 'Hide password');
        assert.equal(buttons[0].attributes['aria-pressed'], 'true');
        buttons[0].click();
        assert.equal(inputs.get('password').type, 'password');
        assert.equal(inputs.get('password').value, 'my-password');
        assert.equal(buttons[0].attributes['aria-label'], 'Show password');
        assert.equal(buttons[0].attributes['aria-pressed'], 'false');
    } finally {
        delete globalThis.document;
    }
});
