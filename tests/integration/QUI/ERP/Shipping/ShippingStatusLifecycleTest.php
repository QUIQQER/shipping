<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\ShippingStatus\Factory;
use QUI\ERP\Shipping\ShippingStatus\Handler;

class ShippingStatusLifecycleTest extends TestCase
{
    public function testStatusCanBeCreatedUpdatedNotifiedAndDeleted(): void
    {
        $Factory = Factory::getInstance();
        $Handler = Handler::getInstance();
        $id = $Factory->getNextId();
        $titles = [];

        foreach (QUI::availableLanguages() as $language) {
            $titles[$language] = 'PHPUnit shipping status ' . $language;
        }

        try {
            $Factory->createShippingStatus($id, '#123456', $titles);
            self::assertTrue($Handler->exists($id));

            $Handler->setShippingStatusNotification($id, false);

            $Status = $Handler->getShippingStatus($id);
            self::assertSame($id, $Status->getId());
            self::assertSame('#123456', $Status->getColor());
            self::assertFalse($Status->isAutoNotification());

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
            QUI\Translator::publish('quiqqer/shipping');
        }
    }
}
