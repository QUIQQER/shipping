<?php

/**
 * This file contains QUI\ERP\Shipping\Shipping
 */

namespace QUI\ERP\Shipping\Order;

use QUI;
use QUI\ERP\Shipping\Shipping as ShippingHandler;

use function array_merge;
use function count;
use function defined;
use function dirname;
use function implode;

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
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Shipping.css');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null): string
    {
        return 'Shipping';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-truck';
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();

        $Order = $this->getOrder();
        $Order->recalculate();

        $SelectedShipping = $Order->getShipping();

        $Customer = $Order->getCustomer();
        $Shipping = QUI\ERP\Shipping\Shipping::getInstance();
        $shippingList = $this->getValidShipping();

        // debugging logger
        if (QUI\ERP\Shipping\Shipping::getInstance()->debuggingEnabled() && !defined('QUIQQER_AJAX')) {
            QUI\ERP\Shipping\Debug::clearLogStock();

            $debugStack = [];
            $debugShipping = $Shipping->getShippingList();
            $Logger = QUI\ERP\Shipping\Debug::getLoggerWithoutFormatter();

            foreach ($debugShipping as $DebugShippingEntry) {
                $DebugShippingEntry->setErpEntity($Order);

                QUI\ERP\Shipping\Debug::enable();
                QUI\ERP\Shipping\Debug::addLog('# ' . $DebugShippingEntry->getTitle());

                if ($DebugShippingEntry->canUsedBy($User, $Order)) {
                    $DebugShippingEntry->isValid();
                    $DebugShippingEntry->canUsedInErpEntity($Order);
                    $DebugShippingEntry->canUsedBy($User, $Order);
                }

                $debugStack = array_merge($debugStack, QUI\ERP\Shipping\Debug::getLogStack());
                $debugStack[] = "";

                QUI\ERP\Shipping\Debug::clearLogStock();
            }

            QUI\ERP\Shipping\Debug::disable();

            $Logger->info("\n\n" . implode("\n", $debugStack));
            $Engine->assign('debug', implode("\n", $debugStack));
        }

        // send email if empty
        if (empty($shippingList)) {
            QUI\ERP\Shipping\Debug::sendAdminInfoMailAboutEmptyShipping($Order);

            $Package = QUI::getPackage('quiqqer/shipping');
            $Conf = $Package->getConfig();

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
            'User' => $User,
            'Customer' => $Customer,
            'SelectedShipping' => $SelectedShipping,
            'shippingList' => $shippingList
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Shipping.html');
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate(): void
    {
        $Order = $this->getOrder();

        $Shipping = $Order->getShipping();
        $User = $Order->getCustomer();

        // setting rule behavior
        $behavior = ShippingHandler::NO_RULE_FOUND_ORDER_CONTINUE;

        try {
            $Package = QUI::getPackage('quiqqer/shipping');
            $Conf = $Package->getConfig();

            $behavior = (int)$Conf->getValue('no_rules', 'behavior');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }


        // if shipping are selectable and no shipping is selected
        $shippingList = $this->getValidShipping();

        if ($Shipping === null && count($shippingList) === 1) {
            try {
                $Order->setShipping($shippingList[0]);
                $Order->save();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }

            $Shipping = $Order->getShipping();
        }


        if ($Shipping === null && !empty($shippingList)) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.no.shipping.selected'
            ]);
        }

        // if no shipping exists, BUT order can be continued
        // and if really no shipping is selectable
        if (
            $Shipping === null
            && $behavior === ShippingHandler::NO_RULE_FOUND_ORDER_CANCEL
        ) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.missing.shipping.order.canceled'
            ]);
        }

        // if no shipping exists, BUT order can be continued
        if (
            $Shipping === null
            && $behavior === ShippingHandler::NO_RULE_FOUND_ORDER_CONTINUE
        ) {
            return;
        }

        if (!$Shipping->canUsedBy($User, $Order)) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.shipping.is.not.allowed'
            ]);
        }

        if (!$Shipping->canUsedInErpEntity($Order)) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/shipping',
                'exception.shipping.is.not.allowed'
            ]);
        }
    }

    /**
     * @return QUI\ERP\Shipping\Types\ShippingEntry[]
     */
    protected function getValidShipping(): array
    {
        return ShippingHandler::getInstance()->getValidShippingEntries($this->getOrder());
    }

    /**
     * Save the shipping to the order
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save(): void
    {
        $shipping = false;

        if (isset($_REQUEST['shipping'])) {
            $shipping = $_REQUEST['shipping'];
        }

        if (empty($shipping) && $this->getAttribute('shipping')) {
            $shipping = $this->getAttribute('shipping');
        }

        $User = QUI::getUserBySession();
        $Order = $this->getOrder();

        try {
            $Shipping = QUI\ERP\Shipping\Shipping::getInstance();
            $ShippingEntry = $Shipping->getShippingEntry($shipping);
            $ShippingEntry->setErpEntity($Order);

            if (!$ShippingEntry->canUsedBy($User, $Order)) {
                return;
            }

            if (!$ShippingEntry->canUsedInErpEntity($Order)) {
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
