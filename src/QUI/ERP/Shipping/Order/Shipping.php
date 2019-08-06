<?php

/**
 * This file contains QUI\ERP\Shipping\Shipping
 */

namespace QUI\ERP\Shipping\Order;

use QUI;
use QUI\ERP\Shipping\Shipping as ShippingHandler;

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
        $userShipping = $Shipping->getUserShipping($User);

        $shippingList = [];

        foreach ($userShipping as $ShippingEntry) {
            $ShippingEntry->setOrder($Order);

            if ($ShippingEntry->isValid()
                && $ShippingEntry->canUsedInOrder($Order)
                && $ShippingEntry->canUsedBy($User)) {
                $shippingList[] = $ShippingEntry;
            }
        }

        // send email if empty
        if (empty($shippingList)) {
            QUI\ERP\Shipping\Debug::sendAdminInfoMailAboutEmptyShipping($Order);

            $Package = QUI::getPackage('quiqqer/shipping');
            $Conf    = $Package->getConfig();

            if ((int)$Conf->getValue('no_rules', 'behavior') === ShippingHandler::NO_RULE_FOUND_ORDER_CANCEL) {
                $message = QUI::getLocale()->get(
                    'quiqqer/shipping',
                    'message.no.rule.found.order.cancel',
                    ['adminmail' => QUI::conf('mail', 'admin_mail')]
                );
            } else {
                $message = QUI::getLocale()->get(
                    'quiqqer/shipping',
                    'message.no.rule.found.order.continue'
                );
            }

            $Engine->assign([
                'shippingEmptyMessage' => $message
            ]);
        }

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
        $Order = $this->getOrder();

        $Shipping = $Order->getShipping();
        $User     = $Order->getCustomer();

        // setting rule behavior
        $behavior = ShippingHandler::NO_RULE_FOUND_ORDER_CONTINUE;

        try {
            $Package = QUI::getPackage('quiqqer/shipping');
            $Conf    = $Package->getConfig();

            $behavior = (int)$Conf->getValue('no_rules', 'behavior');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }


        // if no shipping exists, BUT order can be continued
        if ($Shipping === null
            && $behavior === ShippingHandler::NO_RULE_FOUND_ORDER_CANCEL) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.missing.shipping.order.canceled'
            ]);
        }

        // if no shipping exists, BUT order can be continued
        if ($Shipping === null
            && $behavior === ShippingHandler::NO_RULE_FOUND_ORDER_CONTINUE) {
            return;
        }

        if (!$Shipping->canUsedBy($User)) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.shipping.is.not.allowed'
            ]);
        }

        if (!$Shipping->canUsedInOrder($Order)) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.shipping.is.not.allowed'
            ]);
        }
    }

    /**
     * Save the shipping to the order
     *
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
            $this->getOrder()->removeShipping();
            $this->getOrder()->save();

            return;
        }


        $User  = QUI::getUserBySession();
        $Order = $this->getOrder();

        try {
            $Shipping      = QUI\ERP\Shipping\Shipping::getInstance();
            $ShippingEntry = $Shipping->getShippingEntry($shipping);
            $ShippingEntry->setOrder($Order);

            if (!$ShippingEntry->canUsedBy($User)) {
                return;
            }

            if (!$ShippingEntry->canUsedInOrder($Order)) {
                return;
            }
        } catch (QUI\ERP\Shipping\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $Order->setShipping($ShippingEntry);
        $Order->save();
    }
}
