<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use App\Helpers\PakettikauppaAPI;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Psy\Exception\FatalErrorException;
use Log;

class PickupPointsController extends Controller {

    private $pickupPointSettings;
    private $type;
    private $test_mode;

    public function __construct(Request $request) {
        $this->type = config('shopify.type');
        $this->test_mode = config('shopify.test_mode');
    }

    public function list(Request $request) {
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
        $pk_use_config = null;
        // setup Pakettikauppa Client
        if ($this->type == "posti" || $this->type == "itella") {
            $pk_client_params = [
                'posti_config' => [
                    'api_key' => $shop->api_key,
                    'secret' => $shop->api_secret,
                    'base_uri' => $this->test_mode ? 'https://argon.api.posti.fi' : 'https://nextshipping.posti.fi',
                    'use_posti_auth' => true,
                    'posti_auth_url' => $this->test_mode ? 'https://oauth.barium.posti.com' : 'https://oauth2.posti.com',
                ]
            ];
            $pk_use_config = "posti_config";
        } else {
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
        }

        if ($pk_client_params == null) {
            Log::debug("Pikcup points: fatal error");
            throw new FatalErrorException();
        }

        $pk_client = new PakettikauppaAPI($pk_client_params, $pk_use_config);
        $pk_client->setSenderSystemName('Shopify');

        if ($pk_use_config == "posti_config") {
            $token = $pk_client->getToken();
            if (isset($token->access_token)) {
                $pk_client->setAccessToken($token->access_token);
            }
        }

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

            Log::debug('TotalWeight: ' . $totalWeightInGrams);
            //if weight is more than 35kg, no pick up points support it, return empty
            if ($totalWeightInGrams > 35000) {
                $json = json_encode(['rates' => []]);
                Log::debug($json);
                echo $json;
                return;
            }

            $pickupPointProviders = array();

            foreach ($this->pickupPointSettings as $_provider => $_settings) {
                if ($_settings['active'] == 'true') {
                    if ($this->checkProviderWeightLimit($_provider, $totalWeightInGrams)) {
                        $pickupPointProviders[] = $_provider;
                    }
                }
            }

            if (empty($pickupPointProviders)) {
                // no pickup point providers
                Log::debug('No pickup point providers');
                $json = json_encode(['rates' => []]);
                echo $json;
                return;
            }

            // convert array to string
            $pickupPointProviders = implode(",", $pickupPointProviders);

            $pickupFilterQuery = !empty($shop->pickup_filter) ? implode(',', $shop->pickup_filter) : null;
            // search nearest pickup locations
            $pickupPoints = $pk_client->searchPickupPoints(
                    $destination->postal_code,
                    $destination->address1,
                    $destination->country,
                    $pickupPointProviders,
                    $shop->pickuppoints_count,
                    $pickupFilterQuery,
                    5
            );

            if (empty($pickupPoints) && ($destination->country == 'LT' || $destination->country == 'AX' || $destination->country == 'FI')) {
                //debug response
                Log::debug('Response from pickup point search: ' . json_encode(
                        [
                            'http_request' => $pk_client->http_request,
                            'http_response_code' => $pk_client->http_response_code,
                            'http_error' => $pk_client->http_error,
                            'http_response' => $pk_client->http_response
                        ]
                ));
                // search some pickup points if no pickup locations was found
                $pickupPoints = $pk_client->searchPickupPoints(
                        '00100',
                        null,
                        'FI',
                        $pickupPointProviders,
                        $shop->pickuppoints_count,
                        $pickupFilterQuery,
                        5
                );
            }
            
            //check if array received, sometimes got none array and exception is thrown
            if (!is_array($pickupPoints)) {
                Log::debug('Pickup points list is not array: ' . var_export($pickupPoints, true));
                $json = json_encode(array('rates' => $rates));
                echo $json;
                return;
            }
            
            // generate custom carrier service response
            try {
                foreach ($pickupPoints as $_pickupPoint) {
                    $_pickupPointName = ucwords(mb_strtolower($_pickupPoint->name));

                    $_pickupPoint->provider_service = 0;
                    if (isset($_pickupPoint->service->service_code) && $_pickupPoint->service->service_code) {
                        $_pickupPoint->provider_service = $_pickupPoint->service->service_code;
                    } else if (isset($_pickupPoint->service_code) && $_pickupPoint->service_code) {
                        $_pickupPoint->provider_service = $_pickupPoint->service_code;
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
                        'description' => $_pickupPoint->provider . ' (' . ((is_object($_pickupPoint->service) && isset($_pickupPoint->service->name) && $_pickupPoint->service->name != null) ? $_pickupPoint->service->name : '') . ') ' . ($_pickupPoint->description == null ? '' : " ({$_pickupPoint->description})"),
                        'service_code' => "{$_pickupPoint->provider_service}:{$_pickupPoint->pickup_point_id}",
                        'currency' => 'EUR',
                        'total_price' => $this->priceForPickupPoint($_pickupPoint->provider_service, $totalValue)
                    );
                }
            } catch (\Exception $e) {
                Log::debug($e->getMessage());
                Log::debug($e->getTraceAsString());
                Log::debug(var_export($pickupPoints, true));
                Log::debug(var_export($request->all(), true));
            }
        }

        $customCarrierServices = array('rates' => $rates);

        if (!(in_array($destination->country,['LV', 'LT', 'FI', 'AX', 'EE', 'SE', 'DK']))) {
            $customCarrierServices = array('rates' => []);
        }

        $json = json_encode($customCarrierServices);

        Log::debug($json);
        echo $json;
    }
    
    private function checkProviderWeightLimit($provider, $weight) {
        $provider_weight_map = array(
            '2103' => 25000,
            '2331' => 35000,
            '2771' => 31500,
            '90080' => 30000,
            '90084' => 30000,
            '80010' => 20000
        );
        if (!isset($provider_weight_map[$provider])) {
            return true;
        }
        if ($provider_weight_map[$provider] > $weight) {
            return true;
        }
        return false;
    }

    private function convertDBSTime($openingHours) {
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

    private function priceForPickupPoint($provider, $totalValue) {
        //take first provider if multi provider string
        $provider = explode(',', $provider);
        $main_provider = $provider[0] ?? false;

        if(!$main_provider){
            return 0;
        }

        $pickupPointSettings = $this->pickupPointSettings[$main_provider] ?? [];

        if(empty($pickupPointSettings)){
            return 0;
        }

        if ($pickupPointSettings['trigger_price'] > 0 and $pickupPointSettings['trigger_price'] * 100 <= $totalValue) {
            return (int) round($pickupPointSettings['triggered_price'] * 100.0);
        }

        return (int) round($pickupPointSettings['base_price'] * 100.0);
    }

}
