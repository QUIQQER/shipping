<?php

namespace QUI\ERP\Shipping;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\FirePHPHandler;
use Monolog\Logger;
use PHPMailer\PHPMailer\Exception;
use QUI;
use QUI\ERP\Shipping\Types\ShippingEntry;

use function class_exists;

/**
 * Class Debug
 */
class Debug
{
    /**
     * @var bool
     */
    protected static array|bool $shippingRuleDebugs = [];

    /**
     * @var Logger|null
     */
    protected static ?Logger $Logger = null;

    /**
     * @var Logger|null
     */
    protected static ?Logger $FormatLogger = null;

    /**
     * Stack of log messages
     *
     * @var array
     */
    protected static array $logStack = [];

    /**
     * @var bool
     */
    protected static bool $enable = false;

    /**
     * enable the debugging
     */
    public static function enable(): void
    {
        self::$enable = true;
    }

    /**
     * disable the debugging
     */
    public static function disable(): void
    {
        self::$enable = false;
    }

    /**
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enable;
    }

    /**
     * @param $ruleId
     * @return bool
     */
    public static function isRuleAlreadyDebugged($ruleId): bool
    {
        return isset(self::$shippingRuleDebugs[$ruleId]);
    }

    /**
     * @param $ruleId
     */
    public static function ruleIsDebugged($ruleId): void
    {
        self::$shippingRuleDebugs[$ruleId] = true;
    }

    /**
     * @param string $messages
     */
    public static function addLog(string $messages): void
    {
        if (self::$enable) {
            self::$logStack[] = $messages;
        }
    }

    /**
     * @return array
     */
    public static function getLogStack(): array
    {
        return self::$logStack;
    }

    /**
     * clear the log stack
     */
    public static function clearLogStock(): void
    {
        self::$logStack = [];
    }

    /**
     * @param ShippingEntry $Entry
     * @param $result
     * @param $debuggingLog
     */
    public static function generateShippingEntryDebuggingLog(
        ShippingEntry $Entry,
        $result,
        $debuggingLog
    ): void {
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

            $debugMessage .= $entry['id'] . ' - ' . $entry['title'] . ' -> ' . $entry['reason'];
        }

        try {
            QUI::getEvents()->addEvent('onResponseSent', function () use (
                $Entry,
                $result,
                $debugMessage
            ) {
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
     * @return Logger|null
     */
    public static function getLogger(): ?Logger
    {
        if (self::$Logger !== null) {
            return self::$Logger;
        }

        $Logger = new Logger('quiqqer-shipping');

        if (class_exists('Monolog\Handler\BrowserConsoleHandler')) {
            $Logger->pushHandler(new BrowserConsoleHandler(Logger::DEBUG));
        }

        $Logger->pushHandler(new FirePHPHandler(Logger::DEBUG));
        $Logger->pushHandler(new ChromePHPHandler(Logger::DEBUG));

        self::$Logger = $Logger;

        return self::$Logger;
    }

    /**
     * @return Logger|null
     */
    public static function getLoggerWithoutFormatter(): ?Logger
    {
        if (self::$FormatLogger !== null) {
            return self::$FormatLogger;
        }

        $Logger = new Logger('quiqqer-shipping');

        if (class_exists('Monolog\Handler\BrowserConsoleHandler')) {
            $Console = new BrowserConsoleHandler(Logger::DEBUG);
        }

        $FireFox = new FirePHPHandler(Logger::DEBUG);
        $Chrome  = new ChromePHPHandler(Logger::DEBUG);

        $Formatter = new LineFormatter(
            null,
            // Format of message in log, default [%datetime%] %channel%.%level_name%: %message% %context% %extra%\n
            null, // Datetime format
            true, // allowInlineLineBreaks option, default false
            true  // ignoreEmptyContextAndExtra option, default false
        );

        if (isset($Console)) {
            $Console->setFormatter($Formatter);
            $Logger->pushHandler($Console);
        }

        $FireFox->setFormatter($Formatter);
        $Logger->pushHandler($FireFox);

        $Chrome->setFormatter($Formatter);
        $Logger->pushHandler($Chrome);

        self::$FormatLogger = $Logger;

        return self::$FormatLogger;
    }

    /**
     * @param QUI\ERP\Order\OrderInterface $Order
     * @throws Exception
     */
    public static function sendAdminInfoMailAboutEmptyShipping(
        QUI\ERP\Order\OrderInterface $Order
    ): void {
        if (Shipping::getInstance()->shippingDisabled()) {
            return;
        }

        try {
            $Article     = $Order->getArticles();
            $articleHtml = $Article->toHTML();
        } catch (QUI\Exception) {
            //@todo send mail because of exception
            return;
        }

        $DeliveryAddress = $Order->getDeliveryAddress();
        $delivery        = $DeliveryAddress->render();

        $adminMail = QUI::conf('mail', 'admin_mail');
        $subject   = QUI::getLocale()->get('quiqqer/shipping', 'mail.admin.info.empty.shipping.subject');

        $body = QUI::getLocale()->get('quiqqer/shipping', 'mail.admin.info.empty.shipping.body');
        $body .= '<br /><br />------<br /><br />';
        $body .= $delivery;
        $body .= '<br /><br />------<br /><br />';
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
