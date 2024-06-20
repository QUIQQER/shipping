<?php

/**
 * This File contains \QUI\ERP\Shipping\EventHandler
 */

namespace QUI\ERP\Shipping;

use Exception;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Offers\AbstractOffer;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\OrderProcess\Checkout as OrderCheckoutStepControl;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\ERP\SalesOrders\SalesOrder;
use QUI\ERP\Shipping\Shipping as ShippingHandler;
use QUI\Smarty\Collector;

use function array_merge;
use function count;
use function explode;
use function json_decode;
use function method_exists;
use function time;
use function usort;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Shipping
 */
class EventHandler
{
    const DEFAULT_SHIPPING_TIME_KEY = 'add-default-shipping';

    /**
     * event for on package setup
     *
     * @throws QUI\Exception
     */
    public static function onPackageSetup(QUI\Package\Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/shipping') {
            return;
        }

        // Translations
        $languages = QUI\Translator::getAvailableLanguages();
        $StatusFactory = QUI\ERP\Shipping\ShippingStatus\Factory::getInstance();

        // create locale
        $var = 'message.no.rule.found.order.continue';
        $params = [
            'datatype' => 'php,js',
            'package' => 'quiqqer/shipping'
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
        $var = 'message.no.rule.found.order.cancel';
        $params = [
            'datatype' => 'php,js',
            'package' => 'quiqqer/shipping'
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

        // create shipping order status
        $getLocaleTranslations = function ($key) use ($languages) {
            $result = [];

            foreach ($languages as $language) {
                $result[$language] = QUI::getLocale()->getByLang($language, 'quiqqer/order', $key);
            }

            return $result;
        };

        $Handler = QUI\ERP\Shipping\ShippingStatus\Handler::getInstance();
        $list = $Handler->getList();

        if (empty($list)) {
            $StatusFactory->createShippingStatus(1, '#dbb50c', $getLocaleTranslations('processing.status.default.1'));
            $StatusFactory->createShippingStatus(2, '#418e73', $getLocaleTranslations('processing.status.default.2'));
            $StatusFactory->createShippingStatus(3, '#4fd500', $getLocaleTranslations('processing.status.default.3'));
        }

        // Product fields
        self::createProductFields();
    }

    /**
     * event : on admin load footer
     */
    public static function onAdminLoadFooter(): void
    {
        echo '<script src="' . URL_OPT_DIR . 'quiqqer/shipping/bin/backend/load.js"></script>';
    }

    /**
     * event - on price factor init
     *
     * @param $Basket
     * @param AbstractOrder $Order
     * @param QUI\ERP\Products\Product\ProductList $Products
     */
    public static function onQuiqqerOrderBasketToOrderEnd(
        $Basket,
        AbstractOrder $Order,
        QUI\ERP\Products\Product\ProductList $Products
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $Shipping = $Order->getShipping();

        if (!$Shipping) {
            return;
        }

        if (!$Shipping->getPrice()) {
            return;
        }

        $PriceFactors = $Products->getPriceFactors();
        $PriceFactors->addToEnd($Shipping->toPriceFactor(null, $Order));

        try {
            $Products->recalculation();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $Order->getArticles()->calc();

        if (method_exists($Order, 'save')) {
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
    ): void {
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

        $payments = explode(',', $payments);
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
     * @param $Address
     * @param $Order
     */
    public static function onOrderProcessCustomerDataEnd(
        Collector $Collector,
        $User,
        $Address,
        $Order
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        try {
            $Control = new QUI\ERP\Shipping\Order\ShippingAddress([
                'User' => $User,
                'Order' => $Order
            ]);

            $Collector->append($Control->create());
        } catch (Exception $exception) {
            QUI\System\Log::addError($exception->getMessage());
        }
    }

    public static function onQuiqqerOrderOrderProcessCheckoutOutputBefore(
        OrderCheckoutStepControl $Checkout
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $Order = $Checkout->getOrder();

        if (!$Order) {
            return;
        }

        if ($Order->hasDeliveryAddress()) {
            return;
        }

        $SessionUser = QUI::getUserBySession();
        $Customer = $Order->getCustomer();

        if ($SessionUser->getUUID() !== $Customer->getUUID()) {
            return;
        }

        $addressId = $SessionUser->getAttribute('quiqqer.delivery.address');

        if ($addressId) {
            try {
                $DeliveryAddress = $Customer->getAddress($addressId);
                $Order->setDeliveryAddress($DeliveryAddress);
                $Order->save(QUI::getUsers()->getSystemUser());
            } catch (Exception) {
            }
        }
    }

    /**
     * quiqqer/order: onQuiqqerOrderOrderProcessCheckoutOutput
     *
     * @param OrderCheckoutStepControl $Checkout
     * @param string $text
     * @return void
     */
    public static function onQuiqqerOrderOrderProcessCheckoutOutput(
        OrderCheckoutStepControl $Checkout,
        string $text
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $Order = $Checkout->getOrder();

        if (!$Order) {
            return;
        }

        $DeliveryAddress = $Order->getDeliveryAddress();

        if ($DeliveryAddress->getId() === 0 || $DeliveryAddress->getUUID() == 0) {
            $customerId = $Order->getCustomer()->getUUID();
            $Customer = QUI::getUsers()->get($customerId);

            $deliveryAddressId = $Customer->getAttribute('quiqqer.delivery.address');

            if (!empty($deliveryAddressId)) {
                try {
                    $DeliveryAddress = $Customer->getAddress($deliveryAddressId);
                    $ErpDeliveryAddress = new QUI\ERP\Address(
                        json_decode($DeliveryAddress->toJSON(), true),
                        $Order->getCustomer()
                    );

                    $Order->setDeliveryAddress($ErpDeliveryAddress);
                    $Order->save(QUI::getUsers()->getSystemUser());
                } catch (Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }
    }

    /**
     * @param QUI\ERP\Order\Controls\OrderProcess\CustomerData $CustomerData
     *
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public static function onQuiqqerOrderCustomerDataSave(
        QUI\ERP\Order\Controls\OrderProcess\CustomerData $CustomerData
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        if (!isset($_REQUEST['shipping-address'])) {
            return;
        }

        // save shipping address
        $Order = $CustomerData->getOrder();
        $Customer = $Order->getCustomer();

        try {
            $User = QUI::getUsers()->get($Customer->getUUID());
        } catch (QUI\Exception) {
            $User = QUI::getUserBySession();
        }

        // same address like the invoice address
        if ((int)$_REQUEST['shipping-address'] === -1) {
            $Order->setDeliveryAddress($Order->getInvoiceAddress());
            $Order->save();
            return;
        }

        try {
            $Address = $User->getAddress($_REQUEST['shipping-address']);
        } catch (QUI\Exception) {
            $Order->clearAddressDelivery();
            $Order->save();
            return;
        }

        $ErpAddress = new QUI\ERP\Address(
            array_merge($Address->getAttributes(), ['uuid' => $Address->getUUID()])
        );

        $Order->setDeliveryAddress($ErpAddress);
        $Order->save();
    }

    /**
     * @param Collector $Collector
     * @param QUI\Users\User $User
     * @throws Exception
     */
    public static function onFrontendUsersAddressTop(
        Collector $Collector,
        QUI\Users\User $User
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $ShippingAddress = new QUI\ERP\Shipping\FrontendUsers\ShippingAddressSelect([
            'User' => $User
        ]);

        $Collector->append($ShippingAddress->create());
    }

    /**
     * @param QUI\Users\User $User
     */
    public static function onUserSaveBegin(QUI\Users\User $User): void
    {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $Request = QUI::getRequest()->request;

        $submit = $Request->get('submit-shipping');
        $address = (int)$Request->get('shipping-address');

        if (
            isset($_REQUEST['step'])
            && $_REQUEST['step'] === 'Customer'
            && !empty($_REQUEST['shipping-address'])
        ) {
            $address = (int)$_REQUEST['shipping-address'];
        }

        if ($submit === false || !$address) {
            return;
        }

        try {
            $Address = $User->getAddress($address);
            $User->setAttribute('quiqqer.delivery.address', $Address->getId());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if (isset($Address)) {
            QUI\ERP\Utils\User::setUserCurrentAddress($User, $Address);
        }
    }

    /**
     * event: onTemplateGetHeader
     * sets the uer current address
     */
    public static function onTemplateGetHeader(): void
    {
        $User = QUI::getUserBySession();
        $addressId = $User->getAttribute('quiqqer.delivery.address');

        if (!$addressId) {
            return;
        }

        try {
            QUI\ERP\Utils\User::setUserCurrentAddress(
                $User,
                $User->getAddress($addressId)
            );
        } catch (QUI\Exception) {
        }
    }

    /**
     * Create all fixed product fields that quiqqer/shipping provides
     *
     * @return void
     * @throws QUI\Exception
     */
    protected static function createProductFields(): void
    {
        $fields = [
            Shipping::PRODUCT_FIELD_SHIPPING_TIME => [
                'title' => [
                    'de' => 'Lieferzeit',
                    'en' => 'Delivery time'
                ],
                'type' => Shipping::PRODUCT_FIELD_TYPE_SHIPPING_TIME,
                'public' => true,
                'standard' => true
            ]
        ];

        $fieldsCreated = false;

        foreach ($fields as $fieldId => $field) {
            try {
                ProductFields::getField($fieldId);
                continue;
            } catch (Exception) {
                // Field does not exist -> create it
            }

            try {
                ProductFields::createField([
                    'id' => $fieldId,
                    'type' => $field['type'],
                    'titles' => $field['title'],
                    'workingtitles' => $field['title'],
                    'systemField' => 0,
                    'standardField' => !empty($field['standard']) ? 1 : 0,
                    'publicField' => !empty($field['public']) ? 1 : 0,
                    'options' => !empty($field['options']) ? $field['options'] : null
                ]);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            $fieldsCreated = true;
        }

        if ($fieldsCreated) {
            QUI\Translator::publish('quiqqer/products');
        }
    }

    /**
     * Create a shipping information button
     *
     * @param Collector $Collector
     * @param QUI\ERP\Products\Controls\Price $Price
     * @throws QUI\Exception
     */
    public static function onQuiqqerProductsPriceEnd(Collector $Collector, QUI\ERP\Products\Controls\Price $Price): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $enableShippingInfo = !!$Config->getValue('shipping', 'showShippingInfoAfterPrice');

        if (!$enableShippingInfo || !$Price->getAttribute('withVatText')) {
            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();
        $html = $Engine->fetch(dirname(__FILE__) . '/templates/shippingInformation.html');

        $Collector->append($html);
    }

    //region default shipping

    /**
     * event: add default shipping at onQuiqqerOrderFactoryCreate
     *
     * @param AbstractOrder $Order
     * @return void
     */
    public static function onQuiqqerOrderFactoryCreate(AbstractOrder $Order): void
    {
        try {
            $Process = new QUI\ERP\Process($Order->getGlobalProcessId());

            // wenn verknüpfte entities, dann nicht standard versand setzen
            // by mor
            if (count($Process->getEntities()) <= 1) {
                self::addDefaultShipping($Order->getArticles());
                $Order->update(QUI::getUsers()->getSystemUser());
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * event: add default shipping at onQuiqqerInvoiceTemporaryInvoiceCreated
     *
     * @param InvoiceTemporary $TemporaryInvoice
     * @return void
     */
    public static function onQuiqqerInvoiceTemporaryInvoiceCreated(
        InvoiceTemporary $TemporaryInvoice
    ): void {
        if ($TemporaryInvoice->getCustomDataEntry(self::DEFAULT_SHIPPING_TIME_KEY)) {
            return;
        }

        try {
            $Process = new QUI\ERP\Process($TemporaryInvoice->getGlobalProcessId());

            // wenn verknüpfte entities, dann nicht standard versand setzen
            // by mor
            if (count($Process->getEntities()) <= 1) {
                self::addDefaultShipping($TemporaryInvoice->getArticles());
                $TemporaryInvoice->addCustomDataEntry(self::DEFAULT_SHIPPING_TIME_KEY, time());
                $TemporaryInvoice->update(QUI::getUsers()->getSystemUser());
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * event: add default shipping at onQuiqqerOffersCreated
     *
     * @param AbstractOffer $Offer
     * @return void
     */
    public static function onQuiqqerOffersCreated(AbstractOffer $Offer): void
    {
        if ($Offer->getCustomDataEntry(self::DEFAULT_SHIPPING_TIME_KEY)) {
            return;
        }

        try {
            $Process = new QUI\ERP\Process($Offer->getGlobalProcessId());

            // wenn verknüpfte entities, dann nicht standard versand setzen
            // by mor
            if (count($Process->getEntities()) <= 1) {
                self::addDefaultShipping($Offer->getArticles());
                $Offer->addCustomDataEntry(self::DEFAULT_SHIPPING_TIME_KEY, time());
                $Offer->update(QUI::getUsers()->getSystemUser());
            }
        } catch (Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * event: add default shipping at onQuiqqerSalesOrdersCreated
     *
     * @param SalesOrder $Sales
     * @return void
     */
    public static function onQuiqqerSalesOrdersCreated(SalesOrder $Sales): void
    {
        try {
            $Process = new QUI\ERP\Process($Sales->getGlobalProcessId());

            // wenn verknüpfte entities, dann nicht standard versand setzen
            // by mor
            if (count($Process->getEntities()) <= 1) {
                self::addDefaultShipping($Sales->getArticles());
                $Sales->update();
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }
    }

    /**
     * event: addDefaultShipping
     *
     * @param ArticleList $Articles
     * @return void
     * @throws QUI\Exception
     */
    protected static function addDefaultShipping(ArticleList $Articles): void
    {
        if (!QUI::isBackend()) {
            return;
        }

        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $add = $Config->getValue('shipping', 'addDefaultShipping');

        if (empty($add)) {
            return;
        }

        try {
            $PriceFactors = $Articles->getPriceFactors();

            // check if shipping factor exist
            $shippingFactor = null;
            $factors = $PriceFactors->toArray();

            foreach ($factors as $factor) {
                if (str_contains($factor['identifier'], 'shipping-pricefactor-')) {
                    $shippingFactor = $factor;
                    break;
                }
            }

            if (!$shippingFactor) {
                $PriceFactor = Shipping::getInstance()->getDefaultPriceFactor();
                $Articles->addPriceFactor($PriceFactor);
                $Articles->recalculate();
            }
        } catch (QUI\Exception) {
        }
    }

    //endregion

    /**
     * @param AbstractOrder $Order
     * @param array $data
     * @return void
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    public static function onQuiqqerOrderUpdateBegin(
        AbstractOrder $Order,
        array &$data = []
    ): void {
        $Articles = $Order->getArticles();
        $PriceFactors = $Articles->getPriceFactors();

        if (!$PriceFactors->count()) {
            return;
        }

        // check if shipping factor exist
        $shippingFactor = null;
        $factors = $PriceFactors->toArray();
        $Shipping = $Order->getShipping();

        foreach ($factors as $index => $factor) {
            if (str_contains($factor['identifier'], 'shipping-pricefactor-')) {
                $shippingFactor = $factor;
                break;
            }
        }

        if (!$shippingFactor && !$Shipping) {
            return;
        }

        if (!$shippingFactor) {
            return;
        }

        $identifier = $shippingFactor['identifier'];
        $identifier = str_replace('shipping-pricefactor-', '', $identifier);
        $id = $identifier;
        $id = (int)$id;

        if ($Order->getAttribute('__SHIPPING__')) {
            $Shipping = $Order->getAttribute('__SHIPPING__');
            $Order->setShipping($Shipping);

            if ($Shipping->getId() === $id) {
                return;
            }
        }

        if (!$Shipping && isset($index) && $identifier !== 'default') {
            // kill shipping factor
            $PriceFactors->removeFactor($index);
        } elseif ($Shipping && $id !== $Shipping->getId() && isset($index)) {
            // replace shipping
            $Factor = $PriceFactors->getFactor($index);
            $factor = $Factor->toArray();

            $factor['identifier'] = 'shipping-pricefactor-' . $Shipping->getId();
            $factor['title'] = $Shipping->getTitle();

            $PriceFactors->setFactor(
                $index,
                new QUI\ERP\Accounting\PriceFactors\Factor($factor)
            );

            $data['articles'] = $Articles->toJSON();
        } elseif ($Shipping && !isset($index)) {
            $PriceFactors->addFactor($Shipping->toPriceFactor()->toErpPriceFactor());
        } elseif (!$Shipping && isset($index) && $identifier === 'default') {
            self::onQuiqqerCustomerChange($Order);
        }
    }

    /**
     * @throws QUI\ERP\Exception
     */
    public static function onQuiqqerCustomerChange(QUI\ERP\ErpEntityInterface $ErpEntity): void
    {
        try {
            if (!QUI::getPackage('quiqqer/shipping')->getConfig()->get('shipping', 'considerCustomerCountry')) {
                return;
            }
        } catch (Exception) {
            return;
        }

        $Articles = $ErpEntity->getArticles();
        $PriceFactors = $Articles->getPriceFactors();
        $shippingEntries = ShippingHandler::getInstance()->getValidShippingEntries($ErpEntity);

        if (empty($shippingEntries)) {
            return;
        }

        // sort by price
        usort($shippingEntries, function ($ShippingEntryA, $ShippingEntryB) {
            $priorityA = $ShippingEntryA->getAttribute('priority');
            $priorityB = $ShippingEntryB->getAttribute('priority');

            if ($priorityA === $priorityB) {
                return 0;
            }

            return $priorityA < $priorityB ? -1 : 1;
        });


        /* @var $PriceFactor QUI\ERP\Accounting\PriceFactors\Factor */
        foreach ($PriceFactors as $index => $PriceFactor) {
            if (!str_contains($PriceFactor->getIdentifier(), 'shipping-pricefactor-')) {
                continue;
            }

            $ShippingEntry = null;

            // set the shipping for the order
            if ($ErpEntity instanceof AbstractOrder) {
                foreach ($shippingEntries as $Entry) {
                    try {
                        $ErpEntity->setShipping($Entry);
                        $ErpEntity->setAttribute('__SHIPPING__', $Entry);
                        $ShippingEntry = $Entry;
                        break;
                    } catch (QUI\Exception) {
                    }
                }
            } else {
                $ShippingEntry = $shippingEntries[0];
            }

            if ($ShippingEntry) {
                $PriceFactor = $ShippingEntry->toPriceFactor(QUI::getLocale(), $ErpEntity);
                $PriceFactors->setFactor($index, $PriceFactor->toErpPriceFactor());
            }

            return;
        }
    }
}
