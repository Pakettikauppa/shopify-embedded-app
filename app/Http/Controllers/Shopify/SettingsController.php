<?php

namespace App\Http\Controllers\Shopify;

use App\Exceptions\ShopifyApiException;
use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Pakettikauppa\Client;
use Psy\Exception\FatalErrorException;
use Log;

/**
 * @property \App\Models\Shopify\Shop $shop
 */
class SettingsController extends Controller
{
    private $client;
    private $shop;
    private $pk_client;

    public function __construct(Request $request)
    {
        $this->middleware(function ($request, $next) {

            if (!session()->has('shop')) {
                session()->put('init_request', $request->fullUrl());

                $params = $request->all();

                return redirect()->route('shopify.auth.index', $params);
            }

            $shop_origin = session()->get('shop');
            $shop = Shop::where('shop_origin', $shop_origin)->first();
            if (!isset($shop)) {
                session()->put('init_request', $request->fullUrl());
                return redirect()->route('shopify.auth.index', request()->all());
            }

            /*
            if (session()->get('shopify_version') != 1) {
                session()->flush();
            }
            */
            $this->shop = $shop;
            if ($shop->settings == null) {
                $shop->settings = '{}';
            }
            $this->settings = json_decode($shop->settings, true);
            $this->pickupPointSettings = $this->settings->pickup_points;

            $this->client = new ShopifyClient(
                $shop->shop_origin,
                $shop->token,
                ENV('SHOPIFY_API_KEY'),
                ENV('SHOPIFY_SECRET')
            );

            // TODO how to make this work without this - cache?
            try {
                $this->client->call('GET', 'admin', '/shop.json');
            } catch (ShopifyApiException $e) {
                Log::debug("ARE WE EVER GOING HERE??");
                session()->put('init_request', $request->fullUrl());
                return redirect()->route('shopify.auth.index', request()->all());
            }

            // set pk_client
            if ($this->shop->test_mode) {
                $pk_client_params = [
                    'test_mode' => true,
                ];
            } else {
                if (isset($this->shop->api_key) && isset($this->shop->api_secret)) {
                    $pk_client_params = [
                        'api_key' => $this->shop->api_key,
                        'secret' => $this->shop->api_secret,
                    ];
                }
            }

            if (is_array($pk_client_params)) {
                $this->pk_client = new Client($pk_client_params);
            }

            \App::setLocale($this->shop->locale);

            return $next($request);
        });
    }

    public function api()
    {
        return view('settings.api', [
            'shop' => $this->shop,
            'api_valid' => true
        ]);
    }

