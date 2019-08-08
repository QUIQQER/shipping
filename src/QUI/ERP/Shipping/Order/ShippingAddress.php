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

        if (!$User) {
            $User = QUI::getUserBySession();
        }

        $addressList = $User->getAddressList();
        $profileLink = '';

        $Engine->assign([
            'addressList' => $addressList,
            'profileLink' => $profileLink
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ShippingAddress.html');
    }
}
