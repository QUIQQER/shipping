<?php

/**
 * This file contains QUI\ERP\Shipping\Types\ShippingEntry
 */

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\CRUD\Factory;
use QUI\Translator;
use QUI\Permissions\Permission;

use QUI\ERP\Shipping\Api;
use QUI\ERP\Shipping\Debug;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Rules\ShippingRule;

/**
 * Class ShippingEntry
 * A user created shipping entry
 *
 * @package QUI\ERP\Shipping\Types
 */
class ShippingEntry extends QUI\CRUD\Child implements Api\ShippingInterface
{
    /**
     * @var null
     */
    protected $Order = null;

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

            QUI\Translator::delete('quiqqer/shipping', 'shipping.'.$id.'.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.'.$id.'.description');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.'.$id.'.workingTitle');

            try {
                QUI\Translator::publish('quiqqer/shipping');
            } catch (QUI\Exception $Exception) {
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
                'shipping.'.$id.'.title'
            );

            $attributes['description'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.description'
            );

            $attributes['workingTitle'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'shipping.'.$id.'.workingTitle'
            );
        }

        // shipping type
        $attributes['shippingType'] = false;

        try {
            $attributes['shippingType'] = $this->getShippingType()->toArray();
        } catch (QUI\ERP\Shipping\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // icon
        $attributes['icon']      = '';
        $attributes['icon_path'] = '';

        try {
            $attributes['icon']      = $this->getIcon();
            $attributes['icon_path'] = $this->getAttribute('icon');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $attributes['price'] = $this->getPrice();

        return $attributes;
    }

    /**
     * Return the shipping as an json array
     *
     * @return string
     */
    public function toJSON()
    {
        return \json_encode($this->toArray());
    }

    /**
     * Return the shipping type of the type
     *
     * @return Api\ShippingTypeInterface
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getShippingType()
    {
        $type = $this->getAttribute('shipping_type');

        if (!\class_exists($type)) {
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
    public function getPriceDisplay()
    {
        $Price = new QUI\ERP\Money\Price(
            $this->getPrice(),
            QUI\ERP\Defaults::getCurrency()
        );

        return '+'.$Price->getDisplayPrice();
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

        foreach ($rules as $Rule) {
            $discount = $Rule->getAttribute('discount');
            $type     = $Rule->getDiscountType();

            if ($type === QUI\ERP\Shipping\Rules\Factory::DISCOUNT_TYPE_ABS) {
                $price = $price + $discount;
                continue;
            }

            $pc    = \round($price * ($discount / 100));
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
     *
     * @return boolean
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        if ($this->isActive() === false) {
            return false;
        }

        try {
            $ShippingType = $this->getShippingType();

            if (\method_exists($ShippingType, 'canUsedBy')) {
                return $ShippingType->canUsedBy($User, $this);
            }
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

    /**
     * is the shipping allowed in the order?
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     *
     * @return bool
     */
    public function canUsedInOrder(QUI\ERP\Order\OrderInterface $Order)
    {
        if ($this->isActive() === false) {
            Debug::addLog($this->getTitle().' is not active');

            return false;
        }

        try {
            $ShippingType = $this->getShippingType();

            if (\method_exists($ShippingType, 'canUsedInOrder')) {
                return $ShippingType->canUsedInOrder($Order, $this);
            }
        } catch (QUI\Exception $Exception) {
            return false;
        }

        return true;
    }

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
            'shipping.'.$this->getId().'.title'
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
            'shipping.'.$this->getId().'.description'
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
            'shipping.'.$this->getId().'.workingTitle'
        );
    }

    /**
     *  Return the icon for the Shipping
     *
     * @return string - image url
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getIcon()
    {
        if (!QUI\Projects\Media\Utils::isMediaUrl($this->getAttribute('icon'))) {
            return $this->getShippingType()->getIcon();
        }

        try {
            $Image = QUI\Projects\Media\Utils::getImageByUrl(
                $this->getAttribute('icon')
            );

            return $Image->getSizeCacheUrl();
        } catch (QUI\Exception $Exception) {
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
            'shipping.'.$this->getId().'.title',
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
            'shipping.'.$this->getId().'.description',
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
            'shipping.'.$this->getId().'.workingTitle',
            $titles
        );
    }

    /**
     * @param string $icon - image.php?
     */
    public function setIcon($icon)
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
    protected function setShippingLocale($var, $title)
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

    //region rules

    /**
     * @param ShippingRule $Rule
     */
    public function addShippingRule(ShippingRule $Rule)
    {
        $shippingRules = $this->getAttribute('shipping_rules');
        $shippingRules = \json_decode($shippingRules, true);

        if (!\in_array($Rule->getId(), $shippingRules)) {
            $shippingRules[] = $Rule->getId();
        }

        $this->setAttribute('shipping_rules', \json_encode($shippingRules));
    }

    /**
     * Add a shipping rule by its id
     *
     * @param integer $shippingRuleId
     * @throws QUI\Exception
     */
    public function addShippingRuleId($shippingRuleId)
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
    public function getShippingRules()
    {
        $shippingRules = $this->getAttribute('shipping_rules');
        $shippingRules = \json_decode($shippingRules, true);

        if (!\is_array($shippingRules)) {
            return [];
        }

        $debugging    = QUI\ERP\Shipping\Shipping::getInstance()->debuggingEnabled();
        $debuggingLog = [];

        // get rules
        $rules = [];
        $Rules = RuleFactory::getInstance();

        foreach ($shippingRules as $shippingRule) {
            try {
                $rules[] = $Rules->getChild($shippingRule);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception);
            }
        }

        // sort by priority
        \usort($rules, function ($ShippingRuleA, $ShippingRuleB) {
            /* @var $ShippingRuleA ShippingRule */
            /* @var $ShippingRuleB ShippingRule */
            $priorityA = (int)$ShippingRuleA->getPriority();
            $priorityB = (int)$ShippingRuleB->getPriority();

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
                        'id'     => $Rule->getId(),
                        'title'  => $Rule->getTitle(),
                        'reason' => 'is not valid',
                        'valid'  => false
                    ];
                }
                continue;
            }

            if ($debugging) {
                Debug::addLog("### {$Rule->getTitle()} is valid");
            }

            if (!$Rule->canUsedInOrder($this->Order)) {
                if ($debugging) {
                    Debug::addLog("### {$Rule->getTitle()} can not used in order");

                    $debuggingLog[] = [
                        'id'     => $Rule->getId(),
                        'title'  => $Rule->getTitle(),
                        'reason' => 'is not valid for order',
                        'valid'  => false
                    ];
                }

                continue;
            }

            if ($debugging) {
                Debug::addLog("### {$Rule->getTitle()} can used in order");
            }

            $result[] = $Rule;

            if ($debugging) {
                $debuggingLog[] = [
                    'id'     => $Rule->getId(),
                    'title'  => $Rule->getTitle(),
                    'reason' => 'is valid',
                    'valid'  => true
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
        if ($debugging) {
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
    public function isValid()
    {
        if (!$this->isActive()) {
            Debug::addLog("{$this->getTitle()} :: is not active");

            return false;
        }

        $shippingRules = $this->getAttribute('shipping_rules');
        $shippingRules = \json_decode($shippingRules, true);

        if (!\is_array($shippingRules)) {
            Debug::addLog("{$this->getTitle()} :: has no rules [OK]");

            return true;
        }

        $rules = $this->getShippingRules();

        if (empty($rules)) {
            Debug::addLog("{$this->getTitle()} :: has no active rules");

            return false;
        }

        Debug::addLog("{$this->getTitle()} :: has active rules [OK]");

        return true;
    }

    //endregion

    /**
     * Set an order to the shipping entry
     * this order is then assigned to the shipping and the validation considers this order
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     */
    public function setOrder(QUI\ERP\Order\OrderInterface $Order)
    {
        $this->Order = $Order;
    }

    /**
     * @param null $Locale
     *
     * @return QUI\ERP\Products\Utils\PriceFactor
     */
    public function toPriceFactor($Locale = null)
    {
        $PriceFactor = new QUI\ERP\Products\Utils\PriceFactor([
            'title'       => QUI::getLocale()->get('quiqqer/shipping', 'shipping.order.title', [
                'shipping' => $this->getTitle($Locale)
            ]),
            'description' => '',
            'priority'    => 1,
            'calculation' => QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT,
            'basis'       => QUI\ERP\Accounting\Calc::CALCULATION_BASIS_CURRENTPRICE,
            'value'       => $this->getPrice(),
            'visible'     => true
        ]);

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
