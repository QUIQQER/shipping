<?php

/**
 * This file contains QUI\ERP\Shipping\Rules\ShippingRule
 */

namespace QUI\ERP\Shipping\Rules;

use QUI;
use QUI\Translator;
use QUI\CRUD\Factory;
use QUI\ERP\Shipping\Debug;
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

            if (!isset($attributes['articles_only']) || empty($attributes['articles_only'])) {
                $attributes['articles_only'] = 0;
            } else {
                $attributes['articles_only'] = (int)$attributes['articles_only'];
            }

            if (!isset($attributes['no_rule_after']) || empty($attributes['no_rule_after'])) {
                $attributes['no_rule_after'] = 0;
            } else {
                $attributes['no_rule_after'] = (int)$attributes['no_rule_after'];
            }

            if (isset($attributes['unit_terms']) && \is_array($attributes['unit_terms'])) {
                $attributes['unit_terms'] = \json_encode($attributes['unit_terms']);
            }

            // null fix
            $nullEmpty = [
                'purchase_quantity_from',
                'purchase_quantity_until',
                'purchase_value_from',
                'purchase_value_until',
                'unit_terms'
            ];

            foreach ($nullEmpty as $k) {
                if (empty($attributes[$k])) {
                    $attributes[$k] = null;
                }
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

        $availableLanguages = QUI\Translator::getAvailableLanguages();

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

        $attributes['unit_terms'] = $this->getUnitTerms();

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
            Debug::addLog("{$this->getTitle()} :: is not active");

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
            Debug::addLog("{$this->getTitle()} :: date from is not valid {$dateFrom} > {$now}");

            return false;
        }

        if ($dateUntil && \strtotime($dateUntil) < $now) {
            Debug::addLog("{$this->getTitle()} :: date from is not valid {$dateFrom} < {$now}");

            return false;
        }

        // assignment
        $userGroupValue = $this->getAttribute('user_groups');
        $areasValue     = $this->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            Debug::addLog("{$this->getTitle()} :: empty user and areas [ok]");

            return true;
        }

        // not in area
        if ($areasValue) {
            $areasValue = \explode(',', $areasValue);
        }

        if (!empty($areasValue) && !AreaUtils::isUserInAreas($User, $areasValue)) {
            Debug::addLog("{$this->getTitle()} :: user is not in areas");

            return false;
        }

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $this->getAttribute('user_groups')
        );

        $discountUsers  = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        if (empty($discountUsers) && empty($discountGroups)) {
            Debug::addLog("{$this->getTitle()} :: empty discount [ok]");

            return true;
        }

        // user checking
        foreach ($discountUsers as $uid) {
            if ($User->getId() == $uid) {
                Debug::addLog("{$this->getTitle()} :: user is found in users [ok]");

                return true;
            }
        }

        // group checking
        $groupsOfUser = $User->getGroups();

        /* @var $Group QUI\Groups\Group */
        foreach ($discountGroups as $gid) {
            foreach ($groupsOfUser as $Group) {
                if ($Group->getId() == $gid) {
                    Debug::addLog("{$this->getTitle()} :: group is found in groups [ok]");

                    return true;
                }
            }
        }

        Debug::addLog("{$this->getTitle()} :: shipping rule is not valid. is not found in users and groups");

        return false;
    }

    /**
     * is the shipping allowed in the order?
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return bool
     */
    public function canUsedInOrder($Order)
    {
        if (!$this->isValid()) {
            Debug::addLog("{$this->getTitle()} :: is not valid");

            return false;
        }

        if (!($Order instanceof QUI\ERP\Order\OrderInterface)) {
            return true;
        }

        if (!$this->canUsedBy($Order->getCustomer())) {
            Debug::addLog("{$this->getTitle()} :: can not be used by {$Order->getCustomer()->getId()}");

            return false;
        }

        /* @var $Order QUI\ERP\Order\Order */
        $Articles    = $Order->getArticles();
        $articleList = $Articles->getArticles();

        if (!$Articles->count()) {
            return false;
        }

        $articles    = $this->getAttribute('articles');
        $articleOnly = (int)$this->getAttribute('articles_only');
        $unitTerms   = $this->getUnitTerms();

        $quantityFrom  = $this->getAttribute('purchase_quantity_from');     // Einkaufsmenge ab
        $quantityUntil = $this->getAttribute('purchase_quantity_until');    // Einkaufsmenge bis
        $purchaseFrom  = $this->getAttribute('purchase_value_from');        // Einkaufswert ab
        $purchaseUntil = $this->getAttribute('purchase_value_until');       // Einkaufswert bis

        // article checks
        $Shipping     = QUI\ERP\Shipping\Shipping::getInstance();
        $unitIds      = $Shipping->getShippingRuleUnitFieldIds();
        $articleFound = true;
        $articleUnits = [];
        $debugUnits   = [];

        if (!empty($articles)) {
            $articleFound = false;

            if (\is_string($articles)) {
                $articles = \explode(',', $articles);
            }

            if (!\is_array($articles)) {
                $articles = [$articles];
            }

            $articles = \array_flip($articles);
        } else {
            $articleOnly = 0;
        }

        foreach ($articleList as $Article) {
            $aid             = $Article->getId();
            $articleQuantity = $Article->getQuantity();

            // get product because of units
            try {
                $Product = Products::getProduct($aid);

                foreach ($unitIds as $unitId) {
                    $Weight = $Product->getField($unitId);
                    $weight = $Weight->getValue();

                    if ((int)$unitId === Fields::FIELD_WEIGHT) {
                        $weight = FieldUtils::weightFieldToKilogram($Weight);
                    }

                    if (\is_array($weight)) {
                        $weight = $weight['quantity'];
                    }

                    if (!isset($articleUnits[$unitId])) {
                        $articleUnits[$unitId] = 0;
                    }

                    $articleUnits[$unitId] = $articleUnits[$unitId] + ($weight * $articleQuantity);

                    $debugUnits[$unitId] = [
                        'field'  => $Weight->getTitle(),
                        'amount' => $articleUnits[$unitId]
                    ];
                }
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

        if (QUI\ERP\Shipping\Debug::isEnabled()) {
            foreach ($debugUnits as $debugUnit) {
                QUI\ERP\Shipping\Debug::addLog(
                    "- Check units {$debugUnit['field']} -> {$debugUnit['amount']}"
                );
            }
        }

        if ($articleFound && $articleOnly && \count($articleList) !== 1) {
            QUI\ERP\Shipping\Debug::addLog(
                "{$this->getTitle()} :: is not a single article"
            );

            return false;
        }

        if ($articleFound === false) {
            QUI\ERP\Shipping\Debug::addLog(
                "{$this->getTitle()} :: article not found for this rule"
            );

            return false;
        }

        // unit terms check
        if (!empty($unitTerms)) {
            foreach ($unitTerms as $unitTerm) {
                if (!\is_array($unitTerm)) {
                    continue;
                }

                if (!isset($unitTerm['value'])) {
                    $unitTerm['value'] = '';
                }

                if (!isset($unitTerm['value2'])) {
                    $unitTerm['value2'] = '';
                }

                if ($unitTerm['value'] === '' && $unitTerm['value2'] === '') {
                    continue;
                }

                if (empty($unitTerm['term'])) {
                    $unitTerm['term'] = 'gt';
                }

                if (empty($unitTerm['term2'])) {
                    $unitTerm['term2'] = 'gt';
                }

                $id    = (int)$unitTerm['id'];
                $unit  = $unitTerm['unit'];
                $value = \floatval($unitTerm['value']);

                $term  = $unitTerm['term'];
                $hTerm = FieldUtils::termToHuman($term);

                if (!isset($articleUnits[$id])) {
                    continue;
                }

                if ($id === Fields::FIELD_WEIGHT) {
                    $unitValue = FieldUtils::weightToKilogram($value, $unit);
                    $compare   = FieldUtils::compare($articleUnits[$id], $unitValue, $term);

                    if ($compare === false) {
                        QUI\ERP\Shipping\Debug::addLog(
                            "{$this->getTitle()} :: weight is not valid {$articleUnits[$id]} {$hTerm} {$unitValue}"
                        );

                        return false;
                    }

                    // term 2
                    if (!empty($unitTerm['value2'])) {
                        $value2 = \floatval($unitTerm['value2']);
                        $term2  = $unitTerm['term2'];
                        $hTerm2 = FieldUtils::termToHuman($term2);

                        $unitValue = FieldUtils::weightToKilogram($value2, $unit);
                        $compare2  = FieldUtils::compare($articleUnits[$id], $unitValue, $term2);

                        if ($compare2 === false) {
                            QUI\ERP\Shipping\Debug::addLog(
                                "{$this->getTitle()} :: weight is not valid {$articleUnits[$id]} {$hTerm2} {$unitValue}"
                            );

                            return false;
                        }
                    }

                    continue;
                }

                $compare = FieldUtils::compare($articleUnits[$id], $value, $term);

                if ($compare === false) {
                    QUI\ERP\Shipping\Debug::addLog(
                        "{$this->getTitle()} :: unit term is not valid {$articleUnits[$id]} {$hTerm} {$value}"
                    );

                    return false;
                }

                // term 2
                if (!empty($unitTerm['value2'])) {
                    $value2   = \floatval($unitTerm['value2']);
                    $term2    = $unitTerm['term2'];
                    $hTerm2   = FieldUtils::termToHuman($term2);
                    $compare2 = FieldUtils::compare($articleUnits[$id], $value2, $term2);

                    if ($compare2 === false) {
                        QUI\ERP\Shipping\Debug::addLog(
                            "{$this->getTitle()} :: unit term is not valid {$articleUnits[$id]} {$hTerm2} {$value2}"
                        );

                        return false;
                    }
                }
            }
        }


        // quantity check
        $count = $Order->count();

        if (!empty($quantityFrom) && $quantityFrom < $count) {
            QUI\ERP\Shipping\Debug::addLog(
                "{$this->getTitle()} :: quantity from is not valid, {$count} < {$quantityFrom}"
            );

            return false;
        }

        if (!empty($quantityUntil) && $quantityFrom > $count) {
            QUI\ERP\Shipping\Debug::addLog(
                "{$this->getTitle()} :: quantity until is not valid, {$count} > {$quantityFrom}"
            );

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
                QUI\ERP\Shipping\Debug::addLog(
                    "{$this->getTitle()} :: purchase from is not valid, {$purchaseFrom} < {$sum}"
                );

                return false;
            }
        }

        if (!empty($purchaseUntil)) {
            $purchaseUntil = \floatval($purchaseUntil);

            if ($purchaseUntil > $sum) {
                QUI\ERP\Shipping\Debug::addLog(
                    "{$this->getTitle()} :: purchase from is not valid, {$purchaseFrom} > {$sum}"
                );

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

        QUI\ERP\Shipping\Debug::addLog(
            "{$this->getTitle()} :: is valid [ok]"
        );

        return true;
    }

    /**
     * no further rules are used after this rule
     *
     * @return bool
     */
    public function noRulesAfter()
    {
        if ($this->existsAttribute('no_rule_after')) {
            return !!$this->getAttribute('no_rule_after');
        }

        return false;
    }

    /**
     * Return the validation status of this rule
     * can the rule be used?
     */
    public function isValid()
    {
        if (!$this->isActive()) {
            QUI\ERP\Shipping\Debug::addLog(
                $this->getTitle()." :: is not active"
            );

            return false;
        }

        // check date
        $usageFrom  = $this->getAttribute('date_from');
        $usageUntil = $this->getAttribute('date_until');
        $time       = \time();

        if (!empty($usageFrom)) {
            $usageFrom = \strtotime($usageFrom);

            if ($usageFrom > $time) {
                QUI\ERP\Shipping\Debug::addLog(
                    $this->getTitle()." :: usage from is not ok, {$usageFrom} > {$time}"
                );

                return false;
            }
        }

        if (!empty($usageUntil)) {
            $usageUntil = \strtotime($usageUntil);

            if ($usageUntil < $time) {
                QUI\ERP\Shipping\Debug::addLog(
                    $this->getTitle()." :: usage from is not ok, {$usageFrom} < {$time}"
                );

                return false;
            }
        }

        QUI\ERP\Shipping\Debug::addLog(
            $this->getTitle()." :: is valid, date from to is valid"
        );

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

    /**
     * Return the unit terms
     *
     * @return bool|array
     */
    public function getUnitTerms()
    {
        $unitTerms = $this->getAttribute('unit_terms');

        if (empty($unitTerms)) {
            return false;
        }

        $unitTerms = \json_decode($unitTerms, true);

        if ($unitTerms) {
            return $unitTerms;
        }

        return false;
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

    //region setter

    /**
     * Set the title
     *
     * @param array $titles
     */
    public function setTitle(array $titles)
    {
        $this->setLocaleVar(
            'shipping.'.$this->getId().'.rule.title',
            $titles
        );
    }

    /**
     * Set the working title
     *
     * @param array $titles
     */
    public function setWorkingTitle(array $titles)
    {
        $this->setLocaleVar(
            'shipping.'.$this->getId().'.rule.workingTitle',
            $titles
        );
    }

    /**
     * Creates a locale
     *
     * @param string $var
     * @param array $title
     */
    protected function setLocaleVar($var, $title)
    {
        $data = [
            'datatype' => 'php,js',
            'package'  => 'quiqqer/shipping'
        ];

        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (!isset($title[$language])) {
                continue;
            }

            $data[$language]         = $title[$language];
            $data[$language.'_edit'] = $title[$language];
        }

        $exists = Translator::getVarData('quiqqer/shipping', $var, 'quiqqer/shipping');

        try {
            if (empty($exists)) {
                Translator::addUserVar('quiqqer/shipping', $var, $data);
            } else {
                Translator::edit('quiqqer/shipping', $var, 'quiqqer/shipping', $data);
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        try {
            Translator::publish('quiqqer/shipping');
        } catch (QUi\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    //endregion
}
