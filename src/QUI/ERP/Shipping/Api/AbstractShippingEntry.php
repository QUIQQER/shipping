<?php

/**
 * This file contains \QUI\ERP\Shipping\Api\AbstractShippingEntry
 */

namespace QUI\ERP\Shipping\Api;

use QUI;

use function get_class;
use function md5;

/**
 * Shipping abstract class
 * This is the parent shipping class for all shipping methods
 *
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractShippingEntry extends QUI\CRUD\Child implements ShippingInterface
{
    /**
     * shipping fields - extra fields for the shipping / accounting
     *
     * @var array
     */
    protected array $shippingFields = [];

    /**
     * default settings
     *
     * @var array
     */
    protected array $defaults = [];

    /**
     * Locale object for the shipping
     *
     * @var ?QUI\Locale
     */
    protected ?QUI\Locale $Locale = null;

    /**
     * Set the locale object to the shipping
     *
     * @param QUI\Locale $Locale
     */
    public function setLocale(QUI\Locale $Locale): void
    {
        $this->Locale = $Locale;
    }

    /**
     * Return the Locale of the shipping
     *
     * @return QUI\Locale
     */
    public function getLocale(): QUI\Locale
    {
        if ($this->Locale === null) {
            $this->Locale = QUI::getLocale();
        }

        return $this->Locale;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return md5(get_class($this));
    }

    /**
     * Return the class of the instance
     *
     * @return string
     */
    public function getClass(): string
    {
        return get_class($this);
    }

    /**
     * @param null $Locale
     * @return string
     */
    abstract public function getTitle($Locale = null): string;

    /**
     * @param null $Locale
     * @return string
     */
    abstract public function getDescription($Locale = null): string;

    /**
     * @return string
     */
    abstract public function getWorkingTitle(): string;

    /**
     * Return the shipping icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon(): string
    {
        return QUI\ERP\Shipping\Shipping::getInstance()->getHost() .
            URL_OPT_DIR
            . 'quiqqer/shipping/bin/images/shipping/default.png';
    }

    /**
     * Return the shipping as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription()
        ];
    }

    /**
     * Is the shipping be visible in the frontend?
     * Every shipping method can determine this by itself (API for developers)
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return true;
    }

    //region text messages

    /**
     * Return the extra text for the invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
     * @return string
     */
    public function getInvoiceInformationText(
        QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
    ): string {
        return '';
    }

    //endregion
}
