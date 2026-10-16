<?php

namespace QUI\ERP\Shipping;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use QUI\Utils\XML\Settings;

class SettingsTest extends TestCase
{
    public function testDefaultShippingPriceRendersAsASaveAwarePriceControl(): void
    {
        $Settings = new Settings();
        $Settings->setXMLPath('//quiqqer/settings/window[@name="ERP"]');
        $html = $Settings->getCategoriesHtml(dirname(__DIR__, 5) . '/settings.xml', 'shipping');
        $Document = new DOMDocument();
        $Document->loadHTML('<!DOCTYPE html><html><body><form>' . $html . '</form></body></html>');
        $XPath = new DOMXPath($Document);
        $inputs = $XPath->query('//input[@name="shipping.defaultShippingPrice"]');

        self::assertNotFalse($inputs);
        self::assertSame(1, $inputs->length);
        $Input = $inputs->item(0);
        self::assertInstanceOf(DOMElement::class, $Input);
        self::assertSame(
            'package/quiqqer/shipping/bin/backend/controls/settings/DefaultShippingPrice',
            $Input->getAttribute('data-qui')
        );
        self::assertSame('text', $Input->getAttribute('type'));
        self::assertNotSame('', trim($Input->parentNode->textContent));
    }
}
