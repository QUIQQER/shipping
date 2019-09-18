<?php

namespace QUI\ERP\Shipping\Products\Fields;

use QUI;
use QUI\ERP\Products\Field\Types\TimePeriod;

/**
 * Class TimePeriod
 *
 * Define an arbitrary time period.
 */
class ShippingTimePeriod extends TimePeriod
{
    const OPTION_TIMEPERIOD            = 'timeperiod';
    const OPTION_UNAVAILABLE           = 'unavailable';
    const OPTION_IMMEDIATELY_AVAILABLE = 'immediately_available';
    const OPTION_ON_REQUEST            = 'on_request';
    const OPTION_AVAILABLE_SOON        = 'available_soon';
    const OPTION_CUSTOM_TEXT           = 'custom_text';

    /**
     * Check the value
     * is the value valid for the field type?
     *
     * @param array
     * @throws \QUI\ERP\Products\Field\Exception
     */
    public function validate($value)
    {
        parent::validate($value);

        if (empty($value)) {
            return;
        }

        if (\is_string($value)) {
            $value = \json_decode($value, true);
        }

        $needles = [
            'option',
            'text'
        ];

        foreach ($needles as $needle) {
            if (!\array_key_exists($needle, $value)) {
                throw new QUI\ERP\Products\Field\Exception([
                    'quiqqer/products',
                    'exception.field.invalid',
                    [
                        'fieldId'    => $this->getId(),
                        'fieldTitle' => $this->getTitle(),
                        'fieldType'  => $this->getType()
                    ]
                ]);
            }
        }
    }

    /**
     * Cleanup the value, so the value is valid
     *
     * @param string|array $value
     * @return array|null
     */
    public function cleanup($value)
    {
        $value        = parent::cleanup($value);
        $defaultValue = $this->getDefaultValueFromConfig();

        if (empty($value)) {
            return $defaultValue;
        }

        switch ($value['option']) {
            case self::OPTION_TIMEPERIOD:
            case self::OPTION_UNAVAILABLE:
            case self::OPTION_IMMEDIATELY_AVAILABLE:
            case self::OPTION_ON_REQUEST:
            case self::OPTION_AVAILABLE_SOON:
            case self::OPTION_CUSTOM_TEXT:
                break;

            default:
                return $defaultValue;
        }

        return $value;
    }

    /**
     * @return string
     */
    public function getJavaScriptControl()
    {
        return 'package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod';
    }

    /**
     * Return the FrontendView
     *
     * @return ShippingTimeFrontendView
     */
    public function getFrontendView()
    {
        return new ShippingTimeFrontendView($this->getFieldDataForView());
    }

    /**
     * Get default value for the ShippingTimePeriod field
     *
     * @return array|null
     */
    protected function getDefaultValueFromConfig()
    {
        try {
            $Conf = QUI::getPackage('quiqqer/shipping')->getConfig();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return null;
        }

        $defaultValue = $Conf->get('shipping', 'deliveryTimeDefault');

        if (empty($defaultValue)) {
            return null;
        }

        return \json_decode($defaultValue, true);
    }
}
