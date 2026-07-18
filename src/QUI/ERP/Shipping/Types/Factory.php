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

        $this->Events->addEvent('onCreateBegin', function (): void {
            Permission::checkPermission('quiqqer.shipping.create');
        });

        // create new translation var for the area
        $this->Events->addEvent('onCreateEnd', function (): void {
            QUI\Translator::publish('quiqqer/shipping');
        });
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return ShippingEntry
     *
     * @throws QUI\ERP\Shipping\Exception
     * @throws QUI\Exception
     */
    public function createChild(array $data = []): ShippingEntry
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

        $NewChild = parent::createChild($data);

        if (!$NewChild instanceof ShippingEntry) {
            throw new \LogicException();
        }

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
     * @return list<string>
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
     * @return ShippingEntry
     *
     * @throws QUI\Exception
     */
    public function getChild($id): ShippingEntry
    {
        $Shipping = parent::getChild($id);

        if (!$Shipping instanceof ShippingEntry) {
            throw new \LogicException();
        }

        return $Shipping;
    }

    /**
     * Creates a locale
     *
     * @param string $var
     * @param string $title
     */
    protected function createShippingLocale($var, $title): void
    {
        if (QUI::getLocale()->isLocaleString($title)) {
            $parts = QUI::getLocale()->getPartsOfLocaleString($title);

            if (isset($parts[0], $parts[1])) {
                $title = QUI::getLocale()->get($parts[0], $parts[1]);
            }
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
