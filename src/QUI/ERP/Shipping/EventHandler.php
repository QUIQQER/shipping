<?php

/**
 * This File contains \QUI\ERP\Shipping\EventHandler
 */

namespace QUI\ERP\Shipping;

use QUI;
use \Quiqqer\Engine\Collector;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Shipping
 */
class EventHandler
{
    /**
     * event for on package setup
     *
     * @throws QUI\Exception
     */
    public static function onPackageSetup()
    {
        $languages = QUI\Translator::getAvailableLanguages();

        // create locale
        $var    = 'message.no.rule.found.order.continue';
        $params = [
            'datatype' => 'php,js',
            'package'  => 'quiqqer/shipping'
        ];

        foreach ($languages as $language) {
            $params[$language] = QUI::getLocale()->getByLang(
                $language,
                'quiqqer/shipping',
                $var
            );
        }

        try {
            QUI\Translator::addUserVar('quiqqer/shipping', $var, $params);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // create locale
        $var    = 'message.no.rule.found.order.cancel';
        $params = [
            'datatype' => 'php,js',
            'package'  => 'quiqqer/shipping'
        ];

        foreach ($languages as $language) {
            $params[$language] = QUI::getLocale()->getByLang(
                $language,
                'quiqqer/shipping',
                $var
            );
        }

        try {
            QUI\Translator::addUserVar('quiqqer/shipping', $var, $params);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }

    /**
     * event - on price factor init
     *
     * @param $Basket
     * @param QUI\ERP\Order\AbstractOrder $Order
     * @param QUI\ERP\Products\Product\ProductList $Products
     */
    public static function onQuiqqerOrderBasketToOrderEnd(
        $Basket,
        QUI\ERP\Order\AbstractOrder $Order,
        QUI\ERP\Products\Product\ProductList $Products
    ) {
        $Shipping = $Order->getShipping();

        if (!$Shipping) {
            return;
        }

        $Shipping->setOrder($Order);

        $PriceFactors = $Products->getPriceFactors();
        $PriceFactors->addToEnd($Shipping->toPriceFactor());

        try {
            $Products->recalculation();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $Order->getArticles()->calc();

        if (\method_exists($Order, 'save')) {
            $Order->save();
        }
    }

    /**
     * @param QUI\ERP\Accounting\Payments\Types\Payment $Payment
     * @param QUI\ERP\Order\OrderInterface $Order
     *
     * @throws QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed
     */
    public static function onQuiqqerPaymentCanUsedInOrder(
        QUI\ERP\Accounting\Payments\Types\Payment $Payment,
        QUI\ERP\Order\OrderInterface $Order
    ) {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $Shipping = $Order->getShipping();

        if (!$Shipping) {
            return;
        }

        $payments = $Shipping->getAttribute('payments');

        if (empty($payments)) {
            return;
        }

        $payments = \explode(',', $payments);
        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();

        foreach ($payments as $paymentId) {
            try {
                $ShippingPayment = $Payments->getPayment($paymentId);

                if ($ShippingPayment->getId() === $Payment->getId()) {
                    return;
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        throw new QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed(
            QUI::getLocale()->get('This Payment can not be used, because of the shipping rules')
        );
    }

    /**
     * @param Collector $Collector
     * @param $User
     * @param $Order
     */
    public static function onOrderProcessCustomerDataEnd(
        Collector $Collector,
        $User,
        $Address,
        $Order
    ) {
        $Control = new QUI\ERP\Shipping\Order\ShippingAddress([
            'User'  => $User,
            'Order' => $Order
        ]);

        $Collector->append($Control->create());
    }

    /**
     * @param QUI\ERP\Order\Controls\OrderProcess\CustomerData $CustomerData
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public static function onQuiqqerOrderCustomerDataSave(
        QUI\ERP\Order\Controls\OrderProcess\CustomerData $CustomerData
    ) {
        if (!isset($_REQUEST['shipping-address'])) {
            return;
        }

        // save shipping address
        $Order    = $CustomerData->getOrder();
        $Customer = $Order->getCustomer();

        try {
            $User = QUI::getUsers()->get($Customer->getId());
        } catch (QUI\Exception $Exception) {
            $User = QUI::getUserBySession();
        }

        try {
            $Address = $User->getAddress($_REQUEST['shipping-address']);
        } catch (QUI\Exception $Exception) {
            return;
        }

        $Order->setData('shipping-address', $Address->getAttributes());
        $Order->setData('shipping-address-id', $Address->getId());
        $Order->save();
    }

    /**
     * @param Collector $Collector
     * @param QUI\Users\User $User
     */
    public static function onFrontendUsersAddressTop(
        Collector $Collector,
        QUI\Users\User $User
    ) {
        $ShippingAddress = new QUI\ERP\Shipping\FrontendUsers\ShippingAddressSelect([
            'User' => $User
        ]);

        $Collector->append($ShippingAddress->create());
    }

    /**
     * @param QUI\Users\User $User
     */
    public static function onUserSaveBegin(QUI\Users\User $User)
    {
        $Request = QUI::getRequest()->request;

        $submit  = $Request->get('submit-shipping');
        $address = (int)$Request->get('shipping-address');

        if ($submit === false || !$address) {
            return;
        }

        try {
            $Address = $User->getAddress($address);

            $User->setAttribute('quiqqer.shipping.address', $Address->getId());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }
}
