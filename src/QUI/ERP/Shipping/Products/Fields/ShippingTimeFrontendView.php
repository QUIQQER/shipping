<?php

namespace QUI\ERP\Shipping\Products\Fields;

use QUI;

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
     * @throws QUI\Exception
     */
    public function create()
    {
        if (!$this->hasViewPermission()) {
            return '';
        }

        $Engine = QUI::getTemplateManager()->getEngine();
        $L      = QUI::getLocale();
        $lg     = 'quiqqer/shipping';

        /** @var ShippingTimePeriod $Field */
        $Field = QUI\ERP\Products\Handler\Fields::getField($this->getId());

        $value = $this->getValue();

        switch ($value['option']) {
            case ShippingTimePeriod::OPTION_UNAVAILABLE:
            case ShippingTimePeriod::OPTION_ON_REQUEST:
            case ShippingTimePeriod::OPTION_IMMEDIATELY_AVAILABLE:
            case ShippingTimePeriod::OPTION_AVAILABLE_SOON:
                $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.'.$value['option']);
                break;

            case ShippingTimePeriod::OPTION_CUSTOM_TEXT:
                $lang = $L->getCurrent();

                if (isset($value['text'][$lang])) {
                    $valueText = $value['text'][$lang];
                } else {
                    $valueText = current($value['text']);
                }
                break;

            default:
                $from = $value['from'];
                $to   = $value['to'];
                $unit = $value['unit'];

                if (empty($to) && empty($from)) {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.unavailable');
                    break;
                }

                $singleTime = true;

                if ($from === $to) {
                    $valueText = $L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.period', [
                        'period' => $from
                    ]);
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
                        'to'   => $to
                    ]);

                    $singleTime = false;
                }

                if ($singleTime) {
                    $valueText .= ' '.$L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.unit_single.'.$unit);
                } else {
                    $valueText .= ' '.$L->get($lg, 'fields.ShippingTimeFrontendView.timeperiod.unit_multi.'.$unit);
                }
        }

        $Engine->assign([
            'title'     => $this->getTitle(),
            'valueText' => $valueText
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/ShippingTimePeriodFrontendView.html');
    }
}
