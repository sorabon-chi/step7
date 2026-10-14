const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const product_script = fs.readFileSync(path.resolve(__dirname, '../../resources/js/products.js'), 'utf8');

function createDeleteForm(product_name) {
    const listeners = {};
    const delete_button = { disabled: true };
    const delete_form = {
        dataset: { productName: product_name },
        addEventListener(event_name, listener) {
            assert.equal(event_name, 'submit');
            listeners[event_name] = listener;
        },
        querySelector(selector) {
            assert.equal(selector, 'button[type="submit"]');
            assert.equal(typeof listeners.submit, 'function');
            return delete_button;
        },
    };

    return { delete_form, delete_button, listeners };
}

function initializeForms(form_entries, confirmed) {
    const confirmation_messages = [];
    vm.runInNewContext(product_script, {
        document: {
            querySelectorAll(selector) {
                assert.equal(selector, '.product-list__delete-form');
                return form_entries.map((entry) => entry.delete_form);
            },
        },
        window: {
            confirm(message) {
                confirmation_messages.push(message);
                return confirmed;
            },
        },
    });

    return confirmation_messages;
}

test('cancel stops the form submission', () => {
    const form_entry = createDeleteForm('コーラ');
    const messages = initializeForms([form_entry], false);
    let default_prevented = false;

    assert.equal(form_entry.delete_button.disabled, false);
    form_entry.listeners.submit({
        preventDefault() {
            default_prevented = true;
        },
    });

    assert.equal(default_prevented, true);
    assert.deepEqual(messages, ['「コーラ」を削除しますか？']);
});

test('accept permits the form submission', () => {
    const form_entry = createDeleteForm('コーラ');
    const messages = initializeForms([form_entry], true);
    let default_prevented = false;

    assert.equal(form_entry.delete_button.disabled, false);
    form_entry.listeners.submit({
        preventDefault() {
            default_prevented = true;
        },
    });

    assert.equal(default_prevented, false);
    assert.deepEqual(messages, ['「コーラ」を削除しますか？']);
});

test('each row confirms its own product', () => {
    const first_form = createDeleteForm('コーラ');
    const second_form = createDeleteForm('お茶');
    const messages = initializeForms([first_form, second_form], true);

    first_form.listeners.submit({
        preventDefault() {
            assert.fail('Unexpected cancellation');
        },
    });
    second_form.listeners.submit({
        preventDefault() {
            assert.fail('Unexpected cancellation');
        },
    });

    assert.equal(first_form.delete_button.disabled, false);
    assert.equal(second_form.delete_button.disabled, false);
    assert.deepEqual(messages, ['「コーラ」を削除しますか？', '「お茶」を削除しますか？']);
});

test('pages without delete forms do not open a dialog', () => {
    assert.deepEqual(initializeForms([], true), []);
});
