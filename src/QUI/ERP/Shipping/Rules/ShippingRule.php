<?php

/**
 * This file contains QUI\ERP\Shipping\Rules\ShippingRule
 */

namespace QUI\ERP\Shipping\Rules;

use QUI;
use QUI\CRUD\Factory;
use QUI\Permissions\Permission;

use QUI\ERP\Areas\Utils as AreaUtils;
use QUI\ERP\Products\Utils\Fields as FieldUtils;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Exceptions\ShippingCanNotBeUsed;

use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Handler\Fields;

/**
 * Class ShippingEntry
 * A user created shipping entry
 *
 * @package QUI\ERP\Shipping\Types
 */
class ShippingRule extends QUI\CRUD\Child
{
    /**
     * Shipping constructor.
     *
     * @param int $id
     * @param Factory $Factory
     */
    public function __construct($id, Factory $Factory)
    {
        parent::__construct($id, $Factory);

        $this->Events->addEvent('onDeleteBegin', function () {
            Permission::checkPermission('quiqqer.shipping.delete');

            // delete locale
            $id = $this->getId();

            QUI\Translator::delete('quiqqer/shipping', 'shipping.'.$id.'.rule.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.'.$id.'.rule.workingTitle');
        });

        $this->Events->addEvent('onSaveBegin', function () {
            Permission::checkPermission('quiqqer.shipping.edit');

            $id         = $this->getId();
            $attributes = $this->getAttributes();

            if (\is_array($attributes['title'])) {
                QUI\Translator::edit(
                    'quiqqer/shipping',
                    'shipping.'.$id.'.rule.title',
                    'quiqqer/shipping',
                    $attributes['title']
                );
            };

            if (\is_array($attributes['workingTitle'])) {
                QUI\Translator::edit(
                    'quiqqer/shipping',
                    'shipping.'.$id.'.rule.workingTitle',
                    'quiqqer/shipping',
                    $attributes['workingTitle']
                );
            };

            QUI\Translator::publish('quiqqer/shipping');


            // discount
            $attributes['discount'] = \floatval($attributes['discount']);

            if (\is_numeric($attributes['discount_type']) || empty($attributes['discount_type'])) {
                $attributes['discount_type'] = (int)$attributes['discount_type'];
            }

            if ($attributes['discount_type'] === RuleFactory::DISCOUNT_TYPE_PERCENTAGE ||
                $attributes['discount_type'] === 'PERCENTAGE'
            ) {
                $attributes['discount_type'] = RuleFactory::DISCOUNT_TYPE_PERCENTAGE;
            } else {
                $attributes['discount_type'] = RuleFactory::DISCOUNT_TYPE_ABS;
            }

            // purchase
            if (empty($attributes['purchase_quantity_from'])) {
                $attributes['purchase_quantity_from'] = null;
            }

            if (empty($attributes['purchase_quantity_to'])) {
                $attributes['purchase_quantity_until'] = null;
            }

            if (empty($attributes['purchase_value_to'])) {
                $attributes['purchase_value_until'] = null;
            }

            if (empty($attributes['purchase_value_to'])) {
                $attributes['purchase_value_until'] = null;
            }

            // update for saving
            $this->setAttributes($attributes);
        });
    }

    /**
     * Return the payment as an array
     *
     * @return array
     */
    public function toArray()
    {
        $lg = 'quiqqer/shipping';
        $id = $this->getId();

        $attributes = $this->getAttributes();
        $Locale     = QUI::getLocale();

        try {
            $availableLanguages = QUI\Translator::getAvailableLanguages();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $availableLanguages = [];
        }

        foreach ($availableLanguages as $language) {
            $attributes['title'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.rule.title'
            );

            $attributes['workingTitle'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.rule.workingTitle'
            );
        }

        return $attributes;
    }

    /**
     * Return the shipping rule title
     *
     * @param null $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $language = $Locale->getCurrent();
        $id       = $this->getId();

        return $Locale->getByLang(
            $language,
            'quiqqer/shipping',
            'shipping.'.$id.'.rule.title'
        );
    }

    /**
     * Return the shipping rule priority
     *
     * @return int
     */
    public function getPriority()
    {
        return (int)$this->getAttribute('priority');
    }

    /**
     * Return the shipping rule discount value
     *
     * @return float
     */
    public function getDiscount()
    {
        return \floatval($this->getAttribute('discount'));
    }

