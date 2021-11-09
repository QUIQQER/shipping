<?php

/**
 * This file contains QUI\ERP\Shipping\ShippingStatus\Factory
 */

namespace QUI\ERP\Shipping\ShippingStatus;

use QUI;

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
     * @param string|integer $id - shipping ID
     * @param string $color - color of the status
     * @param array $title - title
     *
     * @throws Exception
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function createShippingStatus($id, $color, array $title)
    {
        $list = Handler::getInstance()->getList();
        $id   = (int)$id;
        $data = [];

        if (isset($list[$id])) {
            throw new Exception([
                'quiqqer/shipping',
                'exception.shippingStatus.exists'
            ]);
        }

        // config
        $Package = QUI::getPackage('quiqqer/shipping');
        $Config  = $Package->getConfig();

        $Config->setValue('shipping_status', $id, $color);
        $Config->save();

        // translations
        if (\is_array($title)) {
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                if (isset($title[$language])) {
                    $data[$language] = $title[$language];
                }
            }
        }

        // ShippingStatus title
        $data['package']  = 'quiqqer/shipping';
        $data['datatype'] = 'php,js';
        $data['html']     = 1;

        QUI\Translator::addUserVar(
            'quiqqer/shipping',
            'shipping.status.'.$id,
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

        if (!\count($list)) {
            return 1;
        }

        $max = \max(\array_keys($list));

        return $max + 1;
    }
}
