<?php

namespace QUI\ERP\Shipping;

use QUI;
use QUI\ERP\Shipping\Types\ShippingEntry;

/**
 * Class Debug
 */
class Debug
{
    /**
     * @var bool
     */
    protected static $shippingRuleDebugs = [];

    /**
     * @var \Monolog\Logger
     */
    protected static $Logger = null;

    /**
     * @var \Monolog\Logger
     */
    protected static $FormatLogger = null;

    /**
     * @param $ruleId
     * @return bool
     */
    public static function isRuleAlreadyDebugged($ruleId)
    {
        return isset(self::$shippingRuleDebugs[$ruleId]);
    }

    /**
     * @param $ruleId
     */
    public static function ruleIsDebugged($ruleId)
    {
        self::$shippingRuleDebugs[$ruleId] = true;
    }

    /**
     * @param $Entry
     * @param $result
     * @param $debuggingLog
     */
    public static function generateShippingEntryDebuggingLog(
        ShippingEntry $Entry,
        $result,
        $debuggingLog
    ) {
        if (self::isRuleAlreadyDebugged($Entry->getId())) {
            return;
        }


        // rule log
        $debugMessage = "\n";

        foreach ($debuggingLog as $entry) {
            $debugMessage .= "\n";

            if ($entry['valid']) {
                $debugMessage .= '✅ ';
            } else {
                $debugMessage .= '❌ ';
            }

            $debugMessage .= $entry['id'].' - '.$entry['title'].' -> '.$entry['reason'];
        }

        try {
            QUI::getEvents()->addEvent('onResponseSent', function () use ($Entry, $result, $debugMessage) {
                $log = [];

                /* @var $ShippingRule QUI\ERP\Shipping\Rules\ShippingRule */
                foreach ($result as $ShippingRule) {
                    $log[] = [
                        'id'       => $ShippingRule->getId(),
                        'title'    => $ShippingRule->getTitle(),
                        'priority' => $ShippingRule->getPriority(),
                        'discount' => $ShippingRule->getDiscount()
                    ];
                }

                self::getLogger()->info($Entry->getTitle(), $log);
                self::getLoggerWithoutFormatter()->info($debugMessage);
            });
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        QUI\ERP\Shipping\Debug::ruleIsDebugged($Entry->getId());
    }

    /**
     * @return \Monolog\Logger
     */
    public static function getLogger()
    {
        if (self::$Logger !== null) {
            return self::$Logger;
        }

        $Logger  = new \Monolog\Logger('quiqqer-shipping');
        $Handler = new \Monolog\Handler\BrowserConsoleHandler(\Monolog\Logger::DEBUG);
        $Logger->pushHandler($Handler);

        self::$Logger = $Logger;

        return self::$Logger;
    }

    /**
     * @return \Monolog\Logger
     */
    public static function getLoggerWithoutFormatter()
    {
        if (self::$FormatLogger !== null) {
            return self::$FormatLogger;
        }

        $Logger    = new \Monolog\Logger('quiqqer-shipping');
        $Handler   = new \Monolog\Handler\BrowserConsoleHandler(\Monolog\Logger::DEBUG);
        $Formatter = new \Monolog\Formatter\LineFormatter(
            null,
            // Format of message in log, default [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n
            null, // Datetime format
            true, // allowInlineLineBreaks option, default false
            true  // ignoreEmptyContextAndExtra option, default false
        );

        $Handler->setFormatter($Formatter);
        $Logger->pushHandler($Handler);

        self::$FormatLogger = $Logger;

        return self::$FormatLogger;
    }

    /**
     * @param QUI\ERP\Order\OrderInterface $Order
     */
    public static function sendAdminInfoMailAboutEmptyShipping(
        QUI\ERP\Order\OrderInterface $Order
    ) {
        try {
            $Article     = $Order->getArticles();
            $articleHtml = $Article->toHTML();
        } catch (QUI\Exception $Exception) {
            //@todo send mail because of exception
            return;
        }

        $adminMail = QUI::conf('mail', 'admin_mail');
        $subject   = QUI::getLocale()->get('quiqqer/shipping', 'mail.admin.info.empty.shipping.subject');

        $body = QUI::getLocale()->get('quiqqer/shipping', 'mail.admin.info.empty.shipping.body');
        $body .= '<br /><br />------<br />><br />';
        $body .= $articleHtml;

        if (empty($adminMail)) {
            QUI\System\Log::addAlert($body);

            return;
        }

        // send mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($adminMail);
        $Mailer->setSubject($subject);
        $Mailer->setBody($body);

        try {
            $Mailer->send();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert($body);
            QUI\System\Log::writeException($Exception);
        }
    }
}
