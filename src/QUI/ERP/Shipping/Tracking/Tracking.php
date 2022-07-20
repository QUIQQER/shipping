<?php


namespace QUI\ERP\Shipping\Tracking;

use QUI\Countries\Country;

use function array_filter;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_decode;

use const ETC_DIR;
use const JSON_PRETTY_PRINT;

/**
 * Helper class for shipping tracking
 */
class Tracking
{
    protected static array $tracking = [

        //UPS - UNITED PARCEL SERVICE
        [
            'active' => '1',
            'type'   => 'ups',
            'title'  => 'UPS',
            'image'  => 'quiqqer/shipping/bin/images/tracking/ups.svg',
            'url'    => 'https://wwwapps.ups.com/WebTracking/processInputRequest?TypeOfInquiryNumber=T&InquiryNumber1='
        ],

        //USPS - UNITED STATES POSTAL SERVICE
        [
            'active' => '1',
            'type'   => 'usps',
            'title'  => 'USPS',
            'image'  => 'quiqqer/shipping/bin/images/tracking/usps.png',
            'url'    => 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1='
        ],

        //FEDEX - FEDERAL EXPRESS
        [
            'active' => '1',
            'type'   => 'fedex',
            'title'  => 'FedEx',
            'image'  => 'quiqqer/shipping/bin/images/tracking/fedex.svg',
            'url'    => 'https://www.fedex.com/fedextrack/?trknbr='
        ],

        //LaserShip
        [
            'active' => '1',
            'type'   => 'laser_ship',
            'title'  => 'LaserShip',
            'image'  => 'quiqqer/shipping/bin/images/tracking/lasership.png',
            'url'    => 'https://www.fedex.com/fedextrack/?trknbr='
        ],

        //ONTRAC
        [
            'active' => '1',
            'type'   => 'ontrac',
            'title'  => 'OnTrac',
            'image'  => 'quiqqer/shipping/bin/images/tracking/ontrac.svg',
            'url'    => 'https://www.ontrac.com/trackres.asp?tracking_number='
        ],

        //DHL
        [
            'active'  => '1',
            'type'    => 'dhl',
            'title'   => 'DHL',
            'image'   => 'quiqqer/shipping/bin/images/tracking/dhl.svg',
            'url '    => 'https://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=',
            'country' => [
                'en' => 'https://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=',
                'de' => 'https://www.dhl.com/de-de/home/tracking/tracking-parcel.html?submit=1&tracking-id='
            ]
        ],

        //DPD
        [
            'active' => '1',
            'type'   => 'dpd',
            'title'  => 'DPD',
            'image'  => 'quiqqer/shipping/bin/images/tracking/dpd.svg',
            'url'    => 'https://track.dpdnl.nl/?parcelnumber='
        ]
    ];

    /**
     * @return string
     */
    public static function getConfigFile(): string
    {
        return ETC_DIR . 'plugins/quiqqer/shippingTracking.json';
    }

    /**
     * Create a shipping tracking json file into the etc folder
     *
     * @return void
     */
    public static function onPackageInstall()
    {
        $file = self::getConfigFile();

        if (!file_exists($file)) {
            file_put_contents($file, json_encode(self::$tracking, JSON_PRETTY_PRINT));
        }
    }

    /**
     * @return array
     */
    public static function getActiveCarriers(): array
    {
        $data = json_decode(
            file_get_contents(self::getConfigFile()),
            true
        );

        return array_filter($data, function ($entry) {
            return (int)$entry['active'];
        });
    }

    /**
     * @param string|numeric $trackingId
     * @param string $carrier
     * @param ?Country $Country
     *
     * @return string
     */
    public static function getUrl($trackingId, string $carrier, ?Country $Country): string
    {
        $carriers = self::getActiveCarriers();
        $country  = false;

        if ($Country) {
            $country = $Country->getCode();
        }

        foreach ($carriers as $entry) {
            if ($entry['type'] !== $trackingId) {
                continue;
            }

            if ($country && isset($entry['country'][$country])) {
                return $entry['country'][$country] . $trackingId;
            }

            return $entry['url'] . $trackingId;
        }

        return '';
    }
}
