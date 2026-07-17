<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\ShippingStatus\Factory;
use QUI\ERP\Shipping\ShippingStatus\Handler;
use QUI\ERP\Products\Handler\Fields;

class ShippingStatusLifecycleTest extends TestCase
{
    public function testStatusCanBeCreatedUpdatedNotifiedAndDeleted(): void
    {
        $Factory = Factory::getInstance();
        $Handler = Handler::getInstance();
        $id = $Factory->getNextId();
        $titles = [];
        $messageVars = [
            'message.no.rule.found.order.continue',
            'message.no.rule.found.order.cancel'
        ];
        $existingMessageVars = [];

        foreach ($messageVars as $messageVar) {
            $existingMessageVars[$messageVar] = QUI\Translator::getVarData(
                'quiqqer/shipping',
                $messageVar,
                'quiqqer/shipping'
            );
        }

        foreach (QUI::availableLanguages() as $language) {
            $titles[$language] = 'PHPUnit shipping status ' . $language;
        }

        try {
            $Factory->createShippingStatus($id, '#123456', $titles);
            self::assertTrue($Handler->exists($id));

            $FieldsList = new \ReflectionProperty(Fields::class, 'list');
            $previousFields = $FieldsList->getValue();
            $Field = $this->createMock(QUI\ERP\Products\Field\Field::class);

            try {
                $FieldsList->setValue(null, array_replace($previousFields, [
                    Shipping::PRODUCT_FIELD_SHIPPING_TIME => $Field
                ]));
                EventHandler::onPackageSetup(QUI::getPackage('quiqqer/shipping'));
            } finally {
                $FieldsList->setValue(null, $previousFields);
            }

            self::assertTrue($Handler->exists($id));

            $Handler->setShippingStatusNotification($id, false);

            $Status = $Handler->getShippingStatus($id);
            self::assertSame($id, $Status->getId());
            self::assertSame('#123456', $Status->getColor());
            self::assertFalse($Status->isAutoNotification());

            $Customer = $this->createMock(QUI\ERP\User::class);
            $Customer->method('getName')->willReturn('PHPUnit Customer');
            $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
            $Order->method('getCustomer')->willReturn($Customer);
            $Order->method('getPrefixedId')->willReturn('ORDER-1');
            $Order->method('getCreateDate')->willReturn('2026-07-17 12:00:00');
            $Locale = $this->createMock(QUI\Locale::class);
            $Locale->method('get')->willReturn('Rendered status message');
            $Locale->method('formatDate')->willReturn('17.07.2026');
            self::assertSame(
                'Rendered status message',
                $Status->getStatusChangeNotificationText($Order, $Locale)
            );

            $Handler->setShippingStatusNotification($id, true);
            self::assertTrue($Handler->getShippingStatus($id)->isAutoNotification());

            $updatedTitles = array_map(
                static fn (string $title): string => $title . ' updated',
                $titles
            );
            $Handler->updateShippingStatus($id, '#abcdef', $updatedTitles);
            $Handler->refreshList();

            $Updated = $Handler->getShippingStatus($id);
            self::assertSame('#abcdef', $Updated->getColor());
            self::assertSame($id, $Updated->toArray()['id']);

            $Handler->createNotificationTranslations($id);
            $Handler->createNotificationTranslations($id);

            $Handler->deleteShippingStatus($id);
            $Handler->refreshList();
            self::assertFalse($Handler->exists($id));
        } finally {
            if ($Handler->exists($id)) {
                $Handler->deleteShippingStatus($id);
                $Handler->refreshList();
            }

            QUI\Translator::delete(
                'quiqqer/shipping',
                'shipping.status.notification.' . $id
            );
            $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
            $Config->del('shipping_status_notification', (string)$id);
            $Config->save();

            foreach ($existingMessageVars as $messageVar => $existing) {
                if (empty($existing)) {
                    QUI\Translator::delete('quiqqer/shipping', $messageVar);
                }
            }

            QUI\Translator::publish('quiqqer/shipping');
        }
    }
}
