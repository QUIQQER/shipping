<?php

/**
 * This class contains \QUI\ERP\Shipping\Shipping
 */

namespace QUI\ERP\Shipping;

use QUI;
use QUI\ERP\Shipping\Types\Factory;
use QUI\ERP\Shipping\Api\AbstractShippingProvider;

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
    protected $shipping = [];

    /**
     * @var bool
     */
    protected $debugging = null;

    /**
     * @var null
     */
    protected $shippingDisabled = null;

    /**
     * Return all available shipping provider
     *
     * @return array
     */
    public function getShippingProviders()
    {
        $cacheProvider = 'package/quiqqer/shipping/provider';

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception $Exception) {
            $packages = \array_map(function ($package) {
                return $package['name'];
            }, QUI::getPackageManager()->getInstalled());

            $providers = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = array_merge($providers, $Package->getProvider('shipping'));
                    }
                } catch (QUI\Exception $Exception) {
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
            if (!\class_exists($provider)) {
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
    public function shippingDisabled()
    {
        if ($this->shippingDisabled !== null) {
            return $this->shippingDisabled;
        }

        try {
            $Config                 = QUI::getPackage('quiqqer/shipping')->getConfig();
            $this->shippingDisabled = !!$Config->getValue('shipping', 'disabled');
        } catch (QUI\Exception $Exception) {
            $this->shippingDisabled = false;
        }

        return $this->shippingDisabled;
    }

    /**
     * Is shipping debugging enabled?
     *
     * @return bool
     */
    public function debuggingEnabled()
    {
        if ($this->debugging !== null) {
            return $this->debugging;
        }

        try {
            $Config          = QUI::getPackage('quiqqer/shipping')->getConfig();
            $this->debugging = !!$Config->getValue('shipping', 'debug');
        } catch (QUI\Exception $Exception) {
            $this->debugging = false;
        }

        return $this->debugging;
    }

    /**
     * Return all available Shipping methods
     *
     * @return array
     */
    public function getShippingTypes()
    {
        $shipping  = [];
        $providers = $this->getShippingProviders();

        foreach ($providers as $Provider) {
            $types = $Provider->getShippingTypes();

            foreach ($types as $type) {
                if (!\class_exists($type)) {
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
    public function getShippingType($shippingType)
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
    public function getShippingEntry($shippingId)
    {
        try {
            return Factory::getInstance()->getChild($shippingId);
        } catch (QUI\Exception $Exception) {
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
    public function getShippingList($queryParams = [])
    {
        if (!isset($queryParams['order'])) {
            $queryParams['order'] = 'priority ASC';
        }

        try {
            return Factory::getInstance()->getChildren($queryParams);
        } catch (QUi\Exception $Exception) {
            return [];
        }
    }

    /**
     * Return all shipping entries for the user
     *
     * @param \QUI\Interfaces\Users\User|null $User - optional
     * @param QUI\ERP\Order\AbstractOrder $Order - optional
     * @return QUI\ERP\Shipping\Types\ShippingEntry[]
     */
    public function getUserShipping($User = null, $Order = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        if ($Order === null) {
            return [];
        }

        return \array_filter($this->getShippingList(), function ($Shipping) use ($User, $Order) {
            /* @var $Shipping QUI\ERP\Shipping\Types\ShippingEntry */
            if ($Shipping->isActive() === false) {
                return false;
            }

            return $Shipping->canUsedBy($User, $Order);
        });
    }

    /**
     * Return the unit field ids, for the shipping rule definition
     *
     * @return array
     */
    public function getShippingRuleUnitFieldIds()
    {
        try {
            $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        } catch (QUI\Exception $Exception) {
            return [QUI\ERP\Products\Handler\Fields::FIELD_WEIGHT];
        }

        $ids = $Config->getValue('shipping', 'ruleFields');

        if (empty($ids)) {
            return [QUI\ERP\Products\Handler\Fields::FIELD_WEIGHT];
        }

        return \explode(',', $ids);
    }

    /**
     * @return bool|string
     */
    public function getHost()
    {
        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception $Exception) {
            try {
                $Project = QUI::getProjectManager()->getStandard();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return '';
            }
        }

        $host = $Project->getVHost(true, true);
        $host = \trim($host, '/');

        return $host;
    }

    /**
     * @param QUI\ERP\Order\Order|
     *      QUI\ERP\Order\OrderInProcess|
     *      QUI\ERP\Accounting\Invoice\Invoice|
     *      QUI\ERP\Accounting\Invoice\InvoiceTemporary
     * $Order
     *
     * @return QUI\ERP\Shipping\Types\ShippingEntry|QUI\ERP\Shipping\Types\ShippingUnique
     */
    public function getShippingByObject($Object)
    {
        if (!($Object instanceof QUI\ERP\Order\Order) &&
            !($Object instanceof QUI\ERP\Order\OrderInProcess) &&
            !($Object instanceof QUI\ERP\Accounting\Invoice\Invoice) &&
            !($Object instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary)
        ) {
            return null;
        }

        $Shipping = $Object->getShipping();
        $Delivery = $Object->getDeliveryAddress();

        if ($Delivery && $Shipping) {
            $Shipping->setAddress($Delivery);
        }

        return $Shipping;
    }

    /**
     * @param $orderId
     * @return QUI\ERP\Shipping\Types\ShippingEntry|QUI\ERP\Shipping\Types\ShippingUnique
     */
    public function getShippingByOrderId($orderId)
    {
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById($orderId);
        } catch (QUI\Exception $Exception) {
            return null;
        }

        return $this->getShippingByObject($Order);
    }


    /**
     * Notify customer about an Order status change (via e-mail)
     *
     * @param QUI\ERP\Order\AbstractOrder $Order
     * @param int $statusId
     * @param string $message (optional) - Custom notification message [default: default status change message]
     * @return void
     *
     * @throws QUI\Exception
     */
    public function sendStatusChangeNotification(
        QUI\ERP\Order\AbstractOrder $Order,
        $statusId,
        $message = null
    ) {
        $Customer      = $Order->getCustomer();
        $customerEmail = $Customer->getAttribute('email');

        if (empty($customerEmail)) {
            QUI\System\Log::addWarning(
                'Status change notification for order #'.$Order->getPrefixedId().' cannot be sent'
                .' because customer #'.$Customer->getId().' has no e-mail address.'
            );

            return;
        }

        if (empty($message)) {
            $Status  = ShippingStatus\Handler::getInstance()->getShippingStatus($statusId);
            $message = $Status->getStatusChangeNotificationText($Order);
        }

        $Mailer = new QUI\Mail\Mailer();
        /** @var QUI\Locale $Locale */
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
