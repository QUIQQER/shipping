<?php

/**
 * This file contains QUI\ERP\Shipping\ShippingStatus\Status
 */

namespace QUI\ERP\Shipping\ShippingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;

/**
 * Class Exception
 *
 * @package QUI\ERP\Shipping\ShippingStatus
 */
class Status
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $color;

    /**
     * @var bool
     */
    protected $notification = false;

    /**
     * Status constructor.
     *
     * @param int|\string $id - Shipping status id
     * @throws Exception
     */
    public function __construct($id)
    {
        $list = Handler::getInstance()->getList();

        if (!isset($list[$id])) {
            throw new Exception([
                'quiqqer/shipping',
                'exception.shippingStatus.not.found'
            ]);
        }

        $this->id    = (int)$id;
        $this->color = $list[$id];

        // notification
        try {
            $Package = QUI::getPackage('quiqqer/shipping');
            $Config  = $Package->getConfig();
            $result  = $Config->getSection('shipping_status_notification');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        if (!empty($result[$id])) {
            $this->notification = \boolval($result[$id]);
        }
    }

    //region Getter

    /**
     * Return the status id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale (optional) $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/shipping', 'shipping.status.'.$this->id);
    }

    /**
     * Get status notification message
     *
     * @param AbstractOrder $Order - The order the status change applies to
     * @param QUI\Locale $Locale (optional) - [default: QUI::getLocale()]
     * @return string
     */
    public function getStatusChangeNotificationText(AbstractOrder $Order, $Locale = null)
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        $Customer = $Order->getCustomer();

        return $Locale->get('quiqqer/shipping', 'shipping.status.notification.'.$this->id, [
            'customerName' => $Customer->getName(),
            'orderNo'      => $Order->getPrefixedId(),
            'orderDate'    => $Locale->formatDate($Order->getCreateDate()),
            'orderStatus'  => $this->getTitle($Locale)
        ]);
    }

    /**
     * Return the status color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Check if the customer has to be notified if this status is set to an order
     *
     * @return bool
     */
    public function isAutoNotification()
    {
        return $this->notification;
    }

    //endregion

    /**
     * Status as array
     *
     * @param null|QUI\Locale $Locale - optional. if no locale, all translations would be returned
     * @return array
     */
    public function toArray($Locale = null)
    {
        $title = $this->getTitle($Locale);

        if ($Locale === null) {
            $statusId         = $this->getId();
            $title            = [];
            $statusChangeText = [];

            $Locale    = QUI::getLocale();
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                $title[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/shipping',
                    'shipping.status.'.$statusId
                );

                $statusChangeText[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/shipping',
                    'shipping.status.notification.'.$statusId
                );
            }
        }

        return [
            'id'           => $this->getId(),
            'title'        => $title,
            'color'        => $this->getColor(),
            'notification' => $this->isAutoNotification()
        ];
    }
}
