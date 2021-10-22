<?php

namespace App\Http\Controllers\Shopify;

use App\Exceptions\ShopifyApiException;
use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use App\Http\Middleware\ShopifyTokenMiddleware;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Illuminate\Support\Facades\Session;
use Pakettikauppa\Client;
use Psy\Exception\FatalErrorException;
use Log;

/**
 * @property \App\Models\Shopify\Shop $shop
 */
class SettingsController extends Controller {

    const MSG_OK = 'ok';
    const MSG_ERROR = 'error';

    private $shopifyClient;
    private Shop $shop;
    private Client $pk_client;
    private $pickupPointSettings;
    private $settings;
    private $type;
    private $carrierName;
    private $test_mode;
    protected Request $request;

    public function __construct(Request $request) {
        $this->shopifyClient = null;
        $this->type = config('shopify.type');
        $this->test_mode = config('shopify.test_mode');
        $this->carrierName = config('shopify.carrier_name');
    }

    /**
     * Gives ShopifyClient instance if it is created, creates if not. Can be forced to recreate by using $getNew set as true
     * 
     * @param bool $getNew true to create new ShopifyClient instance
     * 
     * @return \App\Models\Shopify\ShopifyClient
     */
    public function getShopifyClient($getNew = false) {
        if (!$getNew && $this->shopifyClient) {
            return $this->shopifyClient;
        }

        $shop = request()->get('shop');

        $this->shopifyClient = new ShopifyClient(
                $shop->shop_origin,
                $shop->token,
                config('shopify.api_key'),
                config('shopify.secret')
        );

        return $this->shopifyClient;
    }

    /**
     * Returns Shop object by supplied shopOrigin
     * 
     * @param string $shopOrigin
     * 
     * @return \App\Models\Shopify\Shop;
     */
    public function getShop($shopOrigin) {
        return Shop::where('shop_origin', $shopOrigin)->first();
    }

    /**
     * API settings view endpoint
     */
    public function api() {
        return view('settings.api', [
            'shop' => request()->get('shop'),
            'api_valid' => true,
            'type' => $this->type
        ]);
    }

