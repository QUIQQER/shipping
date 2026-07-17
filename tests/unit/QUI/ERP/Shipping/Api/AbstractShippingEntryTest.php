<?php

namespace QUI\ERP\Shipping\Api;

use PHPUnit\Framework\TestCase;
use QUI;

require_once __DIR__ . '/TestShippingEntry.php';

class AbstractShippingEntryTest extends TestCase
{
    public function testBaseEntryProvidesStableApiDefaults(): void
    {
        $Entry = new TestShippingEntry();
        $Locale = new QUI\Locale();
        self::assertSame(QUI::getLocale(), $Entry->getLocale());
        $Entry->setLocale($Locale);

        self::assertSame($Locale, $Entry->getLocale());
        self::assertSame(TestShippingEntry::class, $Entry->getClass());
        self::assertSame(md5(TestShippingEntry::class), $Entry->getName());
        self::assertStringEndsWith(
            'quiqqer/shipping/bin/images/shipping/default.png',
            $Entry->getIcon()
        );
        self::assertSame(
            ['name' => $Entry->getName(), 'title' => 'Test shipping', 'description' => 'Test description'],
            $Entry->toArray()
        );
        self::assertTrue($Entry->isVisible());
    }

    public function testInvoiceInformationDefaultsToEmptyText(): void
    {
        $Invoice = $this->createMock(QUI\ERP\Accounting\Invoice\InvoiceView::class);

        self::assertSame('', (new TestShippingEntry())->getInvoiceInformationText($Invoice));
    }
}
