<?php

/**
 * This file contains QUI\ERP\Shipping\Types\Factory
 */

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\Permissions\Permission;

use function class_exists;
use function is_integer;

/**
 * Class Factory
 *
 * @package QUI\ERP\Shipping\Types
 */
class Factory extends QUI\CRUD\Factory
{
    /**
     * Handler constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->Events->addEvent('onCreateBegin', function () {
            Permission::checkPermission('quiqqer.shipping.create');
        });

        // create new translation var for the area
        $this->Events->addEvent('onCreateEnd', function () {
            QUI\Translator::publish('quiqqer/shipping');
        });
    }

    /**
     * @param array $data
     *
     * @return ShippingEntry
     *
     * @throws QUI\ERP\Shipping\Exception
     * @throws QUI\Exception
     */
    public function createChild(array $data = []): QUI\CRUD\Child
    {
        if (!isset($data['active']) || !is_integer($data['active'])) {
            $data['active'] = 0;
        }

        if (!isset($data['priority']) || !is_integer($data['priority'])) {
            $data['priority'] = 0;
        }

        if (!isset($data['shipping_type']) || !class_exists($data['shipping_type'])) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.create.shipping.class.not.found'
            ]);
        }


        QUI::getEvents()->fireEvent('shippingCreateBegin', [$data['shipping_type']]);

        /* @var $NewChild ShippingEntry */
        $NewChild = parent::createChild($data);

        $this->createShippingLocale(
            'shipping.' . $NewChild->getId() . '.title',
            $NewChild->getShippingType()->getTitle()
        );

        $this->createShippingLocale(
            'shipping.' . $NewChild->getId() . '.workingTitle',
            $NewChild->getShippingType()->getTitle() . ' - ' . $NewChild->getId()
        );

        // description
        $this->createShippingLocale('shipping.' . $NewChild->getId() . '.description', '&nbsp;');

        try {
            QUI\Translator::publish('quiqqer/shipping');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        QUI::getEvents()->fireEvent('shippingCreateEnd', [$NewChild]);

        return $NewChild;
    }

    /**
     * @return string
     */
    public function getDataBaseTableName(): string
    {
        return 'shipping';
    }

    /**
     * @return string
     */
    public function getChildClass(): string
    {
        return ShippingEntry::class;
    }

    /**
     * @return array
     */
    public function getChildAttributes(): array
    {
        return [
            'id',
            'active',
            'icon',
            'priority',
            'areas',
            'articles',
            'categories',
            'user_groups',
            'payments',
            'shipping_type',
            'shipping_rules'
        ];
    }

    /**
     * @param int $id
     *
     * @return QUI\ERP\Shipping\Types\ShippingEntry
     *
     * @throws QUI\Exception
     */
    public function getChild($id): QUI\CRUD\Child
    {
        /* @var QUI\ERP\Shipping\Types\ShippingEntry $Shipping */
        $Shipping = parent::getChild($id);

        return $Shipping;
    }

    /**
     * Creates a locale
     *
     * @param $var
     * @param $title
     */
    protected function createShippingLocale($var, $title): void
    {
        if (QUI::getLocale()->isLocaleString($title)) {
            $parts = QUI::getLocale()->getPartsOfLocaleString($title);
            $title = QUI::getLocale()->get($parts[0], $parts[1]);
        }

        $data = [];

        foreach (QUI::availableLanguages() as $language) {
            $data[$language] = $title;
            $data[$language . '_edit'] = $title;
        }


        try {
            QUI\Translator::add('quiqqer/shipping', $var, 'quiqqer/shipping');
            QUI\Translator::update('quiqqer/shipping', $var, 'quiqqer/shipping', $data);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }
}
