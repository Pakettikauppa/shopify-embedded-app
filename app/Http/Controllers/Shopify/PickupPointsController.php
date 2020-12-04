<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Pakettikauppa\Client;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Psy\Exception\FatalErrorException;
use Log;

class PickupPointsController extends Controller
{
    private $pickupPointSettings;

    public function list(Request $request)
    {
        // SETUP EVERYTHING
        // setup and validate Shop
        $shop = Shop::where('shop_origin', $request->header('x-shopify-shop-domain'))->first();

        if ($shop == null) {
            throw new FatalErrorException();
        }

        Log::debug("Searching for " . $shop->shop_origin);

        $calculatedMac = base64_encode(hash_hmac('sha256', $request->getContent(), config('shopify.secret'), true));
        if (!hash_equals($calculatedMac, $request->header('x-shopify-hmac-sha256'))) {
            Log::debug("Hash mismatch");
            throw new UnprocessableEntityHttpException();
        }

        $pk_client_params = null;
        // setup Pakettikauppa Client
        if ($shop->test_mode) {
            $pk_client_params = [
                'test_mode' => true,
            ];
        } else {
            if (!empty($shop->api_key) && !empty($shop->api_secret)) {
                $pk_client_params = [
                    'api_key' => $shop->api_key,
                    'secret' => $shop->api_secret,
                ];
            }
        }

        if ($pk_client_params == null) {
            Log::debug("Pikcup points: fatal error");
            throw new FatalErrorException();
        }

        $pk_client = new Client($pk_client_params);

        // test if pickup points are available in settings
        if (!(isset($shop->pickuppoints_count) && $shop->pickuppoints_count > 0)) {
            Log::debug("no pickup point counts");
            return;
        }

        // fetch pickup points
        if ($shop->settings == null) {
            $shop->settings = '{}';
        }
        $this->pickupPointSettings = json_decode($shop->settings, true);

        // get destination address
        $requestBody = json_decode($request->getContent());
        Log::debug($request->getContent());
        $destination = $requestBody->rate->destination;

        $rates = array();
        if (count($this->pickupPointSettings) > 0) {
            // calculate total value of the cart
            $totalValue = 0;
            $totalWeightInGrams = 0;
            $totalDiscount = 0;
            foreach ($requestBody->rate->items as $_item) {
                $totalValue += $_item->price * $_item->quantity;
                $totalWeightInGrams += $_item->grams * $_item->quantity;
            }

            $pickupPointProviders = array();

            foreach ($this->pickupPointSettings as $_provider => $_settings) {
                if ($_settings['active'] == 'true') {
                    if (!($totalWeightInGrams > 20000 and $_provider === '80010')) {
                        $pickupPointProviders[] = $_provider;
                    }
                }
            }

            // convert array to string
            $pickupPointProviders = implode(",", $pickupPointProviders);

            // search nearest pickup locations
            $pickupPoints = $pk_client->searchPickupPoints(
                $destination->postal_code,
                $destination->address1,
                $destination->country,
                $pickupPointProviders,
                $shop->pickuppoints_count
            );

            if (empty($pickupPoints) && ($destination->country == 'LT' || $destination->country == 'AX' || $destination->country == 'FI')) {
                // search some pickup points if no pickup locations was found
                $pickupPoints = $pk_client->searchPickupPoints(
                    '00100',
                    null,
                    'FI',
                    $pickupPointProviders,
                    $shop->pickuppoints_count
                );
            }
            // generate custom carrier service response
            try {
                foreach ($pickupPoints as $_pickupPoint) {
                    $_pickupPointName = ucwords(mb_strtolower($_pickupPoint->name));

                    switch ($_pickupPoint->provider_code) {
                        case 'Posti':
                            $_pickupPoint->provider_service = '2103';
                            break;
                        case 'Matkahuolto':
                            $_pickupPoint->provider_service = '90080';
                            break;
                        case 'DB Schenker':
                            $_pickupPoint->provider_service = '80010';
                            break;
                    }

                    if ($_pickupPoint->provider_service == '80010') {
                        $_descriptionArray = [];
                        preg_match(
                            "/V(?<week>[0-9-]*)[ ]*L?(?<sat>[0-9-]*)[ ]*S?(?<sun>[0-9-]?.*)/",
                            $_pickupPoint->description,
                            $_descriptionArray
                        );

                        if (count($_descriptionArray) > 0) {
                            $_weekHours = 'ma-pe ' . $this->convertDBSTime($_descriptionArray['week']);
                            $_satHours = '';
                            $_sunHours = '';

                            if (isset($_descriptionArray['sat'])) {
                                $_satHours = ', la ' . $this->convertDBSTime($_descriptionArray['sat']);
                            }
                            if (isset($_descriptionArray['sun'])) {
                                $_sunHours = ', su ' . $this->convertDBSTime($_descriptionArray['sun']);
                            }

                            $_pickupPoint->description = "{$_weekHours}{$_satHours}{$_sunHours}";
                        }
                    }

                    $rates[] = array(
                        'service_name' => "{$_pickupPointName}, " . "{$_pickupPoint->street_address}, {$_pickupPoint->postcode}, {$_pickupPoint->city}",
                        'description' => $_pickupPoint->provider . ($_pickupPoint->description == null ? '' : " ({$_pickupPoint->description})"),
                        'service_code' => "{$_pickupPoint->provider_service}:{$_pickupPoint->pickup_point_id}",
                        'currency' => 'EUR',
                        'total_price' => $this->priceForPickupPoint($_pickupPoint->provider_service, $totalValue)
                    );
                }
            } catch (\Exception $e) {
                Log::debug($e->getTraceAsString());
                Log::debug(var_export($pickupPoints, true));
                Log::debug(var_export($request->all(), true));
            }
        }

        $customCarrierServices = array('rates' => $rates);

        if (!($destination->country == 'LT' || $destination->country == 'FI' || $destination->country == 'AX' || $destination->country == 'EE')) {
            $customCarrierServices = array('rates' => []);
        }

        $json = json_encode($customCarrierServices);

        Log::debug($json);
        echo $json;
    }

    private function convertDBSTime($openingHours)
    {
        $openingHours = '000000000' . $openingHours;

        try {
            $startTime = substr($openingHours, -9, 2) . "." . substr($openingHours, -7, 2);
            $endTime = substr($openingHours, -4, 2) . "." . substr($openingHours, -2, 2);

            return "{$startTime} - {$endTime}";
        } catch (\Exception $e) {
            Log::debug($e->getTraceAsString());
            return $openingHours;
        }
    }

    private function priceForPickupPoint($provider, $totalValue)
    {
        $pickupPointSettings = $this->pickupPointSettings[$provider];

        if ($pickupPointSettings['trigger_price'] > 0 and $pickupPointSettings['trigger_price'] * 100 <= $totalValue) {
            return (int)round($pickupPointSettings['triggered_price'] * 100.0);
        }

        return (int)round($pickupPointSettings['base_price'] * 100.0);
    }
}
