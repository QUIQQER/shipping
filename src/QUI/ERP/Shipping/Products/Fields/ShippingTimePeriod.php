<?php

namespace QUI\ERP\Shipping\Products\Fields;

use QUI;
use QUI\ERP\Products\Field\Exception;
use QUI\ERP\Products\Field\Types\TimePeriod;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;
use function json_last_error;

use const JSON_ERROR_NONE;

/**
 * Class TimePeriod
 *
 * Define an arbitrary time period.
 */
class ShippingTimePeriod extends TimePeriod
{
    const OPTION_TIMEPERIOD = 'timeperiod';
    const OPTION_UNAVAILABLE = 'unavailable';
    const OPTION_IMMEDIATELY_AVAILABLE = 'immediately_available';
    const OPTION_ON_REQUEST = 'on_request';
    const OPTION_AVAILABLE_SOON = 'available_soon';
    const OPTION_CUSTOM_TEXT = 'custom_text';
    const OPTION_USE_DEFAULT = 'use_default';

    /**
     * Check the value
     * is the value valid for the field type?
     *
     * @param mixed $value
     * @throws Exception
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);

        if (empty($value)) {
            return;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $needles = [
            'option'
        ];

        foreach ($needles as $needle) {
            if (!array_key_exists($needle, $value)) {
                throw new Exception([
                    'quiqqer/products',
                    'exception.field.invalid',
                    [
                        'fieldId' => $this->getId(),
                        'fieldTitle' => $this->getTitle(),
                        'fieldType' => $this->getType()
                    ]
                ]);
            }
        }
    }

    /**
     * Cleanup the value, so the value is valid
     *
     * @param mixed $value
     * @return array|null
     */
    public function cleanup(mixed $value): mixed
    {
        $defaultValue = $this->getDefaultValue();

        if (empty($value)) {
            return $defaultValue;
        }

        if (!is_string($value) && !is_array($value)) {
            return $defaultValue;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $defaultValue;
            }
        }

        $value['from'] = (int)$value['from'];
        $value['to'] = (int)$value['to'];

        switch ($value['option']) {
            case self::OPTION_TIMEPERIOD:
            case self::OPTION_UNAVAILABLE:
            case self::OPTION_IMMEDIATELY_AVAILABLE:
            case self::OPTION_ON_REQUEST:
            case self::OPTION_AVAILABLE_SOON:
            case self::OPTION_USE_DEFAULT:
                break;

            case self::OPTION_CUSTOM_TEXT:
                if (empty($value['text']) || !is_array($value['text'])) {
                    $value['text'] = [];

                    foreach (QUI::availableLanguages() as $lang) {
                        $value['text'][$lang] = '';
                    }
                }
                break;

            default:
                return $defaultValue;
        }

        return $value;
    }

    /**
     * @return string
     */
    public function getJavaScriptControl(): string
    {
        return 'package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod';
    }

    /**
     * Return the FrontendView
     *
     * @return ShippingTimeFrontendView
     */
    public function getFrontendView(): ShippingTimeFrontendView
    {
        return new ShippingTimeFrontendView($this->getFieldDataForView());
    }

    /**
     * Return the default value
     *
     * @return mixed|null
     */
    public function getDefaultValue(): mixed
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

        return json_decode($defaultValue, true);
    }
}
