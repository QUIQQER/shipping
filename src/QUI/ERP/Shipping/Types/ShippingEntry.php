<?php

/**
 * This file contains QUI\ERP\Shipping\Types\ShippingEntry
 */

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\CRUD\Factory;
use QUI\ERP\Shipping\Api;
use QUI\ERP\Shipping\Debug;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Rules\ShippingRule;
use QUI\Exception;
use QUI\Permissions\Permission;
use QUI\Translator;

use function class_exists;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function method_exists;
use function round;
use function usort;

/**
 * Class ShippingEntry
 * A user created shipping entry
 *
 * @package QUI\ERP\Shipping\Types
 */
class ShippingEntry extends QUI\CRUD\Child implements Api\ShippingInterface
{
    /**
     * @var QUI\ERP\ErpEntityInterface|null
     */
    protected ?QUI\ERP\ErpEntityInterface $ErpEntity = null;

    /**
     * @var null|QUI\ERP\Address|QUI\Users\Address
     */
    protected $Address = null;

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

            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $id . '.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $id . '.description');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $id . '.workingTitle');

            try {
                QUI\Translator::publish('quiqqer/shipping');
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        });

        $this->Events->addEvent('onSaveBegin', function () {
            Permission::checkPermission('quiqqer.shipping.edit');
        });
    }

    /**
     * Return the shipping as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $lg = 'quiqqer/shipping';
        $id = $this->getId();

        $attributes = $this->getAttributes();
        $Locale = QUI::getLocale();
        $currentLang = $Locale->getCurrent();

        $availableLanguages = QUI\Translator::getAvailableLanguages();

        foreach ($availableLanguages as $language) {
            $attributes['title'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.' . $id . '.title'
            );

            $attributes['description'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.' . $id . '.description'
            );

            $attributes['workingTitle'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.' . $id . '.workingTitle'
            );

            if ($language === $currentLang) {
                $attributes['currentTitle'] = $attributes['title'][$language];
                $attributes['currentDescription'] = $attributes['description'][$language];
                $attributes['currentWorkingTitle'] = $attributes['workingTitle'][$language];
            }
        }

        // shipping type
        $attributes['shippingType'] = false;

        try {
            $attributes['shippingType'] = $this->getShippingType()->toArray();
        } catch (QUI\ERP\Shipping\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // icon
        $attributes['icon'] = '';
        $attributes['icon_path'] = '';

        try {
            $attributes['icon'] = $this->getIcon();
            $attributes['icon_path'] = $this->getAttribute('icon');
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $attributes['price'] = $this->getPrice();

        return $attributes;
    }

    /**
     * Return the shipping as a json array
     *
     * @return string
     */
    public function toJSON(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Return the shipping type of the type
     *
     * @return Api\ShippingTypeInterface
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getShippingType(): Api\ShippingTypeInterface
    {
        $type = $this->getAttribute('shipping_type');

        if (!class_exists($type)) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.found',
                ['shippingType' => $type]
            ]);
        }

        $Type = new $type();

        if (!($Type instanceof Api\ShippingTypeInterface)) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.abstractShipping',
                ['shippingType' => $type]
            ]);
        }

        return $Type;
    }

    /**
     * Return the price display
     *
     * @return string
     */
    public function getPriceDisplay(): string
    {
        $PriceFactor = $this->toPriceFactor();

        $ErpEntity = $this->ErpEntity;
        $isNetto = false;

        if ($ErpEntity) {
            $Customer = $ErpEntity->getCustomer();
            $isNetto = $Customer->isNetto();
        }

        // display is incl vat
        $vat = $PriceFactor->getVat();
        $price = $this->getPrice();

        if (!$isNetto && $vat) {
            $price = $price + ($price * ($vat / 100));
        }


        // if user currency is different to the default, we have to convert the price
        $DefaultCurrency = QUI\ERP\Defaults::getCurrency();
        $UserCurrency = QUI\ERP\Defaults::getUserCurrency();

        if ($UserCurrency && $DefaultCurrency->getCode() !== $UserCurrency->getCode()) {
            try {
                $price = $DefaultCurrency->convert($price, $UserCurrency);
                $Price = new QUI\ERP\Money\Price($price, $UserCurrency);
            } catch (Exception $exception) {
                $Price = new QUI\ERP\Money\Price($price, $DefaultCurrency);
            }
        } else {
            $Price = new QUI\ERP\Money\Price($price, $DefaultCurrency);
        }

        if (!$price) {
            return '';
        }

        $numberAsString = strval($price);
        $exploded = explode('.', $numberAsString);
        $numberOfDecimalPlaces = isset($exploded[1]) ? strlen($exploded[1]) : 0;

        $priceStringTitle = '';
        $priceStringTitle .= QUI::getLocale()->get('quiqqer/shipping', 'shipping.plus');
        $priceStringTitle .= ' ';
        $priceStringTitle .= $Price->getDisplayPrice();

        $priceString = $priceStringTitle;

        if ($numberOfDecimalPlaces > 4) {
            $priceRounded = round($price, 4);
            $PriceDisplay = new QUI\ERP\Money\Price($priceRounded, $Price->getCurrency());

            $priceString = '';
            $priceString .= QUI::getLocale()->get('quiqqer/shipping', 'shipping.plus');
            $priceString .= ' ~';
            $priceString .= $PriceDisplay->getDisplayPrice();
        }

        return '<span title="' . $priceStringTitle . '">' . $priceString . '</span>';
    }

    /**
     * Return the price of the shipping entry
     *
     * @return float|int
     */
    public function getPrice()
    {
        $rules = $this->getShippingRules();
        $price = 0;

        $ErpEntity = $this->ErpEntity;

        foreach ($rules as $Rule) {
            $discount = $Rule->getAttribute('discount');
            $type = $Rule->getDiscountType();

            if ($type === QUI\ERP\Shipping\Rules\Factory::DISCOUNT_TYPE_ABS) {
                $price = $price + $discount;
                continue;
            }

            if ($type === QUI\ERP\Shipping\Rules\Factory::DISCOUNT_TYPE_PC_ORDER && $ErpEntity) {
                try {
                    $ErpEntity = $this->ErpEntity;
                    $Calculation = $ErpEntity->getPriceCalculation();
                    $nettoSum = $Calculation->getNettoSum()->get();

                    if (!$nettoSum) {
                        continue;
                    }

                    $pc = round($nettoSum * ($discount / 100));
                    $price = $price + $pc;

                    continue;
                } catch (Exception $Exception) {
                    QUI\System\Log::addDebug($Exception->getMessage());
                }
            }

            $pc = round($price * ($discount / 100));
            $price = $price + $pc;
        }

        if ($price <= 0) {
            return 0;
        }

        return $price;
    }

    /**
     * is the user allowed to use this shipping
     *
     * @param QUI\Interfaces\Users\User $User
     * @param QUI\ERP\ErpEntityInterface $Entity
     *
     * @return boolean
     */
    public function canUsedBy(
        QUI\Interfaces\Users\User $User,
        QUI\ERP\ErpEntityInterface $Entity
    ): bool {
        if ($this->isActive() === false) {
            return false;
        }

        try {
            $ShippingType = $this->getShippingType();

            if (method_exists($ShippingType, 'canUsedBy')) {
                return $ShippingType->canUsedBy($User, $this, $Entity);
            }
        } catch (Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * is the shipping allowed in this erp entity?
     *
     * @param QUI\ERP\ErpEntityInterface $Entity
     *
     * @return bool
     */
    public function canUsedInErpEntity(QUI\ERP\ErpEntityInterface $Entity): bool
    {
        if ($this->isActive() === false) {
            Debug::addLog($this->getTitle() . ' is not active');

            return false;
        }

        try {
            $ShippingType = $this->getShippingType();

            if (method_exists($ShippingType, 'canUsedIn')) {
                return $ShippingType->canUsedIn($Entity, $this);
            }
        } catch (Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * Activate the shipping type
     *
     * @throws QUI\ExceptionStack|Exception
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
    public function isActive(): bool
    {
        return !!$this->getAttribute('active');
    }

    /**
     * Deactivate the shipping type
     *
     * @throws QUI\ExceptionStack|Exception
     */
    public function deactivate()
    {
        $this->setAttribute('active', 0);
        $this->update();
        $this->refresh();
    }

    //region GETTER

    /**
     * Return the shipping title
     *
     * @param null $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/shipping',
            'shipping.' . $this->getId() . '.title'
        );
    }

    /**
     * Return the shipping description
     *
     * @param null $Locale
     * @return array|string
     */
    public function getDescription($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/shipping',
            'shipping.' . $this->getId() . '.description'
        );
    }

    /**
     * Return the shipping working title
     *
     * @param null $Locale
     * @return array|string
     */
    public function getWorkingTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/shipping',
            'shipping.' . $this->getId() . '.workingTitle'
        );
    }

    /**
     *  Return the icon for the Shipping
     *
     * @return string - image url
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getIcon(): string
    {
        if (!QUI\Projects\Media\Utils::isMediaUrl($this->getAttribute('icon'))) {
            return $this->getShippingType()->getIcon();
        }

        try {
            $Image = QUI\Projects\Media\Utils::getImageByUrl(
                $this->getAttribute('icon')
            );

            return $Image->getSizeCacheUrl();
        } catch (Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $this->getShippingType()->getIcon();
    }

    //endregion

    //region SETTER

    /**
     * Set the title
     *
     * @param array $titles
     */
    public function setTitle(array $titles)
    {
        $this->setShippingLocale(
            'shipping.' . $this->getId() . '.title',
            $titles
        );
    }

    /**
     * Set the description
     *
     * @param array $descriptions
     */
    public function setDescription(array $descriptions)
    {
        $this->setShippingLocale(
            'shipping.' . $this->getId() . '.description',
            $descriptions
        );
    }

    /**
     * Set the working title
     *
     * @param array $titles
     */
    public function setWorkingTitle(array $titles)
    {
        $this->setShippingLocale(
            'shipping.' . $this->getId() . '.workingTitle',
            $titles
        );
    }

    /**
     * @param string $icon - image.php?
     */
    public function setIcon(string $icon)
    {
        if (QUI\Projects\Media\Utils::isMediaUrl($icon)) {
            $this->setAttribute('icon', $icon);
        }
    }

    /**
     * Remove the shipping entry icon
     */
    public function removeIcon()
    {
        $this->setAttribute('icon', false);
    }

    /**
     * Creates a locale
     *
     * @param string $var
     * @param array $title
     */
    protected function setShippingLocale(string $var, array $title)
    {
        $data = [
            'datatype' => 'php,js',
            'package' => 'quiqqer/shipping'
        ];

        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (!isset($title[$language])) {
                continue;
            }

            $data[$language] = $title[$language];
            $data[$language . '_edit'] = $title[$language];
        }

        $exists = Translator::getVarData('quiqqer/shipping', $var, 'quiqqer/shipping');

        try {
            if (empty($exists)) {
                Translator::addUserVar('quiqqer/shipping', $var, $data);
            } else {
                Translator::edit('quiqqer/shipping', $var, 'quiqqer/shipping', $data);
            }
        } catch (Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        try {
            Translator::publish('quiqqer/shipping');
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    //endregion

    //region rules

    /**
     * @param ShippingRule $Rule
     */
    public function addShippingRule(ShippingRule $Rule)
    {
        $shippingRules = $this->getAttribute('shipping_rules');
        $shippingRules = json_decode($shippingRules, true);

        if (!is_array($shippingRules)) {
            $shippingRules = [];
        }

        if (!in_array($Rule->getId(), $shippingRules)) {
            $shippingRules[] = $Rule->getId();
        }

        $this->setAttribute('shipping_rules', json_encode($shippingRules));
    }

    /**
     * Add a shipping rule by its id
     *
     * @param integer $shippingRuleId
     * @throws Exception
     */
    public function addShippingRuleId(int $shippingRuleId)
    {
        /* @var $Rule ShippingRule */
        $Rule = RuleFactory::getInstance()->getChild($shippingRuleId);
        $this->addShippingRule($Rule);
    }

    /**
     * Return the shipping rules of the shipping entry
     *
     * @return ShippingRule[]
     */
    public function getShippingRules(): array
    {
        $shippingRules = $this->getAttribute('shipping_rules');
        $shippingRules = json_decode($shippingRules, true);

        if (!is_array($shippingRules)) {
            return [];
        }

        $debugging = QUI\ERP\Shipping\Shipping::getInstance()->debuggingEnabled();
        $debuggingLog = [];

        // get rules
        $rules = [];
        $Rules = RuleFactory::getInstance();

        foreach ($shippingRules as $shippingRule) {
            try {
                $rules[] = $Rules->getChild($shippingRule);
            } catch (Exception $Exception) {
                QUI\System\Log::addDebug($Exception);
            }
        }

        // sort by priority
        usort($rules, function ($ShippingRuleA, $ShippingRuleB) {
            /* @var $ShippingRuleA ShippingRule */
            /* @var $ShippingRuleB ShippingRule */
            $priorityA = $ShippingRuleA->getPriority();
            $priorityB = $ShippingRuleB->getPriority();

            if ($priorityA === $priorityB) {
                return 0;
            }

            return $priorityA < $priorityB ? -1 : 1;
        });

        // check rules
        $result = [];

        Debug::addLog("### Check Shipping Rules for {$this->getTitle()}");

        foreach ($rules as $Rule) {
            /* @var $Rule ShippingRule */
            if (!$Rule->isValid()) {
                if ($debugging) {
                    Debug::addLog("### {$Rule->getTitle()} is not valid");

                    $debuggingLog[] = [
                        'id' => $Rule->getId(),
                        'title' => $Rule->getTitle(),
                        'reason' => 'is not valid',
                        'valid' => false
                    ];
                }
                continue;
            }

            if ($debugging) {
                Debug::addLog("### {$Rule->getTitle()} is valid");
            }

            if (!$Rule->canUsedIn($this->ErpEntity)) {
                if ($debugging) {
                    Debug::addLog("### {$Rule->getTitle()} can not used in this entity");

                    $debuggingLog[] = [
                        'id' => $Rule->getId(),
                        'title' => $Rule->getTitle(),
                        'reason' => 'is not valid for order',
                        'valid' => false
                    ];
                }

                continue;
            }

            if ($debugging) {
                Debug::addLog("### {$Rule->getTitle()} can used in this entity");
            }

            $result[] = $Rule;

            if ($debugging) {
                $debuggingLog[] = [
                    'id' => $Rule->getId(),
                    'title' => $Rule->getTitle(),
                    'reason' => 'is valid',
                    'valid' => true
                ];
            }


            if ($Rule->noRulesAfter()) {
                if ($debugging) {
                    Debug::addLog("### {$Rule->getTitle()} - no rules after");
                }

                break;
            }
        }

        // debug shipping entry / rules
        if ($debugging && !defined('QUIQQER_AJAX')) {
            QUI\ERP\Shipping\Debug::enable();
            QUI\ERP\Shipping\Debug::generateShippingEntryDebuggingLog($this, $result, $debuggingLog);
            QUI\ERP\Shipping\Debug::disable();
        }

        return $result;
    }

    /**
     * Can the shipping be used basically?
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (!$this->isActive()) {
            Debug::addLog("{$this->getTitle()} :: is not active");

            return false;
        }

        $shippingRules = $this->getAttribute('shipping_rules');
        $shippingRules = json_decode($shippingRules, true);

        if (!is_array($shippingRules)) {
            Debug::addLog("{$this->getTitle()} :: has no rules [OK]");

            return true;
        }

        $rules = $this->getShippingRules();

        // @todo ist das so gewollt?
        // wenn keine rules zugewiesen sind, das der shipping entry nie nutzbar ist?
        if (empty($rules)) {
            Debug::addLog("{$this->getTitle()} :: has no active rules");

            return false;
        }

        Debug::addLog("{$this->getTitle()} :: has active rules [OK]");

        return true;
    }

    //endregion


    /**
     * Set an erp entity to the shipping entry
     * this erp entity is then assigned to the shipping and the validation considers this erp entity
     *
     * @param QUI\ERP\ErpEntityInterface $ErpEntity
     */
    public function setErpEntity(QUI\ERP\ErpEntityInterface $ErpEntity)
    {
        $this->ErpEntity = $ErpEntity;
    }

    /**
     * @deprecated use setErpEntity()
     */
    public function setOrder(QUI\ERP\ErpEntityInterface $ErpEntity)
    {
        $this->setErpEntity($ErpEntity);
    }

    /**
     * @param null $Locale
     * @param QUI\ERP\ErpEntityInterface|null $ErpEntity
     *
     * @return QUI\ERP\Products\Utils\PriceFactor
     */
    public function toPriceFactor(
        $Locale = null,
        QUI\ERP\ErpEntityInterface $ErpEntity = null
    ): QUI\ERP\Products\Utils\PriceFactor {
        if ($ErpEntity === null) {
            $ErpEntity = $this->ErpEntity;
        }

        $price = $this->getPrice();


        // if erp entity currency is different to the default, we have to convert the price
        $EntityCurrency = $ErpEntity->getCurrency();
        $DefaultCurrency = QUI\ERP\Defaults::getCurrency();

        if ($DefaultCurrency->getCode() !== $EntityCurrency->getCode()) {
            try {
                $price = $DefaultCurrency->convert($price, $EntityCurrency);
            } catch (Exception $exception) {
            }
        }

        $PriceFactor = new QUI\ERP\Products\Utils\PriceFactor([
            'identifier' => 'shipping-pricefactor-' . $this->getId(),
            'title' => QUI::getLocale()->get('quiqqer/shipping', 'shipping.order.title', [
                'shipping' => $this->getTitle($Locale)
            ]),
            'description' => '',
            'priority' => 1,
            'calculation' => QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT,
            'basis' => QUI\ERP\Accounting\Calc::CALCULATION_BASIS_CURRENTPRICE,
            'value' => $price,
            'visible' => true,
            'currency' => $EntityCurrency->getCode()
        ]);

        $isEuVatUser = QUI\ERP\Tax\Utils::isUserEuVatUser(
            $ErpEntity->getCustomer()
        );

        if ($isEuVatUser) {
            return $PriceFactor;
        }

        try {
            $PriceFactor->setVat(
                QUI\ERP\Shipping\Shipping::getInstance()->getVat($ErpEntity)
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        return $PriceFactor;
    }

    //region address

    /**
     * @param $Address
     */
    public function setAddress($Address)
    {
        $this->Address = $Address;
    }

    /**
     * Return the address
     */
    public function getAddress()
    {
        return $this->Address;
    }

    //endregion
}
