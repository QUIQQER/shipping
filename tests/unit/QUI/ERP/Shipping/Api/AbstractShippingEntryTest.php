<?php

namespace QUI\ERP\Shipping\Api;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\Methods\Standard\ShippingType;

class AbstractShippingEntryTest extends TestCase
{
    public function testBaseEntryProvidesStableApiDefaults(): void
    {
        $Entry = new TestShippingEntry();
        $Locale = new QUI\Locale();
        $Entry->setLocale($Locale);

        self::assertSame($Locale, $Entry->getLocale());
        self::assertSame(TestShippingEntry::class, $Entry->getClass());
        self::assertSame(md5(TestShippingEntry::class), $Entry->getName());
        self::assertSame('/default.svg', $Entry->getIcon());
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

class TestShippingEntry extends AbstractShippingEntry
{
    public function __construct()
    {
    }

    public function getId(): int|string
    {
        return 1;
    }

    public function getTitle($Locale = null): string
    {
        return 'Test shipping';
    }

    public function getDescription(null|QUI\Locale $Locale = null): string
    {
        return 'Test description';
    }

    public function getWorkingTitle(): string
    {
        return 'Test working title';
    }

    public function getIcon(): string
    {
        return '/default.svg';
    }

    public function getShippingType(): ShippingTypeInterface
    {
        return new ShippingType();
    }

    public function getPrice(): float|int
    {
        return 0;
    }

    public function getPriceDisplay(): string
    {
        return '';
    }

    public function getAttribute(string $name): mixed
    {
        return null;
    }

    public function toJSON(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }
}
