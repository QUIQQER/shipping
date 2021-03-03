<?php

/**
 * This file contains QUI\ERP\Shipping\Types\Factory
 */

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\Permissions\Permission;

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

        $self = $this;

        $this->Events->addEvent('onCreateBegin', function () {
            Permission::checkPermission('quiqqer.shipping.create');
        });

        // create new translation var for the area
        $this->Events->addEvent('onCreateEnd', function () use ($self) {
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
    public function createChild($data = [])
    {
        if (!isset($data['active']) || !\is_integer($data['active'])) {
            $data['active'] = 0;
        }

        if (!isset($data['priority']) || !\is_integer($data['priority'])) {
            $data['priority'] = 0;
        }

        if (!isset($data['shipping_type']) || !\class_exists($data['shipping_type'])) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.create.shipping.class.not.found'
            ]);
        }


        QUI::getEvents()->fireEvent('shippingCreateBegin', [$data['shipping_type']]);

        /* @var $NewChild ShippingEntry */
        $NewChild = parent::createChild($data);

        $this->createShippingLocale(
            'shipping.'.$NewChild->getId().'.title',
            $NewChild->getShippingType()->getTitle()
        );

        $this->createShippingLocale(
            'shipping.'.$NewChild->getId().'.workingTitle',
            $NewChild->getShippingType()->getTitle().' - '.$NewChild->getId()
        );

        $this->createShippingLocale('shipping.'.$NewChild->getId().'.decription', '');

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
    public function getDataBaseTableName()
    {
        return 'shipping';
    }

    /**
     * @return string
     */
    public function getChildClass()
    {
        return ShippingEntry::class;
    }

    /**
     * @return array
     */
    public function getChildAttributes()
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
    public function getChild($id)
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
    protected function createShippingLocale($var, $title)
    {
        $current = QUI::getLocale()->getCurrent();

        if (QUI::getLocale()->isLocaleString($title)) {
            $parts = QUI::getLocale()->getPartsOfLocaleString($title);
            $title = QUI::getLocale()->get($parts[0], $parts[1]);
        }

        try {
            QUI\Translator::addUserVar('quiqqer/shipping', $var, [
                $current   => $title,
                'datatype' => 'php,js',
                'package'  => 'quiqqer/shipping'
            ]);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }
}