    public function pickuppoints()
    {
        if ($this->shop->carrier_service_id != null) {
            try {
                $resp = $this->client->call(
                    'GET',
                    'admin',
                    '/carrier_services/' . $this->shop->carrier_service_id . '.json');

                Log::debug("Carrier Service: " . var_export($resp, true));
            } catch (\Exception $e) {
                Log::debug("Carrier Service Not Found: " . $e->getMessage());

                $this->shop->carrier_service_id = null;
                $this->shop->save();
            }
        }

        if ($this->shop->carrier_service_id == null) {
            $carrierServiceName = 'Pakettikauppa: Noutopisteet / Pickup points';

            $carrierServiceData = array(
                'carrier_service' => array(
                    'name' => $carrierServiceName,
                    'callback_url' => 'http://209.50.56.85/api/pickup-points',
                    'service_discovery' => true,
                )
            );

            // TODO: cache this result so we don't bug users with every request

            try {
                $carrierService = $this->client->call('POST', 'admin', '/carrier_services.json', $carrierServiceData);

                // set carrier_service_id and set it's default count value
                $this->shop->carrier_service_id = $carrierService['id'];
                $this->shop->pickuppoints_count = 10;

                $this->shop->save();
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
                $carrierServices = $this->client->call('GET', 'admin', '/carrier_services.json');

                if (count($carrierServices) > 0) {
                    // yes, we have a carrier service!
                    foreach ($carrierServices as $_service) {
                        if ($_service['name'] == $carrierServiceName) {
                            $this->shop->carrier_service_id = $_service['id'];
                            $this->shop->pickuppoints_count = 10;
                            $this->shop->save();

                            if ($_service['callback_url'] != 'http://209.50.56.85/api/pickup-points') {
                                $this->client->call(
                                    'PUT',
                                    'admin',
                                    '/carrier_services/' . $this->shop->carrier_service_id . '.json',
                                    $carrierServiceData
                                );
                            }
                        }
                    }
                } else {
                    // we just don't know why it failed
                }
            }
        }

        try {
            $resp = $this->pk_client->listShippingMethods();
            $products = json_decode($resp, true);
        } catch (\Exception $ex) {
            throw new FatalErrorException();
        }
        $grouped_services = [];

        $api_valid = !empty($products);

        if ($api_valid) {
            $grouped_services = array_group_by($products, function ($i) {
                return $i['service_provider'];
            });
            ksort($grouped_services);
        }

        // initialize pickup point settings if needed
        foreach ($grouped_services as $_key => $_service_provider) {
            if (!isset($this->pickupPointSettings[$_key])) {
                $this->pickupPointSettings[$_key]['active'] = 'false';
                $this->pickupPointSettings[$_key]['base_price'] = '0';
                $this->pickupPointSettings[$_key]['trigger_price'] = '';
                $this->pickupPointSettings[$_key]['triggered_price'] = '';
            }
        }

        return view('settings.pickuppoints', [
            'pickuppoint_settings' => $this->pickupPointSettings,
            'shipping_methods' => $grouped_services,
            'shop' => $this->shop,
            'additional_services' => unserialize($this->shop->additional_services),
            'api_valid' => $api_valid,
            'pickuppoint_providers' => explode(";", $this->shop->pickuppoint_providers)
        ]);
    }

    public function sender()
    {
        return view('settings.sender', [
            'shop' => $this->shop
        ]);
    }

    public function generic()
    {
        return view('settings.generic', [
            'shop' => $this->shop
        ]);
    }

    public function shipping()
    {
        $shipping_zones = $this->client->call('GET', 'admin', '/shipping_zones.json');
        $shipping_settings = unserialize($this->shop->shipping_settings);

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


        $grouped_services = [];

        try {
            $resp = $this->pk_client->listShippingMethods();
            $products = json_decode($resp, true);
        } catch (\Exception $ex) {
            throw new FatalErrorException();
        }
        $api_valid = isset($products);
        if ($api_valid) {
            $grouped_services = array_group_by($products, function ($i) {
                return $i['service_provider'];
            });
            ksort($grouped_services);
        }

        // initialize pickup point settings if needed
        foreach ($grouped_services as $_key => $_service_provider) {
            if (!isset($this->pickupPointSettings[$_key])) {
                $this->pickupPointSettings[$_key]['active'] = 'false';
                $this->pickupPointSettings[$_key]['base_price'] = '0';
                $this->pickupPointSettings[$_key]['trigger_price'] = '';
                $this->pickupPointSettings[$_key]['triggered_price'] = '';
            }
        }

        return view('settings.shipping', [
            'pickuppoint_settings' => $this->pickupPointSettings,
            'shipping_methods' => $grouped_services,
            'shop' => $this->shop,
            'additional_services' => unserialize($this->shop->additional_services),
            'api_valid' => $api_valid,
            'shipping_rates' => $result_rates,
            'pickuppoint_providers' => explode(";", $this->shop->pickuppoint_providers)
        ]);
    }

