<?php

/**
 * This class contains \QUI\ERP\Shipping\Shipping
 */

namespace QUI\ERP\Shipping;

use QUI;
use QUI\ERP\Shipping\Types\ShippingTypeInterface;
use QUI\ERP\Shipping\Types\Factory;
use QUI\ERP\Shipping\Api\AbstractShippingProvider;
use QUI\ERP\Shipping\Api\AbstractShippingEntry;

/**
 * Shipping
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Shipping extends QUI\Utils\Singleton
{
    /**
     * @var array
     */
    protected $shipping = [];

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
     * @return QUI\ERP\Shipping\Api\AbstractShippingEntry
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
     * @return QUI\ERP\Shipping\Types\ShippingEntry[]
     */
    public function getUserShipping($User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $shipping = \array_filter($this->getShippingList(), function ($Shipping) use ($User) {
            /* @var $Shipping QUI\ERP\Shipping\Types\ShippingEntry */
            return $Shipping->canUsedBy($User);
        });

        return $shipping;
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
        $host = trim($host, '/');

        return $host;
    }

    /**
     * @param QUI\ERP\Order\Order|
     *      QUI\ERP\Order\OrderInProcess|
     *      QUI\ERP\Accounting\Invoice\Invoice|
     *      QUI\ERP\Accounting\Invoice\InvoiceTemporary
     * $Order
     */
    public function getShippingByObject($Object)
    {
    }

    public function getShippingByOrderId($orderId)
    {
    }
}
