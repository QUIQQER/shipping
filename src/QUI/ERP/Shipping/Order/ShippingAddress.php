<?php

/**
 * This file contains QUI\ERP\Shipping\Order\ShippingAddress
 */

namespace QUI\ERP\Shipping\Order;

use QUI;

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
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(\dirname(__FILE__).'/ShippingAddress.css');
        $this->addCSSClass('quiqqer-shipping-address');
    }

    /**
     * @return string
     */
    public function getBody()
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            return '';
        }

        /* @var $User QUI\Users\User */
        $User = $this->getAttribute('User');

        /* @var $Order QUI\ERP\Order\OrderInterface */
        $Order = $this->getAttribute('Order');

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        $addressList = $User->getAddressList();
        $profileLink = false;

        try {
            $Project = QUI::getRewrite()->getProject();
            $sites   = $Project->getSites([
                'where' => [
                    'type' => 'quiqqer/frontend-users:types/profile'
                ],
                'limit' => 1
            ]);

            if (isset($sites[0])) {
                /* @var $Profile QUI\Projects\Site */
                $Profile     = $sites[0];
                $profileLink = $Profile->getUrlRewritten();
                $profileLink .= '/user/address';
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // current address
        $currentAddress  = '';
        $shippingAddress = $Order->getDataEntry('shipping-address-id');

        if (!empty($shippingAddress)) {
            $currentAddress = $shippingAddress;
        } elseif ($User->getAttribute('quiqqer.shipping.address')) {
            $currentAddress = $User->getAttribute('quiqqer.shipping.address');
        }

        $Engine->assign([
            'addressList'    => $addressList,
            'profileLink'    => $profileLink,
            'currentAddress' => $currentAddress
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ShippingAddress.html');
    }
}
