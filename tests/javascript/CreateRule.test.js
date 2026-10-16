const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

const controlDirectory = path.resolve(__dirname, '../../bin/backend/controls/shippingRules');

function loadControl(file, dependencies) {
    let definition;

    vm.runInNewContext(fs.readFileSync(path.join(controlDirectory, file), 'utf8'), {
        Class: function(value) {
            return value;
        },
        define: function(name, names, factory) {
            definition = factory(...names.map(dependency => dependencies[dependency] ?? {}));
        },
        Promise
    });

    return definition;
}

function createFixture(values = ['12,50', '25,00', '119,00'], gross = false) {
    const requests = [];
    const pending = [];
    const fields = values.map((value, index) => {
        const Input = {
            name: ['discount', 'purchase_value_from', 'purchase_value_until'][index],
            type: 'hidden',
            value: '0',
            getAttribute: () => index
        };
        const Editor = {type: 'text', disabled: false, value};
        const Price = {
            getElm: () => ({contains: Field => Field === Input || Field === Editor}),
            isNetto: () => !gross,
            setNettoByUser: price => {
                assert.equal(gross, false);
                assert.equal(price, value);
                return new Promise((resolve, reject) => {
                    pending.push({
                        reject,
                        resolve: () => {
                            Input.value = Number(price.replace(',', '.'));
                            resolve();
                        }
                    });
                });
            },
            setBruttoByUser: price => {
                assert.equal(gross, true);
                assert.equal(price, value);
                return new Promise((resolve, reject) => {
                    pending.push({
                        reject,
                        resolve: () => {
                            Input.value = Number(price.replace(',', '.')) / 1.19;
                            resolve();
                        }
                    });
                });
            }
        };

        return {Input, Editor, Price};
    });
    const Form = {
        elements: fields.flatMap(({Input, Editor}) => [Input, Editor]),
        querySelectorAll: () => fields.map(({Input}) => Input)
    };
    const titles = {de: 'Versandregel', en: 'Shipping rule'};
    const workingTitles = {de: 'Interner Name', en: 'Internal name'};
    const createNames = names => {
        let data = {};

        return {
            refreshData: () => { data = names; },
            getData: () => data
        };
    };
    const definition = loadControl('CreateRule.js', {
        'qui/QUI': {Controls: {getById: index => fields[index].Price}},
        'qui/utils/Form': {
            getFormData: () => Object.fromEntries(fields.map(({Input}) => [Input.name, Input.value]))
        },
        'package/quiqqer/shipping/bin/backend/ShippingRules': {
            create: data => {
                requests.push(data);
                return Promise.resolve(42);
            }
        }
    });
    const Control = Object.assign(Object.create(definition), {
        $DataTitle: createNames(titles),
        $DataWorkingTitle: createNames(workingTitles),
        getElm: () => ({
            querySelector: () => Form,
            getElement: () => Form,
            getElements: () => []
        })
    });

    return {Control, fields, pending, requests, titles, workingTitles};
}

for (const gross of [false, true]) {
    test(`creation waits for all ${gross ? 'gross' : 'net'} prices and reads current names`, async () => {
        const fixture = createFixture(undefined, gross);
        const submission = fixture.Control.submit();

        assert.equal(fixture.requests.length, 0);
        assert.equal(fixture.pending.length, 3);
        fixture.pending[0].resolve();
        await Promise.resolve();
        assert.equal(fixture.requests.length, 0);
        fixture.pending.slice(1).forEach(request => request.resolve());
        assert.equal(await submission, 42);

        const data = fixture.requests[0];
        const divisor = gross ? 1.19 : 1;
        assert.equal(data.discount, 12.5 / divisor);
        assert.equal(data.purchase_value_from, 25 / divisor);
        assert.equal(data.purchase_value_until, 119 / divisor);
        assert.deepEqual(data.title, fixture.titles);
        assert.deepEqual(data.workingTitle, fixture.workingTitles);
    });
}

test('empty prices clear stale values without requesting a conversion', async () => {
    const fixture = createFixture(['', '', '']);

    await fixture.Control.submit();

    assert.equal(fixture.pending.length, 0);
    assert.equal(fixture.requests[0].discount, '');
    assert.equal(fixture.requests[0].purchase_value_from, '');
    assert.equal(fixture.requests[0].purchase_value_until, '');
});

test('a failed price conversion prevents creation', async () => {
    const fixture = createFixture(['12,50']);
    const submission = fixture.Control.submit();

    fixture.pending[0].reject(new Error('Conversion failed'));

    await assert.rejects(submission, /Conversion failed/);
    assert.equal(fixture.requests.length, 0);
});

test('the create button uses a plus icon', () => {
    const definition = loadControl('CreateRuleWindow.js', {Locale: {get: () => 'Create'}});
    let attributes;

    definition.initialize.call({
        parent() {},
        setAttributes: value => { attributes = value; },
        addEvents() {}
    });

    assert.equal(attributes.ok_button.textimage, 'fa fa-plus');
});

test('the create window prevents duplicate submissions while prices are pending', async () => {
    const definition = loadControl('CreateRuleWindow.js', {});
    const events = [];
    let finish;
    const pending = new Promise(resolve => { finish = resolve; });
    const Window = {
        $submitting: false,
        $Create: {
            submit: () => {
                events.push('submit');
                return pending;
            }
        },
        Loader: {
            show: () => events.push('show'),
            hide: () => events.push('hide')
        },
        close: () => events.push('close')
    };
    const submission = definition.$onSubmit.call(Window);

    definition.$onSubmit.call(Window);
    assert.deepEqual(events, ['show', 'submit']);
    finish();
    await submission;
    assert.deepEqual(events, ['show', 'submit', 'hide', 'close']);
    assert.equal(Window.$submitting, false);
});

test('the create window remains open and restores its loader after a failure', async () => {
    const definition = loadControl('CreateRuleWindow.js', {});
    const events = [];
    const Window = {
        $submitting: false,
        $Create: {submit: () => Promise.reject(new Error('Creation failed'))},
        Loader: {
            show: () => events.push('show'),
            hide: () => events.push('hide')
        },
        close: () => events.push('close')
    };

    await assert.rejects(definition.$onSubmit.call(Window), /Creation failed/);
    assert.deepEqual(events, ['show', 'hide']);
    assert.equal(Window.$submitting, false);
});
