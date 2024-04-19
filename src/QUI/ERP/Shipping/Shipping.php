<?php

/**
 * This class contains \QUI\ERP\Shipping\Shipping
 */

namespace QUI\ERP\Shipping;

use QUI;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Products\Utils\PriceFactor;
use QUI\ERP\Shipping\Api\AbstractShippingProvider;
use QUI\ERP\Shipping\Types\Factory;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\ERP\Shipping\Types\ShippingUnique;
use QUI\Interfaces\Users\User;
use QUI\ERP\Accounting\PriceFactors\Factor as ErpPriceFactor;
use QUI\ERP\Products\Interfaces\PriceFactorInterface;

use function array_filter;
use function array_keys;
use function array_map;
use function class_exists;
use function count;
use function explode;
use function key;
use function max;
use function method_exists;
use function trim;

/**
 * Shipping
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Shipping extends QUI\Utils\Singleton
{
    /**
     * Product fields provided by quiqqer/shipping
     */
    const PRODUCT_FIELD_SHIPPING_TIME = 300;

    /**
     * Product field types provided by quiqqer/shipping
     */
    const PRODUCT_FIELD_TYPE_SHIPPING_TIME = 'shipping.ShippingTimePeriod';

    /**
     * Continue order if no rule was found
     */
    const NO_RULE_FOUND_ORDER_CONTINUE = 1;

    /**
     * Cancel order if no rule was found
     */
    const NO_RULE_FOUND_ORDER_CANCEL = 0;

    /**
     * @var array
     */
    protected array $shipping = [];

    /**
     * @var bool
     */
    protected ?bool $debugging = null;

    /**
     * @var bool|null
     */
    protected ?bool $shippingDisabled = null;

    /**
     * Return all available shipping provider
     *
     * @return array
     */
    public function getShippingProviders(): array
    {
        $cacheProvider = 'package/quiqqer/shipping/provider';

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception) {
            $packages = array_map(function ($package) {
                return $package['name'];
            }, QUI::getPackageManager()->getInstalled());

            $providers = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = array_merge($providers, $Package->getProvider('shipping'));
                    }
                } catch (QUI\Exception) {
                }
            }

            try {
                QUI\Cache\Manager::set($cacheProvider, $providers);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // filter provider
        $result = [];

        foreach ($providers as $provider) {
            if (!class_exists($provider)) {
                continue;
            }

            $Provider = new $provider();

            if (!($Provider instanceof AbstractShippingProvider)) {
                continue;
            }

            $result[] = $Provider;
        }

        return $result;
    }

    /**
     * Is the shipping module disabled?
     *
     * @return bool
     */
    public function shippingDisabled(): bool
    {
        if ($this->shippingDisabled !== null) {
            return $this->shippingDisabled;
        }

        try {
            $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
            $this->shippingDisabled = !!$Config->getValue('shipping', 'deactivated');
        } catch (QUI\Exception) {
            $this->shippingDisabled = false;
        }

        return $this->shippingDisabled;
    }

    /**
     * Is shipping debugging enabled?
     *
     * @return bool
     */
    public function debuggingEnabled(): bool
    {
        if ($this->debugging !== null) {
            return $this->debugging;
        }

        try {
            $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
            $this->debugging = !!$Config->getValue('shipping', 'debug');
        } catch (QUI\Exception) {
            $this->debugging = false;
        }

        return $this->debugging;
    }

    /**
     * Return all available Shipping methods
     *
     * @return array
     */
    public function getShippingTypes(): array
    {
        $shipping = [];
        $providers = $this->getShippingProviders();

        foreach ($providers as $Provider) {
            $types = $Provider->getShippingTypes();

            foreach ($types as $type) {
                if (!class_exists($type)) {
                    continue;
                }

                $ShippingType = new $type();

                if ($ShippingType instanceof QUI\ERP\Shipping\Api\ShippingTypeInterface) {
                    $shipping[$ShippingType->getType()] = $ShippingType;
                }
            }
        }

        return $shipping;
    }

    /**
     * Return a wanted shipping type
     *
     * @param string $shippingType - type of the shipping type
     * @return QUI\ERP\Shipping\Api\ShippingTypeInterface
     * @throws Exception
     */
    public function getShippingType(string $shippingType): Api\ShippingTypeInterface
    {
        if (empty($shippingType)) {
            throw new Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.found',
                ['shippingType' => '']
            ]);
        }

        $types = $this->getShippingTypes();

        /* @var $Shipping QUI\ERP\Shipping\Api\ShippingTypeInterface */
        foreach ($types as $Shipping) {
            if ($Shipping->getType() === $shippingType) {
                return $Shipping;
            }
        }

        throw new Exception([
            'quiqqer/shipping',
            'exception.shipping.type.not.found',
            ['shippingType' => $shippingType]
        ]);
    }

    /**
     * Return a shipping
     *
     * @param int|string $shippingId - ID of the shipping type
     * @return QUI\ERP\Shipping\Types\ShippingEntry
     *
     * @throws Exception
     */
    public function getShippingEntry(int|string $shippingId): Types\ShippingEntry
    {
        try {
            return Factory::getInstance()->getChild($shippingId);
        } catch (QUI\Exception) {
            throw new Exception([
                'quiqqer/shipping',
                'exception.shipping.not.found'
            ]);
        }
    }

    /**
     * Return all active shipping
     *
     * @param array $queryParams
     * @return QUI\ERP\Shipping\Types\ShippingEntry[]
     */
    public function getShippingList(array $queryParams = []): array
    {
        if (!isset($queryParams['order'])) {
            $queryParams['order'] = 'priority ASC';
        }

        try {
            return Factory::getInstance()->getChildren($queryParams);
        } catch (QUi\Exception) {
            return [];
        }
    }

    /**
     * Return all shipping entries for the user
     *
     * @param User|null $User - optional
     * @param QUI\ERP\ErpEntityInterface|null $Entity - optional
     * @return QUI\ERP\Shipping\Types\ShippingEntry[]
     */
    public function getUserShipping(User $User = null, QUI\ERP\ErpEntityInterface $Entity = null): array
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        if ($Entity === null) {
            return [];
        }

        return array_filter($this->getShippingList(), function ($Shipping) use ($User, $Entity) {
            if ($Shipping->isActive() === false) {
                return false;
            }

            return $Shipping->canUsedBy($User, $Entity);
        });
    }

    /**
     * Return the shipping price factor of an erp entity
     *
     * @param QUI\ERP\ErpEntityInterface $Entity
     * @return PriceFactorInterface|ErpPriceFactor|null
     */
    public function getShippingPriceFactor(QUI\ERP\ErpEntityInterface $Entity): ErpPriceFactor|PriceFactorInterface|null
    {
        $PriceFactors = $Entity->getArticles()->getPriceFactors();

        foreach ($PriceFactors as $PriceFactor) {
            if (str_contains($PriceFactor->getIdentifier(), 'shipping-pricefactor')) {
                return $PriceFactor;
            }
        }

        return null;
    }

    /**
     * @param AbstractOrder $Order
     * @return PriceFactorInterface|ErpPriceFactor|null
     *
     * @deprecated use getShippingPriceFactor
     */
    public function getShippingPriceFactorByOrder(AbstractOrder $Order): ErpPriceFactor|PriceFactorInterface|null
    {
        QUI\System\Log::addNotice(
            'Shipping->getShippingPriceFactorByOrder() is deprecated, use getShippingPriceFactor'
        );
        return $this->getShippingPriceFactor($Order);
    }

    /**
     * Get all valid shipping entries for an erp entity
     *
     * @param QUI\ERP\ErpEntityInterface $Entity
     * @return QUI\ERP\Shipping\Types\ShippingEntry[]
     */
    public function getValidShippingEntries(QUI\ERP\ErpEntityInterface $Entity): array
    {
        $User = $Entity->getCustomer();

        $userShipping = QUI\ERP\Shipping\Shipping::getInstance()->getUserShipping($User, $Entity);
        $shippingList = [];

        foreach ($userShipping as $ShippingEntry) {
            $ShippingEntry->setErpEntity($Entity);

            if (
                $ShippingEntry->isValid()
                && $ShippingEntry->canUsedInErpEntity($Entity)
                && $ShippingEntry->canUsedBy($User, $Entity)
            ) {
                $shippingList[] = $ShippingEntry;
            }
        }

        return $shippingList;
    }

    /**
     * @param AbstractOrder $Order
     * @return Types\ShippingEntry[]
     * @deprecated use getValidShippingEntries
     */
    public function getValidShippingEntriesByOrder(AbstractOrder $Order): array
    {
        QUI\System\Log::addNotice(
            'Shipping->getValidShippingEntriesByOrder() is deprecated, use getValidShippingEntries'
        );
        return $this->getValidShippingEntries($Order);
    }

    /**
     * Return the unit field ids, for the shipping rule definition
     *
     * @return array
     */
    public function getShippingRuleUnitFieldIds(): array
    {
        try {
            $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        } catch (QUI\Exception) {
            return [QUI\ERP\Products\Handler\Fields::FIELD_WEIGHT];
        }

        $ids = $Config->getValue('shipping', 'ruleFields');

        if (empty($ids)) {
            return [QUI\ERP\Products\Handler\Fields::FIELD_WEIGHT];
        }

        return explode(',', $ids);
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception) {
            try {
                $Project = QUI::getProjectManager()->getStandard();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return '';
            }
        }

        return trim(
            $Project->getVHost(true, true),
            '/'
        );
    }

    /**
     * @param ErpEntityInterface $Entity
     * @return ShippingEntry|ShippingUnique|null
     */
    public function getShippingByObject(
        QUI\ERP\ErpEntityInterface $Entity
    ): Types\ShippingEntry|Types\ShippingUnique|null {
        $Shipping = null;
        $Delivery = $Entity->getDeliveryAddress();

        if (method_exists($Entity, 'getShipping')) {
            $Shipping = $Entity->getShipping();
        }

        if ($Delivery && $Shipping) {
            $Shipping->setAddress($Delivery);
        }

        return $Shipping;
    }

    /**
     * @param $orderId
     * @return ShippingEntry|ShippingUnique|null
     */
    public function getShippingByOrderId($orderId): ShippingEntry|ShippingUnique|null
    {
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById($orderId);
        } catch (QUI\Exception) {
            return null;
        }

        return $this->getShippingByObject($Order);
    }

    /**
     * @return PriceFactor
     * @throws \QUI\Exception
     */
    public function getDefaultPriceFactor(): PriceFactor
    {
        $price = QUI::getPackage('quiqqer/shipping')
            ->getConfig()
            ->getValue('shipping', 'defaultShippingPrice');

        $price = QUI\ERP\Money\Price::validatePrice($price);

        $PriceFactor = new PriceFactor([
            'identifier' => 'shipping-pricefactor-default',
            'title' => QUI::getLocale()->get('quiqqer/shipping', 'shipping.default.pricefactor'),
            'description' => '',
            'calculation' => QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT,
            'basis' => QUI\ERP\Accounting\Calc::CALCULATION_BASIS_CURRENTPRICE,
            'value' => $price,
            'visible' => true,
            'currency' => QUI\ERP\Defaults::getCurrency()->getCode()
        ]);

        $PriceFactor->setNettoSum($price);

        // set default vat
        $Area = QUI\ERP\Defaults::getArea();
        $TaxType = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
        $TaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($TaxType, $Area);
        $PriceFactor->setVat($TaxEntry->getValue());

        return $PriceFactor;
    }

    /**
     * Returns the var of an order
     *
     * @param QUI\ERP\ErpEntityInterface $ErpEntity
     * @return float|int|mixed|string|null
     * @throws \QUI\Exception
     */
    public function getVat(QUI\ERP\ErpEntityInterface $ErpEntity): mixed
    {
        /* @var $Article QUI\ERP\Accounting\Article */
        $Articles = $ErpEntity->getArticles();
        $vats = [];

        foreach ($Articles as $Article) {
            $vat = $Article->getVat();
            $price = $Article->getPrice()->getValue();

            if (!isset($vats[(string)$vat])) {
                $vats[(string)$vat] = 0;
            }

            $vats[(string)$vat] = $vats[(string)$vat] + $price;
        }

        // @todo implement VAT setting for shipping
        // look at vat, which vat should be used
        if (!count($vats) && !$ErpEntity->getCustomer()) {
            // use default vat
            $Area = QUI\ERP\Defaults::getArea();
            $TaxType = QUI\ERP\Tax\Utils::getTaxTypeByArea($Area);
            $TaxEntry = QUI\ERP\Tax\Utils::getTaxEntry($TaxType, $Area);

            return $TaxEntry->getValue();
        }

        if (!count($vats) && $ErpEntity->getCustomer()) {
            $Tax = QUI\ERP\Tax\Utils::getTaxByUser($ErpEntity->getCustomer());
            return $Tax->getValue();
        }

        if (count($vats) === 1) {
            // use article vat
            return key($vats);
        }

        if (count($vats)) {
            // get max, use the max VAT if multiple exists
            return max(array_keys($vats));
        }

        return 0;
    }

    /**
     * Notify customer about an Order status change (via e-mail)
     *
     * @param QUI\ERP\Order\AbstractOrder $Order
     * @param int $statusId
     * @param string|null $message (optional) - Custom notification message [default: default status change message]
     * @return void
     *
     * @throws QUI\Exception
     */
    public function sendStatusChangeNotification(
        AbstractOrder $Order,
        int $statusId,
        string $message = null
    ): void {
        $Customer = $Order->getCustomer();
        $customerEmail = $Customer->getAttribute('email');

        if (empty($customerEmail)) {
            QUI\System\Log::addWarning(
                'Status change notification for order #' . $Order->getPrefixedId() . ' cannot be sent'
                . ' because customer #' . $Customer->getUUID() . ' has no e-mail address.'
            );

            return;
        }

        if (empty($message)) {
            $Status = ShippingStatus\Handler::getInstance()->getShippingStatus($statusId);
            $message = $Status->getStatusChangeNotificationText($Order);
        }

        $Mailer = new QUI\Mail\Mailer();
        $Locale = $Order->getCustomer()->getLocale();

        $Mailer->setSubject(
            $Locale->get('quiqqer/shipping', 'shipping.status.notification.subject', [
                'orderNo' => $Order->getPrefixedId()
            ])
        );

        $Mailer->setBody($message);
        $Mailer->addRecipient($customerEmail);

        try {
            $Mailer->send();
            $Order->addStatusMail($message);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