    public function updateSettings(Request $request)
    {
        $responseStatus = 'error';
        $responseMessage = 'unknown';

        Log::debug(var_export($request->all(), true));

        if (isset($request->test_mode)) {
            if (isset($this->shop->api_key) && isset($this->shop->api_secret)) {
                Log::debug('TESTMODE:' . $request->test_mode);
                $responseStatus = 'ok';
                if ($request->test_mode == 'true') {
                    $responseMessage = trans('app.messages.in-testing');
                } else {
                    $responseMessage = trans('app.messages.in-production');
                }
                $this->shop->test_mode = $request->test_mode == 'true';
            } else {
                $responseStatus = 'error';
                $responseMessage = trans('app.messages.credentials_missing');
            }
        }


        // api
        if (isset($request->api_key)) {
            if (!isset($request->api_key) || !isset($request->api_secret)) {
                $result = [
                    'status' => 'error',
                    'message' => trans('app.messages.invalid_credentials'),
                ];

                return response()->json($result);
            }

            // api check for production

            $client = new Client([
                'api_key' => $request->api_key,
                'secret' => $request->api_secret,
            ]);

            $result = json_decode($client->listShippingMethods());
            if (!is_array($result)) {
                $result = [
                    'status' => 'error',
                    'message' => trans('app.messages.invalid_credentials'),
                ];
                return response()->json($result);
            }

            $this->shop->api_key = $request->api_key;
            $this->shop->api_secret = $request->api_secret;

            $responseStatus = 'ok';
            $responseMessage = trans('app.settings.saved');
        }

        // shipping
        if (isset($request->default_shipping_method)) {
            try {
                $resp = $this->pk_client->listShippingMethods();
                $products = json_decode($resp, true);
            } catch (\Exception $ex) {
                $responseStatus = 'error';
                $responseMessage = trans('invalid_credentials');

                $result = [
                    'status' => $responseStatus,
                    'message' => $responseMessage
                ];

                return response()->json($result);
            }

            $productProviderByCode = array();
            foreach ($products as $_product) {
                $productProviderByCode[(string)$_product['shipping_method_code']] = $_product['service_provider'];
            }

            // Old settings
            $shipping_settings = [];
            if (isset($request->shipping_method)) {
                foreach ($request->shipping_method as $key => $code) {
                    $shipping_settings[] = [
                        'shipping_rate_id' => $key,
                        'product_code' => $code,
                        'service_provider' => ($code == null ? '' : $productProviderByCode[(string)$code])
                    ];
                }
            }


            $this->shop->shipping_settings = serialize($shipping_settings);

            if ($request->default_shipping_method != '') {
                $this->shop->default_service_code = $request->default_shipping_method;
            }

            $this->shop->always_create_return_label = (bool)$request->print_return_labels;
            $this->shop->create_activation_code = (bool)$request->create_activation_code;

            $responseStatus = 'ok';
            $responseMessage = trans('app.settings.saved');
        }

        // sender
        if (isset($request->business_name)) {
            $this->shop->business_name = $request->business_name;
            $this->shop->address = $request->address;
            $this->shop->postcode = $request->postcode;
            $this->shop->city = $request->city;
            $this->shop->country = $request->country;
            $this->shop->email = $request->email;
            $this->shop->phone = $request->phone;
            $this->shop->iban = $request->iban;
            $this->shop->bic = $request->bic;

            $responseStatus = 'ok';
            $responseMessage = trans('app.settings.saved');
        }

        // pickup points
        if (isset($request->pickuppoints_count)) {
            $this->shop->pickuppoints_count = $request->pickuppoints_count;

            $this->shop->include_discounted_price_in_trigger = (bool)$request->include_discounted_price_in_trigger;

            $pickuppoints = $request->pickuppoint;
            foreach ($pickuppoints as $_pickupPoint) {
                if ($_pickupPoint['base_price'] == '') {
                    $_pickupPoint['base_price'] = 0;
                }

                if ($_pickupPoint['triggered_price'] == '') {
                    $_pickupPoint['trigger_price'] = '';
                }
            }
            $this->shop->settings = json_encode($pickuppoints);

            $responseStatus = 'ok';
            $responseMessage = trans('app.settings.saved');
        }

        // generic
        if (isset($request->language)) {
            $this->shop->locale = $request->language;

            $responseStatus = 'ok-reload';
            $responseMessage = trans('app.settings.saved');
        } else {
            if (!isset($this->shop->locale)) {
                $this->shop->locale = 'fi';
            }
        }

        $this->shop->save();

        \App::setLocale($this->shop->locale);

        $result = [
            'status' => $responseStatus,
            'message' => $responseMessage
        ];

        return response()->json($result);
    }

    public function setApiCredentials(Request $request)
    {

        $result = [
            'status' => 'ok'
        ];

        return response()->json($result);
    }

    public function setupWizard()
    {
        return view('app.setup-wizard', [
            'shop' => $this->shop
        ]);
    }
}
