const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

const packages = path.resolve(__dirname, '../../..');

function loadControl(relative, dependencies = {}) {
    let definition;

    vm.runInNewContext(fs.readFileSync(path.join(packages, relative), 'utf8'), {
        Class: function(value) {
            return Object.assign(Object.create(value.Extends ?? null), value);
        },
        define: function(name, names, factory) {
            definition = factory(...names.map(dependency => dependencies[dependency] ?? {}));
        },
        JSON: {encode: JSON.stringify},
        typeOf: value => typeof value,
        Promise
    });

    return definition;
}

function createFixture({gross = false, value = '12,50'} = {}) {
    const validations = [];
    let resolveSave;
    const saved = new Promise(resolve => { resolveSave = resolve; });
    const Input = {name: 'shipping.defaultShippingPrice', type: 'hidden', value: '3'};
    const Netto = {name: 'netto-price', type: 'text', value: gross ? '3' : value, disabled: gross};
    const Brutto = {name: 'netto-price', type: 'text', value: gross ? value : '3,57', disabled: !gross};
    const Form = {elements: [Input, Netto, Brutto]};
    Input.form = Form;
    const Root = {contains: Field => Form.elements.includes(Field)};
    const Ajax = {
        get: (name, resolve) => {
            if (name.endsWith('validatePrice')) {
                validations.push(resolve);
            } else if (name.endsWith('calcNettoPrice')) {
                resolve(12.5);
            } else if (name.endsWith('calcBruttoPrice')) {
                resolve(14.875);
            } else {
                throw new Error('Unexpected request: ' + name);
            }
        },
        post: (name, resolve, options) => {
            assert.equal(name, 'ajax_settings_save');
            resolve();
            resolveSave(JSON.parse(options.params));
        }
    };
    const Locale = {
        getGroupingSeparator: () => '.',
        getDecimalSeparator: () => ','
    };
    const BasePrice = loadControl('erp/bin/backend/controls/elements/PriceCalcInput.js', {
        Ajax,
        Locale
    });
    let Control;
    let Panel;
    const QUI = {
        Controls: {
            getByType: () => [
                {getElm: () => ({contains: () => false})},
                Panel
            ],
            getControlsInElement: () => [Control]
        }
    };
    const Price = loadControl('shipping/bin/backend/controls/settings/DefaultShippingPrice.js', {
        'qui/QUI': QUI,
        'package/quiqqer/erp/bin/backend/controls/elements/PriceCalcInput': BasePrice
    });
    const XMLPanel = loadControl('core/bin/QUI/controls/desktop/panels/XML.js', {
        'qui/QUI': QUI,
        Ajax
    });
    Control = Object.assign(Object.create(Price), {
        $Input: Input,
        $loaded: true,
        $NettoContainer: {getElement: () => Netto},
        $BruttoContainer: {getElement: () => Brutto},
        $LoaderNetto: {setStyle() {}},
        $LoaderBrutto: {setStyle() {}},
        getElm: () => Root,
        isNetto: () => !gross,
        getAttribute: () => 19,
        getId: () => 42,
        fireEvent() {},
        getFormatter: () => Promise.resolve(new Intl.NumberFormat('de-DE', {minimumFractionDigits: 8}))
    });
    Panel = Object.assign(Object.create(XMLPanel), {
        $config: {shipping: {defaultShippingPrice: '3'}},
        $file: ['quiqqer/shipping/settings.xml'],
        $Control: null,
        Loader: {show() {}, hide() {}},
        getElm: () => ({contains: Element => Element === Root}),
        getBody: () => ({getElement: () => Form}),
        getButtonBar: () => ({getElement: () => ({setAttribute() {}})})
    });

    return {Control, Panel, Input, Netto, Brutto, validations, saved};
}

for (const gross of [false, true]) {
    test(`saving waits for the current ${gross ? 'gross' : 'net'} price`, async () => {
        const fixture = createFixture({gross, value: gross ? '14,875' : '12,50'});

        fixture.Panel.save();
        assert.equal(fixture.Panel.$config.shipping.defaultShippingPrice, '3');
        assert.equal(fixture.validations.length, 1);
        fixture.validations[0](gross ? 14.875 : 12.5);

        const configuration = await fixture.saved;
        const price = Number(String(configuration.shipping.defaultShippingPrice).replace(',', '.'));
        assert.equal(price, 12.5);
        assert.equal(fixture.Netto.value, '12,50000000');
    });
}

test('blur corrects the visible price before a subsequent save', async () => {
    const fixture = createFixture();

    fixture.Control.$onChange();
    assert.equal(fixture.validations.length, 1);
    fixture.validations[0](12.5);
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(fixture.Netto.value, '12,50000000');

    fixture.Panel.save();
    assert.equal(fixture.validations.length, 2);
    fixture.validations[1](12.5);
    const configuration = await fixture.saved;
    assert.equal(Number(configuration.shipping.defaultShippingPrice), 12.5);
});

test('clearing the price saves an empty value rather than the previous amount', async () => {
    const fixture = createFixture({value: ''});

    fixture.Panel.save();

    const configuration = await fixture.saved;
    assert.equal(configuration.shipping.defaultShippingPrice, '');
    assert.equal(fixture.validations.length, 0);
});
