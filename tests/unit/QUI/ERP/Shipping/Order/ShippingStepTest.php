<?php

namespace QUI\ERP\Shipping\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Shipping\Types\ShippingEntry;

class ShippingStepTest extends TestCase
{
    public function testValidateAcceptsSelectedUsableShipping(): void
    {
        $User = $this->createMock(QUI\ERP\User::class);
        $Entry = $this->createMock(ShippingEntry::class);
        $Order = $this->createMock(AbstractOrder::class);

        $Order->method('getShipping')->willReturn($Entry);
        $Order->method('getCustomer')->willReturn($User);
        $Entry->expects(self::once())->method('canUsedBy')->with($User, $Order)->willReturn(true);
        $Entry->expects(self::once())->method('canUsedInErpEntity')->with($Order)->willReturn(true);

        $Step = new TestableShippingStep(['Order' => $Order]);
        $Step->validShipping = [$Entry];
        $Step->validate();

        self::assertTrue(true);
    }

    public function testValidateRejectsShippingThatUserCannotUse(): void
    {
        $User = $this->createMock(QUI\ERP\User::class);
        $Entry = $this->createMock(ShippingEntry::class);
        $Order = $this->createMock(AbstractOrder::class);

        $Order->method('getShipping')->willReturn($Entry);
        $Order->method('getCustomer')->willReturn($User);
        $Entry->method('canUsedBy')->willReturn(false);

        $Step = new TestableShippingStep(['Order' => $Order]);
        $Step->validShipping = [$Entry];

        $this->expectException(QUI\ERP\Order\Exception::class);
        $Step->validate();
    }

    public function testValidateRejectsShippingThatEntityCannotUse(): void
    {
        $User = $this->createMock(QUI\ERP\User::class);
        $Entry = $this->createMock(ShippingEntry::class);
        $Order = $this->createMock(AbstractOrder::class);

        $Order->method('getShipping')->willReturn($Entry);
        $Order->method('getCustomer')->willReturn($User);
        $Entry->method('canUsedBy')->willReturn(true);
        $Entry->method('canUsedInErpEntity')->willReturn(false);

        $Step = new TestableShippingStep(['Order' => $Order]);
        $Step->validShipping = [$Entry];

        $this->expectException(QUI\ERP\Order\Exception::class);
        $Step->validate();
    }

    public function testValidateAutomaticallySelectsOnlyAvailableShipping(): void
    {
        $User = $this->createMock(QUI\ERP\User::class);
        $Entry = $this->createMock(ShippingEntry::class);
        $Order = $this->createMock(AbstractOrder::class);

        $Order->method('getShipping')->willReturnOnConsecutiveCalls(null, $Entry);
        $Order->method('getCustomer')->willReturn($User);
        $Order->expects(self::once())->method('setShipping')->with($Entry);
        $Entry->method('canUsedBy')->willReturn(true);
        $Entry->method('canUsedInErpEntity')->willReturn(true);

        $Step = new TestableShippingStep(['Order' => $Order]);
        $Step->validShipping = [$Entry];
        $Step->validate();

        self::assertTrue(true);
    }

    public function testValidateRequiresSelectionWhenSeveralOptionsExist(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getShipping')->willReturn(null);
        $Step = new TestableShippingStep(['Order' => $Order]);
        $Step->validShipping = [
            $this->createMock(ShippingEntry::class),
            $this->createMock(ShippingEntry::class)
        ];

        $this->expectException(QUI\ERP\Order\Exception::class);
        $Step->validate();
    }

    public function testValidateContinuesWithoutAvailableShippingWhenConfigured(): void
    {
        $this->withNoRuleBehavior(
            QUI\ERP\Shipping\Shipping::NO_RULE_FOUND_ORDER_CONTINUE,
            function (): void {
                $Order = $this->createMock(AbstractOrder::class);
                $Order->method('getShipping')->willReturn(null);
                $Step = new TestableShippingStep(['Order' => $Order]);
                $Step->validShipping = [];
                $Step->validate();
                self::assertTrue(true);
            }
        );
    }

    public function testValidateCancelsWithoutAvailableShippingWhenConfigured(): void
    {
        $caught = false;

        $this->withNoRuleBehavior(
            QUI\ERP\Shipping\Shipping::NO_RULE_FOUND_ORDER_CANCEL,
            function () use (&$caught): void {
                $Order = $this->createMock(AbstractOrder::class);
                $Order->method('getShipping')->willReturn(null);
                $Step = new TestableShippingStep(['Order' => $Order]);
                $Step->validShipping = [];

                try {
                    $Step->validate();
                } catch (QUI\ERP\Order\Exception) {
                    $caught = true;
                }
            }
        );

        self::assertTrue($caught);
    }

    public function testSaveIgnoresUnknownShippingId(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Step = new TestableShippingStep([
            'Order' => $Order,
            'shipping' => PHP_INT_MAX
        ]);

        $Step->save();

        self::assertTrue(true);
    }

    private function withNoRuleBehavior(int $behavior, callable $test): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->getValue('no_rules', 'behavior');

        try {
            $Config->setValue('no_rules', 'behavior', $behavior);
            $Config->save();
            $test();
        } finally {
            if ($previous === null) {
                $Config->del('no_rules', 'behavior');
            } else {
                $Config->setValue('no_rules', 'behavior', $previous);
            }

            $Config->save();
        }
    }
}

class TestableShippingStep extends Shipping
{
    public array $validShipping = [];

    protected function getValidShipping(): array
    {
        return $this->validShipping;
    }
}
