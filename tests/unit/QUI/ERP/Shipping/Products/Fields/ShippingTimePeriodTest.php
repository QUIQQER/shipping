<?php

namespace QUI\ERP\Shipping\Products\Fields;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Products\Field\Exception;

class ShippingTimePeriodTest extends TestCase
{
    public function testCleanupNormalizesSupportedValues(): void
    {
        $Field = new ShippingTimePeriod(91001);

        self::assertSame(
            [
                'from' => 2,
                'to' => 5,
                'unit' => 'day',
                'option' => ShippingTimePeriod::OPTION_TIMEPERIOD
            ],
            $Field->cleanup('{"from":"2","to":"5","unit":"day","option":"timeperiod"}')
        );

        $custom = $Field->cleanup([
            'from' => '0',
            'to' => '0',
            'unit' => 'day',
            'option' => ShippingTimePeriod::OPTION_CUSTOM_TEXT
        ]);

        self::assertSame(0, $custom['from']);
        self::assertSame(0, $custom['to']);
        self::assertIsArray($custom['text']);
        self::assertNotEmpty($custom['text']);
    }

    public function testCleanupRejectsInvalidInputWithoutChangingConfiguration(): void
    {
        $Field = new ShippingTimePeriod(91002);
        $default = $Field->getDefaultValue();

        self::assertSame($default, $Field->cleanup(null));
        self::assertSame($default, $Field->cleanup(new \stdClass()));
        self::assertSame($default, $Field->cleanup('{invalid json'));
        self::assertSame($default, $Field->cleanup([
            'from' => 1,
            'to' => 2,
            'unit' => 'day',
            'option' => 'unsupported'
        ]));
    }

    public function testValidationAndMetadata(): void
    {
        $Field = new ShippingTimePeriod(91003);
        $value = [
            'from' => 1,
            'to' => 2,
            'unit' => 'day',
            'option' => ShippingTimePeriod::OPTION_TIMEPERIOD
        ];

        $Field->validate(null);
        $Field->validate($value);
        $Field->validate(json_encode($value, JSON_THROW_ON_ERROR));

        self::assertSame(
            'package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod',
            $Field->getJavaScriptControl()
        );
        self::assertInstanceOf(ShippingTimeFrontendView::class, $Field->getFrontendView());
    }

    public function testFrontendViewReturnsEmptyOutputForEmptyValue(): void
    {
        $View = new ShippingTimeFrontendView([
            'id' => 91007,
            'title' => 'Delivery time',
            'value' => [],
            'isPublic' => true
        ]);

        self::assertSame('', $View->create());
    }

    public function testFrontendViewWithoutPublicPermissionRendersNothing(): void
    {
        $View = new ShippingTimeFrontendView([
            'id' => 91008,
            'title' => 'Private delivery time',
            'value' => [
                'option' => ShippingTimePeriod::OPTION_UNAVAILABLE,
                'from' => 0,
                'to' => 0,
                'unit' => 'day'
            ],
            'isPublic' => false
        ]);

        self::assertSame('', $View->create());
    }

    public function testEmptyConfiguredDefaultValueReturnsNull(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->get('shipping', 'deliveryTimeDefault');

        try {
            $Config->set('shipping', 'deliveryTimeDefault', '');
            $Config->save();
            self::assertNull((new ShippingTimePeriod(91009))->getDefaultValue());
        } finally {
            $Config->set('shipping', 'deliveryTimeDefault', $previous);
            $Config->save();
        }
    }

    public function testValidationRequiresShippingOption(): void
    {
        $this->expectException(Exception::class);

        (new ShippingTimePeriod(91004))->validate([
            'from' => 1,
            'to' => 2,
            'unit' => 'day'
        ]);
    }

    public function testFrontendViewResolvesConfiguredDefaultValue(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->get('shipping', 'deliveryTimeDefault');
        $default = [
            'option' => ShippingTimePeriod::OPTION_TIMEPERIOD,
            'from' => 2,
            'to' => 4,
            'unit' => 'day'
        ];

        try {
            $Config->set('shipping', 'deliveryTimeDefault', json_encode($default, JSON_THROW_ON_ERROR));
            $Config->save();

            $View = new ShippingTimeFrontendView([
                'id' => 91006,
                'title' => 'Delivery time',
                'value' => ['option' => ShippingTimePeriod::OPTION_USE_DEFAULT],
                'isPublic' => true
            ]);

            self::assertSame($default, $View->getValue());
            self::assertStringContainsString('shipping-info', $View->create());
        } finally {
            if ($previous === null) {
                $Config->del('shipping', 'deliveryTimeDefault');
            } else {
                $Config->set('shipping', 'deliveryTimeDefault', $previous);
            }

            $Config->save();
        }
    }

    #[DataProvider('frontendValues')]
    public function testFrontendViewRendersEverySupportedDisplayVariant(array $value): void
    {
        $View = new ShippingTimeFrontendView([
            'id' => 91005,
            'title' => 'Delivery time',
            'value' => $value,
            'isPublic' => true
        ]);

        $html = $View->create();

        self::assertNotSame('', $html);
        self::assertStringContainsString('shipping-info', $html);
        self::assertStringContainsString('product-data-fields-value', $html);
    }

    public static function frontendValues(): array
    {
        return [
            'unavailable' => [[
                'option' => ShippingTimePeriod::OPTION_UNAVAILABLE,
                'from' => 0,
                'to' => 0,
                'unit' => 'day'
            ]],
            'on request' => [[
                'option' => ShippingTimePeriod::OPTION_ON_REQUEST,
                'from' => 0,
                'to' => 0,
                'unit' => 'day'
            ]],
            'available soon' => [[
                'option' => ShippingTimePeriod::OPTION_AVAILABLE_SOON,
                'from' => 0,
                'to' => 0,
                'unit' => 'day'
            ]],
            'custom' => [[
                'option' => ShippingTimePeriod::OPTION_CUSTOM_TEXT,
                'from' => 0,
                'to' => 0,
                'unit' => 'day',
                'text' => ['de' => 'Individuell', 'en' => 'Custom']
            ]],
            'custom fallback language' => [[
                'option' => ShippingTimePeriod::OPTION_CUSTOM_TEXT,
                'from' => 0,
                'to' => 0,
                'unit' => 'day',
                'text' => ['xx' => 'Fallback']
            ]],
            'single period' => [[
                'option' => ShippingTimePeriod::OPTION_TIMEPERIOD,
                'from' => 2,
                'to' => 2,
                'unit' => 'day'
            ]],
            'empty period is unavailable' => [[
                'option' => ShippingTimePeriod::OPTION_TIMEPERIOD,
                'from' => 0,
                'to' => 0,
                'unit' => 'day'
            ]],
            'until period' => [[
                'option' => ShippingTimePeriod::OPTION_TIMEPERIOD,
                'from' => 0,
                'to' => 3,
                'unit' => 'day'
            ]],
            'from period' => [[
                'option' => ShippingTimePeriod::OPTION_TIMEPERIOD,
                'from' => 3,
                'to' => 0,
                'unit' => 'day'
            ]],
            'range' => [[
                'option' => ShippingTimePeriod::OPTION_TIMEPERIOD,
                'from' => 2,
                'to' => 4,
                'unit' => 'day'
            ]]
        ];
    }
}
