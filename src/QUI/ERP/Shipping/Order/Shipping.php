<?php

/**
 * This file contains QUI\ERP\Shipping\Shipping
 */

namespace QUI\ERP\Shipping\Order;

use QUI;

/**
 * Class Shipping
 *
 * @package QUI\ERP\Order\Controls
 */
class Shipping extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * Shipping constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(\dirname(__FILE__).'/Shipping.css');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Shipping';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-truck';
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User   = QUI::getUserBySession();

        $Order = $this->getOrder();
        $Order->recalculate();

        $Customer     = $Order->getCustomer();
        $Shipping     = QUI\ERP\Shipping\Shipping::getInstance();
        $shippingList = $Shipping->getUserShipping($User);

        $shippingList = \array_filter($shippingList, function ($Shipping) use ($Order) {
            /* @var $Shipping QUI\ERP\Shipping\Types\ShippingEntry */
            if ($Shipping->canUsedInOrder($Order) === false) {
                return false;
            }

            return $Shipping->getShippingType()->isVisible();
        });


        $Engine->assign([
            'User'             => $User,
            'Customer'         => $Customer,
            'SelectedShipping' => $Shipping,
            'shippingList'     => $shippingList
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/Shipping.html');
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
        $Order   = $this->getOrder();
        $Payment = $Order->getPayment();

        if ($Payment === null) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.missing.shipping'
            ]);
        }

        // @todo validate customer shipping data
    }

    /**
     * Save the shipping to the order
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save()
    {
        $shipping = false;

        if (isset($_REQUEST['shipping'])) {
            $shipping = $_REQUEST['shipping'];
        }

        if (empty($shipping) && $this->getAttribute('shipping')) {
            $shipping = $this->getAttribute('shipping');
        }

        if (empty($shipping)) {
            return;
        }


        $User  = QUI::getUserBySession();
        $Order = $this->getOrder();

        try {
            $Shipping      = QUI\ERP\Shipping\Shipping::getInstance();
            $ShippingEntry = $Shipping->getShippingEntry($shipping);
            $ShippingEntry->canUsedBy($User);
        } catch (QUI\ERP\Shipping\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $Order->setPayment($ShippingEntry->getId());
        $Order->save();
    }
}
