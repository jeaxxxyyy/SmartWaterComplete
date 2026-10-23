import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const section = (start, end) => {
    const from = source.indexOf(`const ${start} =`);
    const to = source.indexOf(`const ${end} =`, from);
    assert.ok(from >= 0 && to > from, `Could not locate ${start}`);
    return source.slice(from, to);
};

const profileHarness = () => {
    const form = {
        dataset: {},
        elements: { email: { value: '' }, phone: { value: '' } },
        contains: () => false,
    };
    const context = vm.createContext({
        accountForm: form,
        accountFormInitial: {},
        accountSubmitButton: { disabled: true },
        document: { activeElement: null },
        setText() {}, setFieldError() {},
        roleLabelForUser: () => 'Household',
        formatDateTime: (value) => value,
    });
    vm.runInContext(`${section('validateEmail', 'updatePasswordCriteria')}
        ${section('renderProfile', 'setAccountProfileState')}
        globalThis.render = renderProfile;
        globalThis.update = updateAccountFormState;`, context);
    return { form, context };
};

test('profile refresh preserves unsaved contact changes after the input loses focus', () => {
    const { form, context } = profileHarness();
    const user = { email: 'old@example.com', phone: '09170000000' };
    context.render(user);
    form.elements.email.value = 'new@example.com';
    context.update();
    context.render(user);

    assert.equal(form.elements.email.value, 'new@example.com');
    assert.equal(form.dataset.dirty, '1');
    assert.equal(context.accountSubmitButton.disabled, false);
});

test('a successful contact save updates the baseline and disables saving until another change', () => {
    const { form, context } = profileHarness();
    context.render({ email: 'old@example.com', phone: '' });
    form.elements.email.value = 'new@example.com';
    context.update();
    form.dataset.dirty = '0';
    context.render({ email: 'new@example.com', phone: '' });

    assert.equal(form.elements.email.value, 'new@example.com');
    assert.equal(form.dataset.dirty, '0');
    assert.equal(context.accountSubmitButton.disabled, true);
});

const settingsHarness = (requestJson = async () => ({})) => {
    const handlers = new Map();
    const form = {
        dataset: {}, values: { water_rate: '40' },
        addEventListener(name, handler) { handlers.set(name, handler); },
    };
    const context = vm.createContext({
        $: () => null, $$: () => [],
        FormData: class {
            constructor(form) { this.values = form.values; }
            entries() { return Object.entries(this.values); }
        },
        requestJson,
        loadAdmin: async () => {},
        showToast() {}, handleAuthFailure: () => false, errorMessage: () => 'Save failed',
    });
    vm.runInContext(`${section('bindAdminSettingsForm', 'setBillingManagementState')}
        globalThis.bind = bindAdminSettingsForm;`, context);
    context.bind(form, '/api/billing-settings/rates', '[data-status]', 'Saved');
    return { form, handlers };
};

test('settings remain marked as unsaved when the server rejects a save', async () => {
    const { form, handlers } = settingsHarness(async () => { throw new Error('Validation failed'); });
    handlers.get('input')();
    await handlers.get('submit')({ preventDefault() {} });

    assert.equal(form.dataset.dirty, '1');
    assert.equal(form.dataset.saving, '0');
});

test('successful settings saves allow the saved form to refresh', async () => {
    const { form, handlers } = settingsHarness();
    handlers.get('change')();
    await handlers.get('submit')({ preventDefault() {} });

    assert.equal(form.dataset.dirty, '0');
    assert.equal(form.dataset.saving, '0');
});

test('edits made while a save is pending remain unsaved after that response', async () => {
    let finish;
    const { form, handlers } = settingsHarness(() => new Promise((resolve) => { finish = resolve; }));
    handlers.get('input')();
    const saving = handlers.get('submit')({ preventDefault() {} });
    form.values.water_rate = '50';
    handlers.get('input')();
    finish({});
    await saving;

    assert.equal(form.dataset.dirty, '1');
    assert.equal(form.values.water_rate, '50');
});

test('the service request dialog can be closed repeatedly without duplicate handlers', () => {
    const closeButton = new EventTarget();
    closeButton.dataset = {};
    let closed = 0;
    const dialog = { close() { closed++; } };
    const context = vm.createContext({
        $: (selector) => ({
            '[data-admin-service-request-close]': closeButton,
            '[data-admin-service-request-dialog]': dialog,
        })[selector] ?? null,
    });
    vm.runInContext(`${section('bindAdminServiceRequests', 'bindAdminSettingsForm')}
        globalThis.bind = bindAdminServiceRequests;`, context);
    context.bind();
    context.bind();
    closeButton.dispatchEvent(new Event('click'));
    closeButton.dispatchEvent(new Event('click'));

    assert.equal(closed, 2);
});
