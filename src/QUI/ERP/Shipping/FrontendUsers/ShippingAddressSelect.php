<?php

/**
 * This file contains QUI\ERP\Shipping\FrontendUsers\ShippingAddress
 */

namespace QUI\ERP\Shipping\FrontendUsers;

use QUI;

/**
 * Class Shipping
 *
 * @package QUI\ERP\Order\Controls
 */
class ShippingAddressSelect extends QUI\Control
{
    /**
     * Shipping constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttribute('nodeName', 'section');
        $this->addCSSFile(\dirname(__FILE__).'/ShippingAddressSelect.css');
        $this->addCSSClass('quiqqer-shipping-user-address');
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

        $addresses = [];
        $current   = '';
        $User      = $this->getAttribute('User');

        if ($User) {
            /* @var $User QUI\Users\User */
            $addresses = $User->getAddressList();
            $current   = (int)$User->getAttribute('quiqqer.shipping.address');
        }

        $Engine->assign([
            'addresses' => $addresses,
            'current'   => $current
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ShippingAddressSelect.html');
    }
}
