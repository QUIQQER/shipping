<?php

/**
 * This File contains \QUI\ERP\Shipping\EventHandler
 */

namespace QUI\ERP\Shipping;

use QUI;

/**
 * Class EventHandler
 *
 * @package QUI\ERP\Shipping
 */
class EventHandler
{
    /**
     * event - on price factor init
     *
     * @param $Basket
     * @param QUI\ERP\Order\AbstractOrder $Order
     * @param QUI\ERP\Products\Product\ProductList $Products
     */
    public static function onQuiqqerOrderBasketToOrder(
        $Basket,
        QUI\ERP\Order\AbstractOrder $Order,
        QUI\ERP\Products\Product\ProductList $Products
    ) {
        $Shipping = $Order->getShipping();

        if (!$Shipping) {
            return;
        }

        $PriceFactors = $Products->getPriceFactors();
        $PriceFactors->addToEnd($Shipping->toPriceFactor());

        try {
            $Products->recalculation();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }
}