    /**
     * Buttons / Page title translation endpoint
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getButtonsTranslations() {
        return response()->json([
                    'status' => 'OK',
                    'data' => [
                        'value' => [
                            'test_mode_enable_text' => trans('app.settings.testmode_on'),
                            'test_mode_disable_text' => trans('app.settings.testmode_off'),
                        ],
                        'label' => [
                            'buttonSave' => trans('app.settings.save_settings'),
                            'buttonNews' => trans('app.settings.latest-news'),
                            'buttonShipmentSettings' => trans('app.settings.shipment_settings'),
                            'buttonPickupPointsSettings' => trans('app.settings.pickuppoints-settings'),
                            'buttonCompanyInformationSettings' => trans('app.settings.company_info'),
                            'buttonApiSettings' => trans('app.settings.api-settings'),
                            'buttonOtherSettings' => trans('app.settings.generic-settings'),
                            'optionsBtnGroup' => trans('app.settings.settings'),
                        ]
                    ]
        ]);
    }

    /**
     * Request carrier service from shopify by service ID
     * 
     * @param mixed $carrier_service_id
     * 
     * @return mixed carrier service from shopify
     */
    private function getCarrierServiceFromShopify($carrier_service_id) {
        $response = null;

        try {
            $response = $this->getShopifyClient()->call(
                    'GET',
                    'admin',
                    '/carrier_services/' . $carrier_service_id . '.json'
            );

            //Log::debug("Carrier Service: " . var_export($response, true));
        } catch (\Exception $e) {
            Log::debug("Carrier Service Not Found: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Saves carrier service to shopify and return its ID
     * 
     * @return mixed carrier service ID or null
     */
    public function saveCarrierServiceToShopify() {
        $client = $this->getShopifyClient();
        $carrierServiceName = $this->carrierName . ': Noutopisteet / Pickup points';

        $carrierServiceData = array(
            'carrier_service' => array(
                'name' => $carrierServiceName,
                'callback_url' => route('shopify.pickuppoints.list'),
                'service_discovery' => true,
            )
        );

        try {
            $carrierService = $client->call('POST', 'admin', '/carrier_services.json', $carrierServiceData);

            return $carrierService['id'];
        } catch (ShopifyApiException $sae) {
            $exceptionData = array(
                var_export($sae->getMethod(), true),
                var_export($sae->getPath(), true),
                var_export($sae->getParams(), true),
                var_export($sae->getResponseHeaders(), true),
                var_export($sae->getResponse(), true)
            );

            Log::debug('ShopiApiException: ' . var_export($exceptionData, true));

            // it failed, why? Did carrier service already exists but our db shows that it is not active?
            $carrierServices = $client->call('GET', 'admin', '/carrier_services.json');
            
            if (count($carrierServices) > 0) {
                // yes, we have a carrier service!
                foreach ($carrierServices as $_service) {
                    if ($_service['name'] == $carrierServiceName) {

                        // Update callbackurl if it has changed
                        if (($_service['callback_url'] != route('shopify.pickuppoints.list')) || !isset($_service['callback_url'])) {
                            $client->call(
                                    'PUT',
                                    'admin',
                                    '/carrier_services/' . $_service['id'] . '.json',
                                    $carrierServiceData
                            );
                        }

                        return $_service['id'];
                    }
                }
            } else {
                // we just don't know why it failed
            }
        }
        return null;
    }

    /**
     * Pickup points settings view endpoint
     */
    public function pickuppoints() {
        $shop = request()->get('shop');

        if ($shop->carrier_service_id != null) {
            $carrier_service = $this->getCarrierServiceFromShopify($shop->carrier_service_id);
        }
        
        if ($shop->carrier_service_id == null || $carrier_service == null) {
            $carrier_service_id = $this->saveCarrierServiceToShopify();
            $shop->saveCarrierServiceId($carrier_service_id);
        }
        
        $pk_client = $this->getPakketikauppaClient($shop);

        if (!$shop->api_token || $shop->api_token->expires_in < time()){
            return view('settings.api', [
                'shop' => $shop,
                'api_valid' => true,
                'type' => $this->type,
                'error_message' => trans('app.messages.invalid_credentials')
            ]);
        }

        $products = $pk_client->listShippingMethods();

        // dont let it crash and burn
        if (!is_array($products)) {
            $products = array();
        }

        // We want products to be as array and not as object
        $products = json_decode(json_encode($products), true);

        $api_valid = !empty($products);

        // initialize pickup point settings if needed
        $pickupPointSettings = $shop->getPickupPointSettings($products);

        return view('settings.pickuppoints', [
            'pickuppoint_settings' => $pickupPointSettings,
            'shipping_methods' => $products,
            'shop' => $shop,
            'api_valid' => $api_valid,
            'type' => $this->type
        ]);
    }

    /**
     * Sender settings view endpoint
     */
    public function sender() {
        return view('settings.sender', [
            'shop' => request()->get('shop'),
            'type' => $this->type
        ]);
    }

    /**
     * Generic (currently only locale) settings view endpoint
     */
    public function generic() {
        return view('settings.generic', [
            'shop' => request()->get('shop'),
            'type' => $this->type
        ]);
    }

    /**
     * Shipping settings view endpoint
     */
    public function shipping() {
        $shop = request()->get('shop');

        if ($shop->settings == null) {
            $shop->settings = '{}';
        }

        $client = $this->getShopifyClient();

        $shipping_zones = $client->call('GET', 'admin', '/shipping_zones.json');
        $shipping_settings = unserialize($shop->shipping_settings);

        $result_rates = [];
        foreach ($shipping_zones as $shipping_zone) {
            $shipping_rates = $shipping_zone['weight_based_shipping_rates'];
            $shipping_rates = array_merge($shipping_rates, $shipping_zone['price_based_shipping_rates']);

            $shipping_zone_name = $shipping_zone['name'];

            foreach ($shipping_rates as $rate) {
                $arr = [];
                $arr['id'] = $rate['id'];
                $arr['zone'] = $shipping_zone_name;
                $arr['name'] = $rate['name'];
                $arr['product_code'] = '';
                foreach ($shipping_settings as $item) {
                    if ($item['shipping_rate_id'] == $rate['name']) {
                        $arr['product_code'] = $item['product_code'];
                    }
                }
                $result_rates[] = $arr;
            }
        }

        foreach ($result_rates as &$result_rate_a) {
            if (!isset($result_rate_a['duplicate'])) {
                $result_rate_a['duplicate'] = false;
            }

            if (!isset($result_rate_a['same'])) {
                $result_rate_a['same'] = false;
            }

            foreach ($result_rates as &$result_rate_b) {
                if (!empty($result_rate_b['same'])) {
                    continue;
                }

                if (!empty($result_rate_a['same'])) {
                    continue;
                }
                if ($result_rate_a['id'] == $result_rate_b['id']) {
                    continue;
                }
                if ($result_rate_a['name'] != $result_rate_b['name']) {
                    continue;
                }

                if ($result_rate_a['zone'] == $result_rate_b['zone']) {
                    $result_rate_a['same'] = true;
                } else {
                    $result_rate_a['duplicate'] = true;
                    $result_rate_b['duplicate'] = true;
                }
            }
        }

        $grouped_services = [];

        try {
            $pk_client = $this->getPakketikauppaClient($shop);

            if (!$shop->api_token || $shop->api_token->expires_in < time()) {
                return view('settings.api', [
                    'shop' => $shop,
                    'api_valid' => true,
                    'type' => $this->type,
                    'error_message' => trans('app.messages.invalid_credentials')
                ]);
            }

            $resp = $pk_client->listShippingMethods();
            $products = json_decode(json_encode($resp), true);
        } catch (\Exception $ex) {
            throw new FatalErrorException($ex->getMessage());
        }

        $api_valid = isset($products);
        if ($api_valid) {
            $grouped_services = array_group_by($products, function ($i) {
                return $i['service_provider'];
            });
            ksort($grouped_services);
        }

        $pickupPointSettings = $shop->getSettings();

        // initialize pickup point settings if needed
        foreach ($grouped_services as $_key => $_service_provider) {
            if (!isset($pickupPointSettings[$_key])) {
                $pickupPointSettings[$_key]['active'] = 'false';
                $pickupPointSettings[$_key]['base_price'] = '0';
                $pickupPointSettings[$_key]['trigger_price'] = '';
                $pickupPointSettings[$_key]['triggered_price'] = '';
            }
        }

        return view('settings.shipping', [
            'shopify_shipping' => $shipping_zones,
            'pickuppoint_settings' => $pickupPointSettings,
            'shipping_methods' => $grouped_services,
            'shop' => $shop,
            'additional_services' => unserialize($shop->additional_services),
            'api_valid' => $api_valid,
            'shipping_rates' => $result_rates,
            'pickuppoint_providers' => explode(";", $shop->pickuppoint_providers),
            'type' => $this->type
        ]);
    }

    /**
     * Creates pakettikauppa client according to supplied shop settings
     * 
     * @param \App\Models\Shopify\Shop $shop
     * 
     * @return \Pakettikauppa\Client
     */
    public function getPakketikauppaClient($shop) {
        $use_config = null;
        if ($this->type == "posti" || $this->type == "itella") {
            $config = [
                'posti_config' => [
                    'api_key' => $shop->api_key,
                    'secret' => $shop->api_secret,
                    'base_uri' => 'https://nextshipping.posti.fi',
                    'use_posti_auth' => true,
                    'posti_auth_url' => $this->test_mode ? 'https://oauth.barium.posti.com' : 'https://oauth2.posti.com',
                ]
            ];
            $use_config = "posti_config";
            $client = new Client($config, $use_config);

            if (!is_object($shop->api_token) || !$shop->api_token || $shop->api_token->expires_in < time()) {
                $token = $client->getToken();
                if (isset($token->access_token)) {
                    $token->expires_in += time();
                    $shop->api_token = json_encode($token);
                    $shop->save();
                }
            } else {
                $token = $shop->api_token;
            }
            if (isset($token->access_token)) {
                $client->setAccessToken($token->access_token);
            }
            return $client;
        } else {
            $config = [
                'test_mode' => true
            ];
            if (!$shop->test_mode) {
                $config = [
                    'api_key' => $shop->api_key,
                    'secret' => $shop->api_secret,
                ];
            }
        }

        return new Client($config, $use_config);
    }

    /**
     * Calls pakettikauppa api to check if key and secret is valid
     * 
     * @param string $api_key
     * @param string $api_secret
     * 
     * @return bool true if credentials valid, false otherwise
     */
    public function isApiCredentialsValid($api_key, $api_secret) {
        if (!$api_secret || !$api_secret) {
            return false;
        }

        if ($this->type == "posti" || $this->type == "itella") {

            $config = [
                'posti_config' => [
                    'api_key' => $api_key,
                    'secret' => $api_secret,
                    'base_uri' => $this->test_mode ? 'https://argon.api.posti.fi/ecommerce/v3/' : 'https://nextshipping.posti.fi',
                    'use_posti_auth' => true,
                    'posti_auth_url' => $this->test_mode ? 'https://oauth.barium.posti.com' : 'https://oauth2.posti.com',
                ]
            ];
            $client = new Client($config, 'posti_config');
            $token = $client->getToken();
            if (isset($token->access_token)) {
                $client->setAccessToken($token->access_token);
            }
        } else {
            $client = new Client([
                'api_key' => $api_key,
                'secret' => $api_secret,
            ]);
        }
        try {
            $result = $client->listShippingMethods();
        } catch (\Exception $e) {
            return false;
        }

        return is_array($result);
    }

    /**
     * Gives back test mode message according to its status
     * 
     * @param bool $test_mode test mode status
     * 
     * @return string test mode message
     */
    private function testModeMessage($test_mode) {
        return $test_mode ? trans('app.messages.in-testing') : trans('app.messages.in-production');
    }

    /**
     * Sets shop test mode
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTestMode() {
        $shop = request()->get('shop');
        $test_mode = (bool) request()->get('test_mode', true);

        Log::debug($shop->shop_origin . ' TESTMODE: ' . json_encode($test_mode));

        // Test mode OFF but missing either api_key or secret
        if (!$test_mode && (!$shop->api_key || !$shop->api_secret)) {
            return response()->json([
                        'status' => self::MSG_ERROR,
                        'message' => trans('app.messages.credentials_missing')
            ]);
        }

        $isSaved = $shop->saveTestMode($test_mode);

        return response()->json([
                    'status' => $isSaved ? self::MSG_OK : self::MSG_ERROR,
                    'message' => $isSaved ? $this->testModeMessage($test_mode) : trans('app.settings.save_failed')
        ]);
    }

    /**
     * Sets shop api key and secret
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateApiSettings() {
        $api_key = request()->get('api_key');
        $api_secret = request()->get('api_secret');
        $shop = request()->get('shop');

        $result = array();

        if (!$this->isApiCredentialsValid($api_key, $api_secret)) {
            $result = [
                'status' => self::MSG_ERROR,
                'message' => trans('app.messages.invalid_credentials'),
            ];

            return response()->json($result);
        }

        $isSaved = $shop->saveApiCredentials($api_key, $api_secret);

        return response()->json([
                    'status' => $isSaved ? self::MSG_OK : self::MSG_ERROR,
                    'message' => $isSaved ? trans('app.settings.saved') : trans('app.settings.save_failed')
        ]);
    }

    /**
     * Builds service provider list
     * 
     * @param array $products Service providers list from pakettikauppa client
     * 
     * @return array product providers array with method code as key and service provider as value
     */
    private function getProductProvidersByCode($products) {
        $productProviderByCode = array('NO_SHIPPING' => '');
        foreach ($products as $_product) {
            $productProviderByCode[$_product->shipping_method_code] = $_product->service_provider;
        }

        return $productProviderByCode;
    }

    /**
     * Save shipping settings
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateShippingSettings() {
        $shop = request()->get('shop');

        $pk_client = $this->getPakketikauppaClient($shop);
        $products = $pk_client->listShippingMethods();

        if (!is_array($products)) {
            $result = [
                'status' => self::MSG_ERROR,
                'message' => trans('app.messages.invalid_credentials'),
            ];

            return response()->json($result);
        }

        $productProviderByCode = $this->getProductProvidersByCode($products);
        $shipping_settings = $shop->buildShippingSettings(request()->get('shipping_method'), $productProviderByCode);

        $shop_shipping_settings = array(
            'shipping_settings' => $shipping_settings,
            'default_service_code' => request()->get('default_shipping_method'),
            'always_create_return_label' => (bool) request()->get('print_return_labels'),
            'create_activation_code' => (bool) request()->get('create_activation_code'),
        );

        $isSaved = $shop->saveShippingSettings($shop_shipping_settings);

        return response()->json([
                    'status' => $isSaved ? self::MSG_OK : self::MSG_ERROR,
                    'message' => $isSaved ? trans('app.settings.saved') : trans('app.settings.save_failed')
        ]);
    }

    /**
     * Save locale setting
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLocale() {
        $shop = request()->get('shop');
        $locale = request()->get('language');

        if (!$shop || !$locale || !$shop->saveLocale($locale)) {

            return response()->json([
                        'status' => self::MSG_ERROR,
                        'message' => trans('app.settings.save_failed')
            ]);
        }

        \App::setLocale($shop->locale);

        return response()->json([
                    'status' => self::MSG_OK,
                    'message' => trans('app.settings.saved'),
                    'html' => view('settings.generic', [
                        'shop' => request()->get('shop'),
                        'type' => $this->type
                    ])->toHtml()
        ]);
    }

    /**
     * Updates sender information
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSender() {
        $shop = request()->get('shop');

        $sender_data = array(
            'business_name' => request()->get('business_name'),
            'address' => request()->get('address'),
            'postcode' => request()->get('postcode'),
            'city' => request()->get('city'),
            'country' => request()->get('country'),
            'email' => request()->get('email'),
            'phone' => request()->get('phone'),
            'iban' => request()->get('iban'),
            'bic' => request()->get('bic'),
        );

        $isSaved = $shop->saveSender($sender_data);

        return response()->json([
                    'status' => $isSaved ? self::MSG_OK : self::MSG_ERROR,
                    'message' => $isSaved ? trans('app.settings.saved') : trans('app.settings.save_failed')
        ]);
    }

    /**
     * Sets default values based on posted data
     * 
     * @param array $pickuppoints Data from pickup points settings form
     * 
     * @return array
     */
    public function prepPickupPointsData($pickuppoints) {
        foreach ($pickuppoints as $_pickupPoint) {
            if ($_pickupPoint['base_price'] == '') {
                $_pickupPoint['base_price'] = 0;
            }

            if ($_pickupPoint['triggered_price'] == '') {
                $_pickupPoint['trigger_price'] = '';
            }
        }

        return $pickuppoints;
    }

    /**
     * Updates pickup points settings
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePickupPoints() {
        $shop = request()->get('shop');

        $data = array(
            'pickuppoints_count' => (int) request()->get('pickuppoints_count'),
            'include_discounted_price_in_trigger' => (bool) request()->get('include_discounted_price_in_trigger'),
            'settings' => json_encode($this->prepPickupPointsData(request()->get('pickuppoint'))),
        );

        $isSaved = $shop->savePickupPointsSettings($data);

        return response()->json([
                    'status' => $isSaved ? self::MSG_OK : self::MSG_ERROR,
                    'message' => $isSaved ? trans('app.settings.saved') : trans('app.settings.save_failed')
        ]);
    }

}