    /**
     * is the user allowed to use this shipping
     *
     * @param QUI\Interfaces\Users\User $User
     * @return boolean
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        if ($this->isActive() === false) {
            return false;
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerShippingCanUsedBy', [$this, $User]);
        } catch (ShippingCanNotBeUsed $Exception) {
            return false;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return false;
        }


        // usage definitions / limits
        $dateFrom  = $this->getAttribute('date_from');
        $dateUntil = $this->getAttribute('date_until');
        $now       = \time();

        if ($dateFrom && \strtotime($dateFrom) > $now) {
            return false;
        }

        if ($dateUntil && \strtotime($dateUntil) < $now) {
            return false;
        }

        // assignment
        $userGroupValue = $this->getAttribute('user_groups');
        $areasValue     = $this->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            return true;
        }

        // not in area
        if ($areasValue) {
            $areasValue = \explode(',', $areasValue);
        }

        if (!empty($areasValue) && !AreaUtils::isUserInAreas($User, $areasValue)) {
            return false;
        }

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $this->getAttribute('user_groups')
        );

        $discountUsers  = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        if (empty($discountUsers) && empty($discountGroups)) {
            return true;
        }

        // user checking
        foreach ($discountUsers as $uid) {
            if ($User->getId() == $uid) {
                return true;
            }
        }

        // group checking
        $groupsOfUser = $User->getGroups();

        /* @var $Group QUI\Groups\Group */
        foreach ($discountGroups as $gid) {
            foreach ($groupsOfUser as $Group) {
                if ($Group->getId() == $gid) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * is the shipping allowed in the order?
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return bool
     *
     * @todo check unit
     */
    public function canUsedInOrder($Order)
    {
        if (!$this->isValid()) {
            return false;
        }

        if (!($Order instanceof QUI\ERP\Order\OrderInterface)) {
            return true;
        }

        if (!$this->canUsedBy($Order->getCustomer())) {
            return false;
        }

        /* @var $Order QUI\ERP\Order\Order */
        $Articles    = $Order->getArticles();
        $articleList = $Articles->getArticles();

        $articles  = $this->getAttribute('articles');
        $unitValue = $this->getAttribute('unit_value'); // weight amount
        $unit      = $this->getAttribute('unit');       // weight type

        $quantityFrom  = $this->getAttribute('purchase_quantity_from');     // Einkaufsmenge ab
        $quantityUntil = $this->getAttribute('purchase_quantity_until');    // Einkaufsmenge bis
        $purchaseFrom  = $this->getAttribute('purchase_value_from');        // Einkaufswert ab
        $purchaseUntil = $this->getAttribute('purchase_value_until');       // Einkaufswert bis

        // article checks
        $articleFound  = true;
        $articleWeight = 0;

        if (!empty($articles)) {
            $articleFound = false;

            if (\is_string($articles)) {
                $articles = \explode(',', $articles);
            }

            if (!\is_array($articles)) {
                $articles = [$articles];
            }

            $articles = \array_flip($articles);
        }

        foreach ($articleList as $Article) {
            $aid = $Article->getId();

            // get product because of weight
            try {
                $Product = Products::getProduct($aid);
                $Weight  = $Product->getField(Fields::FIELD_WEIGHT);
                $weight  = FieldUtils::weightFieldToKilogram($Weight);

                $articleWeight = $articleWeight + $weight;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }

            if (empty($articles)) {
                continue;
            }

            if (isset($articles[$aid])) {
                $articleFound = true;
                break;
            }
        }

        if ($articleFound === false) {
            return false;
        }

        // weight check
        if (!empty($unitValue) && !empty($unit)) {
            $unitValue = FieldUtils::weightToKilogram($unitValue, $unit);

            if ($articleWeight < $unitValue) {
                return false;
            }
        }


        // quantity check
        $count = $Order->count();

        if (!empty($quantityFrom) && $quantityFrom < $count) {
            return false;
        }

        if (!empty($quantityUntil) && $quantityFrom > $count) {
            return false;
        }

        // purchase
        try {
            $Calculation = $Order->getPriceCalculation();
            $sum         = $Calculation->getSum();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return false;
        }

        if (!empty($purchaseFrom)) {
            $purchaseFrom = \floatval($purchaseFrom);

            if ($purchaseFrom < $sum) {
                return false;
            }
        }

        if (!empty($purchaseUntil)) {
            $purchaseUntil = \floatval($purchaseUntil);

            if ($purchaseUntil > $sum) {
                return false;
            }
        }


        try {
            QUI::getEvents()->fireEvent('shippingCanUsedInOrder', [$this, $Order]);
        } catch (ShippingCanNotBeUsed $Exception) {
            return false;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Return the validation status of this rule
     * can the rule be used?
     */
    public function isValid()
    {
        if (!$this->isActive()) {
            return false;
        }

        // check date
        $usageFrom  = $this->getAttribute('date_from');
        $usageUntil = $this->getAttribute('date_until');
        $time       = \time();

        if (!empty($usageFrom)) {
            $usageFrom = \strtotime($usageFrom);

            if ($usageFrom > $time) {
                return false;
            }
        }

        if (!empty($usageUntil)) {
            $usageUntil = \strtotime($usageUntil);

            if ($usageUntil < $time) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the discount type
     *
     * @return int
     */
    public function getDiscountType()
    {
        return (int)$this->getAttribute('discount_type');
    }

    // region activation / deactivation

    /**
     * Activate the shipping type
     *
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function activate()
    {
        $this->setAttribute('active', 1);
        $this->update();
        $this->refresh();
    }

    /**
     * Is the shipping active?
     *
     * @return bool
     */
    public function isActive()
    {
        return !!$this->getAttribute('active');
    }

    /**
     * Deactivate the shipping type
     *
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function deactivate()
    {
        $this->setAttribute('active', 0);
        $this->update();
        $this->refresh();
    }

    //endregion
}
