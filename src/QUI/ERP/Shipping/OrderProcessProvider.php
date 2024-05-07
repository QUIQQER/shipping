<?php

/**
 * This file contains QUI\ERP\Shipping\OrderProcessProvider
 */

namespace QUI\ERP\Shipping;

use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use QUI\ERP\Shipping\Api\AbstractShippingEntry;

use function is_bool;

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
    protected ?AbstractShippingEntry $Shipping = null;

    /**
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $Process
     *
     * @throws \QUI\Exception
     * @throws \QUI\ERP\Order\Exception
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $Process): void
    {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        $orderId = null;
        $Order = null;

        if ($Process->getOrder()) {
            $Order = $Process->getOrder();
            $orderId = $Order->getId();
        }


        try {
            $result = QUI::getEvents()->fireEvent(
                'shippingOrderProcessSteps',
                [$this, $OrderProcessSteps, $Process, $Order]
            );

            if (!empty($result)) {
                foreach ($result as $entry) {
                    if (is_bool($entry) && $entry === false) {
                        return;
                    }
                }
            }
        } catch (\Exception) {
        }

        $OrderProcessSteps->append(
            new Order\Shipping([
                'orderId' => $orderId,
                'Order' => $Order,
                'priority' => 25
            ])
        );
    }

    /**
     * @param AbstractOrder $Order
     * @param AbstractOrderingStep|null $Step
     * @return string
     */
    public function getDisplay(AbstractOrder $Order, $Step = null): string
    {
        return '';
    }
}
