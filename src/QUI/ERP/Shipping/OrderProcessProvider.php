<?php

/**
 * This file contains QUI\ERP\Shipping\OrderProcessProvider
 */

namespace QUI\ERP\Shipping;

use QUI;

use QUI\ERP\Shipping\Api\AbstractShippingEntry;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;

/**
 * Class OrderProcessProvider
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class OrderProcessProvider extends AbstractOrderProcessProvider
{
    /**
     * @var null|AbstractShippingEntry
     */
    protected $Shipping = null;

    /**
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $Process
     *
     * @throws \QUI\Exception
     * @throws \QUI\ERP\Order\Exception
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $Process)
    {
        $orderId = null;
        $Order   = null;

        if ($Process->getOrder()) {
            $Order   = $Process->getOrder();
            $orderId = $Order->getId();
        }

        $OrderProcessSteps->append(
            new Order\Shipping([
                'orderId'  => $orderId,
                'Order'    => $Order,
                'priority' => 30
            ])
        );
    }

    /**
     * @param AbstractOrder $Order
     * @param AbstractOrderingStep|null $Step
     * @return string
     */
    public function getDisplay(AbstractOrder $Order, $Step = null)
    {
        if ($this->Shipping === null) {
            return '';
        }

//        try {
//            return $this->Payment->getGatewayDisplay($Order, $Step);
//        } catch (QUI\ERP\Order\ProcessingException $Exception) {
//            $this->hasErrors = true;
//
//            return '<div class="message-error">'.$Exception->getMessage().'</div>';
//        } catch (\Exception $Exception) {
//            QUI\System\Log::writeException($Exception);
//
//            $this->hasErrors = true;
//
//            return '<div class="message-error">'.
//                   QUI::getLocale()->get('quiqqer/order', 'exception.processing.error').
//                   '</div>';
//        }
        return '';
    }
}
