<?php

/**
 * This file contains QUI\ERP\Shipping\FrontendUsers\ShippingAddress
 */

namespace QUI\ERP\Shipping\FrontendUsers;

use QUI;

use function dirname;

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
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setAttribute('nodeName', 'section');
        $this->addCSSFile(dirname(__FILE__) . '/ShippingAddressSelect.css');
        $this->addCSSClass('quiqqer-shipping-user-address');
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $addresses = [];
        $current = '';
        $User = $this->getAttribute('User');

        if ($User) {
            $addresses = $User->getAddressList();
            $current = $User->getAttribute('quiqqer.delivery.address');
        }

        $Engine->assign([
            'addresses' => $addresses,
            'current' => $current
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/ShippingAddressSelect.html');
    }
}
