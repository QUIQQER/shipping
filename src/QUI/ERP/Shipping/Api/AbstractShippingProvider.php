<?php

/**
 * This file contains \QUI\ERP\Shipping\Api\AbstractShippingProvider
 */

namespace QUI\ERP\Shipping\Api;

/**
 * Shipping provider
 *
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractShippingProvider
{
    /**
     * Return the shipping types of the provider
     *
     * @return array
     */
    abstract public function getShippingTypes(): array;
}
