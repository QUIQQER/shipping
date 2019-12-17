<?php

/**
 * This file contains QUI\ERP\Shipping\ShippingStatus\Handler
 */

namespace QUI\ERP\Shipping\ShippingStatus;

use QUI;
use QUI\ERP\Order\AbstractOrder;

/**
 * Class Handler
 * - Shipping status management
 * - Returns shipping status objects
 * - Returns shipping status lists
 *
 * @package QUI\ERP\Shipping\ShippingStatus\Factory
 */
class Handler extends QUI\Utils\Singleton
{
    /**
     * @var array
     */
    protected $list = null;

    /**
     * Return all shipping status entries from the config
     *
     * @return array
     */
    public function getList()
    {
        if ($this->list !== null) {
            return $this->list;
        }

        try {
            $Package = QUI::getPackage('quiqqer/shipping');
            $Config  = $Package->getConfig();
            $result  = $Config->getSection('shipping_status');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [];
        }

        if (!$result || !\is_array($result)) {
            $this->list = [];

            return $this->list;
        }

        $this->list = $result;

        return $result;
    }

    /**
     * Refresh the internal list
     *
     * @return array
     */
    public function refreshList()
    {
        $this->list = null;

        return $this->getList();
    }

    /**
     * Return the complete shipping_status status objects
     *
     * @return Status[]
     */
    public function getShippingStatusList()
    {
        $list   = $this->getList();
        $result = [];

        foreach ($list as $entry => $color) {
            try {
                $result[] = $this->getShippingStatus($entry);
            } catch (Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * Return a shipping status
     *
     * @param $id
     * @return Status|StatusUnknown
     *
     * @throws Exception
     */
    public function getShippingStatus($id)
    {
        if ($id === 0) {
            return new StatusUnknown();
        }

        return new Status($id);
    }

    /**
     * Delete / Remove a shipping status
     *
     * @param string|int $id
     *
     * @throws Exception
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function deleteShippingStatus($id)
    {
        $Status = $this->getShippingStatus($id);

        // remove translation
        QUI\Translator::delete(
            'quiqqer/shipping',
            'shipping.status.'.$Status->getId()
        );

        QUI\Translator::publish('quiqqer/shipping');

        // update config
        $Package = QUI::getPackage('quiqqer/shipping');
        $Config  = $Package->getConfig();

        $Config->del('shipping_status', $Status->getId());
        $Config->save();
    }

    /**
     * Set auto-notification setting for a status
     *
     * @param int $id - ShippingStatus ID
     * @param bool $notify - Auto-notification if an order is changed to the given status?
     * @return void
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function setShippingStatusNotification($id, $notify)
    {
        $Status = $this->getShippingStatus($id);

        // update config
        $Package = QUI::getPackage('quiqqer/shipping');
        $Config  = $Package->getConfig();

        $Config->setValue('shipping_status_notification', $Status->getId(), $notify ? "1" : "0");
        $Config->save();
    }

    /**
     * Update a shipping status
     *
     * @param int|string $id
     * @param int|string $color
     * @param array $title
     *
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function updateShippingStatus($id, $color, array $title)
    {
        $Status = $this->getShippingStatus($id);

        // update translation
        $languages = QUI::availableLanguages();

        $data = [
            'package'  => 'quiqqer/shipping',
            'datatype' => 'php,js',
            'html'     => 1
        ];

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language]         = $title[$language];
                $data[$language.'_edit'] = $title[$language];
            }
        }

        QUI\Translator::edit(
            'quiqqer/shipping',
            'shipping.status.'.$Status->getId(),
            'quiqqer/shipping',
            $data
        );

        QUI\Translator::publish('quiqqer/shipping');

        // update config
        $Package = QUI::getPackage('quiqqer/shipping');
        $Config  = $Package->getConfig();

        $Config->setValue('shipping_status', $Status->getId(), $color);
        $Config->save();
    }

    /**
     * Create translations for status notification
     *
     * @param int $id
     * @return void
     */
    public function createNotificationTranslations($id)
    {
        $data = [
            'package'  => 'quiqqer/shipping',
            'datatype' => 'php,js',
            'html'     => 1
        ];

        // translations
        $L         = new QUI\Locale();
        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            $L->setCurrent($language);
            $data[$language] = $L->get('quiqqer/shipping', 'shipping.status.notification.template');
        }

        try {
            // Check if translation already exists
            $translation = QUI\Translator::get('quiqqer/shipping', 'shipping.status.notification.'.$id);

            if (!empty($translation)) {
                return;
            }

            // ShippingStatus notification messages
            QUI\Translator::addUserVar(
                'quiqqer/shipping',
                'shipping.status.notification.'.$id,
                $data
            );

            QUI\Translator::publish('quiqqer/shipping');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Notify customer about an Order status change (via e-mail)
     *
     * @param AbstractOrder $Order
     * @param int $statusId
     * @param string $message (optional) - Custom notification message [default: default status change message]
     * @return void
     *
     * @throws QUI\Exception
     */
    public function sendStatusChangeNotification(AbstractOrder $Order, $statusId, $message = null)
    {
        $Customer      = $Order->getCustomer();
        $customerEmail = $Customer->getAttribute('email');

        if (empty($customerEmail)) {
            QUI\System\Log::addWarning(
                'Status change notification for order #'.$Order->getPrefixedId().' cannot be sent'
                .' because customer #'.$Customer->getId().' has no e-mail address.'
            );

            return;
        }

        if (empty($message)) {
            $Status  = $this->getShippingStatus($statusId);
            $message = $Status->getStatusChangeNotificationText($Order);
        }

        $Mailer = new QUI\Mail\Mailer();
        /** @var QUI\Locale $Locale */
        $Locale = $Order->getCustomer()->getLocale();

        $Mailer->setSubject(
            $Locale->get('quiqqer/shipping', 'shipping.status.notification.subject', [
                'orderNo' => $Order->getPrefixedId()
            ])
        );

        $Mailer->setBody($message);
        $Mailer->addRecipient($customerEmail);

        try {
            $Mailer->send();
            $Order->addStatusMail($message);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
