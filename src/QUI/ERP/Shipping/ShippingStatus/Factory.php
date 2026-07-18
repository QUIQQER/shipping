<?php

/**
 * This file contains QUI\ERP\Shipping\ShippingStatus\Factory
 */

namespace QUI\ERP\Shipping\ShippingStatus;

use QUI;
use QUI\Permissions\Permission;

use function array_keys;
use function count;
use function max;

/**
 * Class Factory
 * - For shipping status creation
 *
 * @package QUI\ERP\Shipping\ShippingStatus\Factory
 */
class Factory extends QUI\Utils\Singleton
{
    /**
     * Create a new shipping status
     *
     * @param integer|string $id - shipping ID
     * @param string $color - color of the status
     * @param array<string, array<array-key, mixed>|string> $title - title
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function createShippingStatus(int | string $id, string $color, array $title): void
    {
        Permission::checkPermission('quiqqer.shipping.create');

        $list = Handler::getInstance()->getList();
        $id = (int)$id;
        $data = [];

        if (isset($list[$id])) {
            throw new Exception([
                'quiqqer/shipping',
                'exception.shippingStatus.exists'
            ]);
        }

        // config
        $Package = QUI::getPackage('quiqqer/shipping');
        $Config = $Package->getConfig();

        if ($Config === null) {
            throw new QUI\Exception('Missing quiqqer/shipping config');
        }

        $Config->setValue('shipping_status', (string)$id, $color);
        $Config->save();

        // translations
        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language] = $title[$language];
            }
        }

        // ShippingStatus title
        $data['package'] = 'quiqqer/shipping';
        $data['datatype'] = 'php,js';
        $data['html'] = 1;

        QUI\Translator::addUserVar(
            'quiqqer/shipping',
            'shipping.status.' . $id,
            $data
        );

        QUI\Translator::publish('quiqqer/shipping');

        // Create translations for auto-notification
        Handler::getInstance()->createNotificationTranslations($id);
        Handler::getInstance()->refreshList();
    }

    /**
     * Return a next ID to create a new Shipping Status
     *
     * @return int
     */
    public function getNextId(): int
    {
        $list = Handler::getInstance()->getList();

        if (!count($list)) {
            return 1;
        }

        $max = max(array_keys($list));

        return $max + 1;
    }
}
