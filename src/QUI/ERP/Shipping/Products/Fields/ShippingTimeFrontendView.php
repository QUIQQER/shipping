<?php

namespace QUI\ERP\Shipping\Products\Fields;

use Exception;
use QUI;

use function dirname;
use function json_decode;

/**
 * Class UnitSelectFrontendView
 *
 * View control for showing UnitSelect values in the product frontend
 */
class ShippingTimeFrontendView extends QUI\ERP\Products\Field\View
{
    /**
     * Render the view, return the html
     *
     * @return string
     */
    public function create(): string
    {
        if (!$this->hasViewPermission()) {
            return '';
        }

        $Engine = QUI::getTemplateManager()->getEngine();
        $L = QUI::getLocale();
        $lg = 'quiqqer/shipping';

        /** @var ShippingTimePeriod $Field */
        $value = $this->getValue();

        if (empty($value)) {
            return '';
        }

        switch ($value['option']) {
            case ShippingTimePeriod::OPTION_UNAVAILABLE:
            case ShippingTimePeriod::OPTION_ON_REQUEST:
            case ShippingTimePeriod::OPTION_IMMEDIATELY_AVAILABLE:
            case ShippingTimePeriod::OPTION_AVAILABLE_SOON:
                $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.' . $value['option']);
                $cssClass = $value['option'];
                break;

            case ShippingTimePeriod::OPTION_CUSTOM_TEXT:
                $lang = $L->getCurrent();

                if (!empty($value['text'][$lang])) {
                    $valueText = $value['text'][$lang];
                } else {
                    $valueText = current($value['text']);
                }

                $cssClass = 'custom_text';
                break;

            default:
                $from = $value['from'];
                $to = $value['to'];
                $unit = $value['unit'];
                $cssClass = 'timeperiod';

                if (empty($to) && empty($from)) {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.unavailable');
                    $cssClass = 'unavailable';
                    break;
                }

                $singleTime = true;

                if ($from === $to) {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.period', [
                        'period' => $from
                    ]);
                    /* @@phpstan-ignore-next-line */
                } elseif (empty($from) && !empty($to)) {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.period', [
                        'period' => $to
                    ]);
                } elseif (!empty($from) && empty($to)) {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.period', [
                        'period' => $from
                    ]);
                } else {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.from_to', [
                        'from' => $from,
                        'to' => $to
                    ]);

                    $singleTime = false;
                }

                if ($singleTime) {
                    $valueText .= ' ' . $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.unit_single.' . $unit);
                } else {
                    $valueText .= ' ' . $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.unit_multi.' . $unit);
                }
        }

        $Engine->assign([
            'title' => $this->getTitle(),
            'valueText' => $valueText,
            'cssClass' => $cssClass
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/ShippingTimePeriodFrontendView.html');
    }

    /**
     * Return the current value
     *
     * @return string|array
     */
    public function getValue(): mixed
    {
        if (!empty($this->value['option']) && $this->value['option'] === ShippingTimePeriod::OPTION_USE_DEFAULT) {
            try {
                $Conf = QUI::getPackage('quiqqer/shipping')->getConfig();
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                return null;
            }

            $defaultValue = $Conf->get('shipping', 'deliveryTimeDefault');

            if (!empty($defaultValue)) {
                return json_decode($defaultValue, true);
            }
        }

        return parent::getValue();
    }
}
