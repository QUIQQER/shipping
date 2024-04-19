<?php

/**
 * This file contains QUI\ERP\Shipping\Order\ShippingAddress
 */

namespace QUI\ERP\Shipping\Order;

use QUI;

use function dirname;

/**
 * Class Shipping
 *
 * @package QUI\ERP\Order\Controls
 */
class ShippingAddress extends QUI\Control
{
    /**
     * Shipping constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/ShippingAddress.css');
        $this->addCSSClass('quiqqer-shipping-address');
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = $this->getAttribute('User');
        $Order = $this->getAttribute('Order');

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        $addressList = $User->getAddressList();
        $profileLink = false;

        try {
            $Project = QUI::getRewrite()->getProject();
            $sites = $Project->getSites([
                'where' => [
                    'type' => 'quiqqer/frontend-users:types/profile'
                ],
                'limit' => 1
            ]);

            if (isset($sites[0])) {
                /* @var $Profile QUI\Projects\Site */
                $Profile = $sites[0];
                $profileLink = $Profile->getUrlRewritten();
                $profileLink .= '/user/address';
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // current address
        $currentAddress = '';

        $Shipping = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
        $ShippingAddress = $Shipping?->getAddress();

        if ($ShippingAddress) {
            $currentAddress = $ShippingAddress->getUUID();
        } elseif ($User->getAttribute('quiqqer.delivery.address')) {
            $currentAddress = $User->getAttribute('quiqqer.delivery.address');
        }

        $Engine->assign([
            'addressList' => $addressList,
            'profileLink' => $profileLink,
            'currentAddress' => $currentAddress
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/ShippingAddress.html');
    }
}
