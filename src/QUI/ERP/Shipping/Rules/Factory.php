<?php

/**
 * This file contains QUI\ERP\Shipping\Rules\Factory
 */

namespace QUI\ERP\Shipping\Rules;

use QUI;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\Permissions\Permission;

/**
 * Class Factory
 *
 * @package QUI\ERP\Shipping\Types
 */
class Factory extends QUI\CRUD\Factory
{
    /**
     * absolute discount
     */
    const DISCOUNT_TYPE_ABS = 0;

    /**
     * percentage discount
     */
    const DISCOUNT_TYPE_PERCENTAGE = 1;

    /**
     * percentage discount from order sum (total) // PERCENTAGE_ORDER
     */
    const DISCOUNT_TYPE_PC_ORDER = 2;

    /**
     * Handler constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $self = $this;

        $this->Events->addEvent('onCreateBegin', function () {
            Permission::checkPermission('quiqqer.shipping.rule.create');
        });

        // create new translation var for the area
        $this->Events->addEvent('onCreateEnd', function () use ($self) {
            QUI\Translator::publish('quiqqer/shipping');
        });
    }

    /**
     * @param array $data
     *
     * @return ShippingRule
     *
     * @throws QUI\Exception
     */
    public function createChild($data = [])
    {
        // filter
        $allowed = \array_flip([
            'title',
            'workingTitle',
            'date_from',
            'date_until',
            'priority',
            'purchase_quantity_from',
            'purchase_quantity_until',
            'purchase_value_from',
            'purchase_value_until',
            'discount',
            'discount_type',
            'articles',
            'articles_only',
            'areas',
            'user_groups',
            'unit_terms',
            'no_rule_after'
        ]);

        $data = \array_filter($data, function ($k) use ($allowed) {
            return isset($allowed[$k]);
        }, \ARRAY_FILTER_USE_KEY);


        if (!isset($data['active']) || !\is_integer($data['active'])) {
            $data['active'] = 0;
        }

        if (!isset($data['purchase_quantity_from']) || !\is_integer($data['purchase_quantity_from'])) {
            $data['purchase_quantity_from'] = 0;
        }

        if (!isset($data['purchase_quantity_until']) || !\is_integer($data['purchase_quantity_until'])) {
            $data['purchase_quantity_until'] = 0;
        }

        if (!isset($data['priority']) || !\is_numeric($data['priority'])) {
            $data['priority'] = 0;
        } elseif (isset($data['priority']) && !\is_int($data['priority'])) {
            $data['priority'] = (int)$data['priority'];
        }

        if (!isset($data['discount']) || empty($data['discount'])) {
            $data['discount'] = 0;
        }

        if (isset($data['unit_terms']) && \is_array($data['unit_terms'])) {
            $data['unit_terms'] = \json_encode($data['unit_terms']);
        }

        if (!isset($data['unit_terms'])) {
            $data['unit_terms'] = '';
        }

        if (!isset($data['articles_only']) || empty($data['articles_only'])) {
            $data['articles_only'] = 0;
        } else {
            $data['articles_only'] = (int)$data['articles_only'];
        }

        if (!isset($data['no_rule_after']) || empty($data['no_rule_after'])) {
            $data['no_rule_after'] = 0;
        } else {
            $data['no_rule_after'] = (int)$data['no_rule_after'];
        }

        // discount
        if (\is_numeric($data['discount_type'])) {
            $data['discount_type'] = (int)$data['discount_type'];
        }

        switch ($data['discount_type']) {
            case 'ABS':
                $data['discount_type'] = RuleFactory::DISCOUNT_TYPE_ABS;
                break;

            case 'PERCENTAGE':
                $data['discount_type'] = RuleFactory::DISCOUNT_TYPE_PERCENTAGE;
                break;

            case 'PERCENTAGE_ORDER':
                $data['discount_type'] = RuleFactory::DISCOUNT_TYPE_PC_ORDER;
                break;

            case RuleFactory::DISCOUNT_TYPE_ABS:
            case RuleFactory::DISCOUNT_TYPE_PERCENTAGE:
            case RuleFactory::DISCOUNT_TYPE_PC_ORDER:
                break;

            default:
                $data['discount_type'] = RuleFactory::DISCOUNT_TYPE_ABS;
        }

        $attributes['discount'] = \floatval($data['discount']);


        // start creating
        QUI::getEvents()->fireEvent('shippingRuleCreateBegin', [$data]);

        /* @var $NewChild ShippingRule */
        $NewChild = parent::createChild($data);

        $localeTitle = 'shipping.' . $NewChild->getId() . '.rule.title';
        $localeWTitle = 'shipping.' . $NewChild->getId() . '.rule.workingTitle';

        $this->createShippingLocale(
            $localeTitle,
            QUI::getLocale()->get('quiqqer/shipping', 'new.shipping.rule.placeholder')
        );

        $this->createShippingLocale(
            $localeWTitle,
            QUI::getLocale()->get('quiqqer/shipping', 'new.shipping.rule.placeholder')
        );

        // set translations
        if (isset($data['title']) && !empty($data['title']) && \is_array($data['title'])) {
            $title = [];

            foreach ($data['title'] as $lang => $v) {
                if (\mb_strlen($lang) === 2) {
                    $title[$lang] = $v;
                }
            }

            QUI\Translator::edit(
                'quiqqer/shipping',
                $localeTitle,
                'quiqqer/shipping',
                $title
            );
        }

        if (isset($data['workingTitle']) && !empty($data['workingTitle']) && \is_array($data['workingTitle'])) {
            $title = [];

            foreach ($data['workingTitle'] as $lang => $v) {
                if (\mb_strlen($lang) === 2) {
                    $title[$lang] = $v;
                }
            }

            QUI\Translator::edit(
                'quiqqer/shipping',
                $localeWTitle,
                'quiqqer/shipping',
                $title
            );
        }


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
        return 'shipping_rules';
    }

    /**
     * @return string
     */
    public function getChildClass()
    {
        return ShippingRule::class;
    }

    /**
     * @return array
     */
    public function getChildAttributes()
    {
        return [
            'id',
            'active',
            'no_rule_after',

            'unit_terms',
            'date_from',
            'date_until',
            'purchase_quantity_from',
            'purchase_quantity_until',
            'purchase_value_from',
            'purchase_value_until',
            'priority',

            'areas',
            'categories',
            'user_groups',

            'articles',
            'articles_only',

            'discount',
            'discount_type',
            'articles',
            'user_groups'
        ];
    }

    /**
     * @param int $id
     *
     * @return QUI\ERP\Shipping\Api\AbstractShippingEntry
     *
     * @throws QUI\Exception
     */
    public function getChild($id)
    {
        /* @var QUI\ERP\Shipping\Api\AbstractShippingEntry $Shipping */
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
                $current => $title,
                'datatype' => 'php,js',
                'package' => 'quiqqer/shipping'
            ]);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }
}
