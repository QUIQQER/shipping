<?php

/**
 * This file contains \QUI\ERP\Shipping\Api\AbstractShippingEntry
 */

namespace QUI\ERP\Shipping\Api;

use QUI;

/**
 * Shipping abstract class
 * This is the parent shipping class for all shipping methods
 *
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractShippingEntry implements ShippingInterface
{
    /**
     * shipping fields - extra fields for the shipping / accounting
     *
     * @var array
     */
    protected $shippingFields = [];

    /**
     * default settings
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Locale object for the shipping
     *
     * @var QUI\Locale
     */
    protected $Locale = null;

    /**
     * Set the locale object to the shipping
     *
     * @param QUI\Locale $Locale
     */
    public function setLocale(QUI\Locale $Locale)
    {
        $this->Locale = $Locale;
    }

    /**
     * Return the Locale of the shipping
     *
     * @return QUI\Locale
     */
    public function getLocale()
    {
        if ($this->Locale === null) {
            $this->Locale = QUI::getLocale();
        }

        return $this->Locale;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return \md5(\get_class($this));
    }

    /**
     * Return the class of the instance
     *
     * @return string
     */
    public function getClass()
    {
        return \get_class($this);
    }

    /**
     * @return string
     */
    abstract public function getTitle();

    /**
     * @return string
     */
    abstract public function getDescription();

    /**
     * @return string
     */
    abstract public function getWorkingTitle();

    /**
     * Return the shipping icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return URL_OPT_DIR.'quiqqer/shipping/bin/shipping/default.png';
    }

    /**
     * Return the shipping as an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'name'        => $this->getName(),
            'title'       => $this->getTitle(),
            'description' => $this->getDescription()
        ];
    }

    /**
     * Is the shipping be visible in the frontend?
     * Every shipping method can determine this by itself (API for developers)
     *
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    //region text messages

    /**
     * Return the extra text for the invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
     * @return mixed
     */
    public function getInvoiceInformationText($Invoice)
    {
        return '';
    }

    //endregion
}
