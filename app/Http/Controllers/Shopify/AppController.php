<?php

namespace App\Http\Controllers\Shopify;

use App\Exceptions\ShopifyApiException;
use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use App\Models\Shopify\Shop;
use App\Models\Shopify\Shipment as ShopifyShipment;
use Pakettikauppa\Client;
use Pakettikauppa\Shipment;
use Psy\Exception\FatalErrorException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
//use Log;
use Storage;

/**
 * @property \App\Models\Shopify\Shop $shop
 */
class AppController extends Controller {

    /**
     * @var ShopifyClient
     */
    private $client;

    /**
     * @var Client
     */
    private $pk_client;
    private $shopifyClient;
    private $fullfill_order = false;
    private $is_return = false;
    private $type;
    private $test_mode;
    private $tracking_url;

    public function __construct(Request $request) {
        $this->type = config('shopify.type');
        $this->test_mode = config('shopify.test_mode');
        $this->tracking_url = config('shopify.tracking_url');
    }

    /**
     * Shopify has a bug and this function is used to handle that
     *
     * @param array $arr
     * @return array
     */
    private function flattenArray($arr) {
        $values = [];
        foreach ($arr as $item) {
            if (is_array($item)) {
                $values = array_merge($values, $this->flattenArray($item));
            } else {
                $values[] = $item;
            }
        }
        return $values;
    }

    /**
     * Creates pakettikauppa client with supplied key and secret
     * 
     * @param \App\Models\Shopify\Shop $shop
     * 
     * @return \Pakettikauppa\Client
     */
    public function getPakketikauppaClient($shop) {

        if ($this->type == "posti" || $this->type == "itella") {
            $config = [
                'posti_config' => [
                    'api_key' => $shop->api_key,
                    'secret' => $shop->api_secret,
                    'base_uri' => $this->test_mode ? 'https://argon.api.posti.fi' : 'https://nextshipping.posti.fi',
                    'use_posti_auth' => true,
                    'posti_auth_url' => $this->test_mode ? 'https://oauth.barium.posti.com' : 'https://oauth2.posti.com',
                ]
            ];
            $use_config = 'posti_config';
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

        return new Client($config);
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

    public function printLabels(Request $request) {
        if (!isset($request->ids) && !isset($request->id)) {
            Log::debug('No id found');
            throw new NotFoundHttpException();
        }

        $fulfill_order = $this->fullfill_order;
        $is_return = $this->is_return;

        $shop = request()->get('shop');

        // api check
        $this->pk_client = $this->getPakketikauppaClient($shop);
        $result = $this->pk_client->listShippingMethods();

        if (!is_array($result)) {
            Log::debug("List Shipping Methods error!");

            return view('app.alert', [
                'shop' => $shop,
                'type' => 'error',
                'title' => trans('app.messages.invalid_credentials'),
                'message' => trans('app.messages.no_api_set_error', ['settings_url' => route('shopify.settings')]),
            ]);
        }

        $this->client = $this->getShopifyClient();

        if (isset($request->ids)) {
            $order_ids = $request->ids;
        } else {
            $order_ids = [$request->id];
        }

        try {
            $orders = $this->client->getOrders($order_ids);
            /*
              $orders = $this->client->call(
              'GET',
              'admin',
              '/orders.json',
              ['ids' => implode(',', $order_ids), 'status' => 'any']
              ); */
        } catch (ShopifyApiException $sae) {
            Log::debug('Unauthorized thingie');

            return redirect()->route('install-link', request()->all());
        }

        $shipments = [];

        foreach ($orders['orders']['edges'] as $orderNode) {
            $order = $orderNode['node'];
            //assign to id in case somewhere not changed
            $order['gid'] = $order['id'];
            $order['id'] = $order['legacyResourceId'];
            $shipment = [];
            $shipment['fulfillment_status'] = !empty($order['fulfillments']) ? $order['fulfillments'][0]['status'] : '';
            $shipment['line_items'] = [];
            foreach ($order['lineItems']['edges'] as $line_item) {
                if ($line_item['node']['requiresShipping']) {
                    $shipment['line_items'][] = $line_item['node'];
                }
            }
            $shipment['id'] = $order['legacyResourceId'];
            $shipment['gid'] = $order['gid'];
            $shipment['admin_order_url'] = 'https://' . $shop->shop_origin . '/admin/orders/' . $order['legacyResourceId'];
            $url_params = [
                'shop' => $shop->shop_origin,
                'is_return' => $is_return ? '1' : '0',
            ];

            $url_params['hmac'] = createShopifyHMAC($url_params);
            $shipment['hmac_print_url'] = http_build_query($url_params);

            if (empty($shipment['line_items'])) {
                $shipment['status'] = 'nothing_to_ship';
                $shipments[] = $shipment;
                continue;
            }

            $done_shipment = ShopifyShipment::where('shop_id', $shop->id)
                    ->where('order_id', $order['legacyResourceId'])
                    ->where('test_mode', $shop->test_mode)
                    ->where('return', $is_return)
                    ->first();

            if ($done_shipment) {
                $shipment['status'] = 'sent';

                if(strpos($done_shipment->tracking_code, ','))
                {
                    $tracking_codes = explode(', ', $done_shipment->tracking_code);
                }
                else
                {
                    $tracking_codes[] = $done_shipment->tracking_code;
                }
                $shipment['tracking_codes'] = $tracking_codes;
                $shipments[] = $shipment;
                continue;
            }

            if (!isset($order['shippingAddress']) and!isset($order['billingAddress'])) {
                $shipment['status'] = 'need_shipping_address';
                $shipments[] = $shipment;
                continue;
            }

            /*
              if ($order['gateway'] == 'Cash on Delivery (COD)') {

              }
             */

            if (isset($order['shippingAddress'])) {
                $shipping_address = $order['shippingAddress'];
            } else {
                $shipping_address = $order['billingAddress'];
            }

            $senderInfo = [
                'name' => $shop->business_name,
                'company' => '',
                'address' => $shop->address,
                'postcode' => $shop->postcode,
                'city' => $shop->city,
                'country' => $shop->country,
                'phone' => $shop->phone,
                'email' => $shop->email,
            ];

            $receiverPhone = $shipping_address['phone'];

            if (empty($receiverPhone) and isset($order['billingAddress']['phone'])) {
                $receiverPhone = $order['billingAddress']['phone'];
            }

            if (empty($receiverPhone)) {
                $receiverPhone = $order['phone'];
            }

            if (empty($receiverPhone) and isset($order['customer']['phone'])) {
                $receiverPhone = $order['customer']['phone'];
            }

            $receiverName = $shipping_address['name'];
            $receiverCompany = $shipping_address['company'];
            if (empty($receiverCompany)) {
                $receiverCompany = null;
            }
            $receiverAddress = $shipping_address['address1'];
            $receiverAddress2 = empty($shipping_address['address2']) ? null : $shipping_address['address2'];

            $receiverZip = $shipping_address['zip'];
            $receiverCity = $shipping_address['city'];
            $receiverCountry = $shipping_address['countryCode'];

            $receiverInfo = [
                'name' => $receiverName,
                'company' => $receiverCompany,
                'address' => $receiverAddress,
                'address2' => $receiverAddress2,
                'postcode' => $receiverZip,
                'city' => $receiverCity,
                'country' => $receiverCountry,
                'phone' => $receiverPhone,
                'email' => $order['email'],
            ];
            if ($is_return) {
                $tmp = $receiverInfo;
                $receiverInfo = $senderInfo;
                $senderInfo = $tmp;
            }

            $contents = $shipment['line_items'];

            $_shipment = $shop->sendShipment(
                    $this->pk_client,
                    $order,
                    $senderInfo,
                    $receiverInfo,
                    $contents,
                    $is_return
            );
            $shipment['status'] = $_shipment['status'];
            $shipment['tracking_code'] = '';
            
            if (isset($_shipment['tracking_code'])) {
                if (is_array($_shipment['tracking_code'])) 
                {
                    $tracking_codes = $_shipment['tracking_code'];
                } 
                else if (strpos($_shipment['tracking_code'], ','))
                {
                    $tracking_codes = explode(', ', $_shipment['tracking_code']);
                }
                else
                {
                    $tracking_codes = [$_shipment['tracking_code']];
                }
                $shipment['tracking_codes'] = $tracking_codes;
            }
            
            if (
                    !empty($this->pk_client->getResponse()->{'response.trackingcode'}['labelcode']) and
                    $shop->create_activation_code === true
            ) {
                try {
                    $query_params = $this->client->buildGraphQLInput(['id' => $order['gid'], 'note' => sprintf('%s: %s', trans('app.settings.activation_code'), $this->pk_client->getResponse()->{'response.trackingcode'}['labelcode'])]);
                    $query = <<<GQL
                            mutation UpdateOrder {
                                orderUpdate(input: $query_params)
                            }        
                            GQL;
                    $this->client->call($query);
                    /*
                      if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                      sleep(2);
                      }

                      $this->client->call(
                      'PUT',
                      'admin',
                      '/orders/' . $order['id'] . '.json',
                      [
                      'order' => [
                      'id' => $order['id'],
                      'note' => sprintf('%s: %s', trans('app.settings.activation_code'), $this->pk_client->getResponse()->{'response.trackingcode'}['labelcode'])
                      ]
                      ]
                      );
                     */
                } catch (\Exception $e) {
                    Log::debug($e->getMessage());
                    Log::debug($e->getTraceAsString());
                }
            }

            if (isset($_shipment['error_message'])) {
                $shipment['error_message'] = $_shipment['error_message'];
            }

            $shipments[] = $shipment;

            Log::debug("Processed order: {$shipment['tracking_code']} - {$order['id']}");
        }

        if ($fulfill_order) {
            foreach ($shipments as $orderKey => $order) {
                if (empty($order['tracking_codes'])) {
                    continue;
                }

                Log::debug("Fullfilling order: " .implode(', ',$order['tracking_codes']) ." - {$order['id']}");

                if ($order['fulfillment_status'] == 'fulfilled') {
                    continue;
                }
                if ($order['status'] == 'custom_error') {
                    continue;
                }
                if ($order['status'] == 'need_shipping_address') {
                    continue;
                }

                $services = [];

                foreach ($order['line_items'] as $item) {
                    //$variantId = $item['variant_id'];

                    try {
                        /*
                          if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                          sleep(2);
                          }

                          $variants = $this->client->call('GET', 'admin', '/variants/' . $variantId . '.json');

                          $inventoryId = $variants['inventory_item_id'];

                          if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                          sleep(2);
                          }
                          // TODO: not the most efficient way to do this
                          // got from graphql

                          $inventoryLevels = $this->client->call(
                          'GET',
                          'admin',
                          '/inventory_levels.json',
                          [
                          'inventory_item_ids' => $inventoryId
                          ]
                          );
                         */
                        $makeNull = true;
                        $inventoryLevels = $item['variant']['inventoryItem']['inventoryLevels']['edges'];
                        foreach ($inventoryLevels as $_inventory) {
                            if ($_inventory['node']['available'] > 0 || $_inventory['node']['available'] == null) {
                                $service = $item['variant']['fulfillmentService']['type'];
                                $services[$service][$_inventory['node']['location']['id']] = ['id' => $item['id']];
                                $makeNull = false;
                            } else {
                                $shipments[$orderKey]['status'] = 'not_in_inventory';
                            }
                        }

                        if ($makeNull) {
                            Log::debug("NULL item: {$item['id']} - " . var_export($inventoryLevels, true));
                        }
                    } catch (ShopifyApiException $sae) {
                        $exceptionData = array(
                            var_export($sae->getMethod(), true),
                            var_export($sae->getPath(), true),
                            var_export($sae->getParams(), true),
                            var_export($sae->getResponseHeaders(), true),
                            var_export($sae->getResponse(), true)
                        );

                        Log::debug('ShopiApiException: ' . var_export($exceptionData, true));
                    } catch (\Exception $e) {
                        Log::debug(var_export($item, true));
                        Log::debug('Fullfillment Exception: ' . $e->getTraceAsString());
                    }
                }


                foreach ($services as $line_items) {
                    foreach ($line_items as $locationId => $items) {
                        $fulfillment = [
                            'orderId' => $order['gid'],
                            'trackingNumbers' => $order['tracking_code'],
                            'locationId' => $locationId,
                            'trackingCompany' => trans('app.settings.company_name_' . $this->type),
                            'trackingUrls' => $this->tracking_url . $order['tracking_code'],
                            'lineItems' => $items,
                        ];

                        try {
                            /*
                              if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                              sleep(2);
                              }
                             *
                             */
                            $query_params = $this->client->buildGraphQLInput($fulfillment);
                            $query = <<<GQL
                            mutation CreateFulfillment {
                                fulfillmentCreate(
                                  input: $query_params
                                )
                                {
                                    userErrors {
                                      field
                                      message
                                    }
                                }
                              }        
                            GQL;
                            $result = $this->client->call($query);
                            /*
                              $result = $this->client->call(
                              'POST',
                              'admin',
                              '/orders/' . $order['id'] . '/fulfillments.json',
                              [
                              'fulfillment' => $fulfillment
                              ]
                              );
                             *
                             */
                            Log::debug(var_export($result, true));
                        } catch (ShopifyApiException $sae) {
                            $exceptionData = array(
                                var_export($sae->getMethod(), true),
                                var_export($sae->getPath(), true),
                                var_export($sae->getParams(), true),
                                var_export($sae->getResponseHeaders(), true),
                                var_export($sae->getResponse(), true)
                            );

                            Log::debug('ShopiApiException: ' . var_export($exceptionData, true));
                        } catch (\Exception $e) {
                            Log::debug('Fullfillment Exception: ' . $e->getTraceAsString());
                        }
                    }
                }
                Log::debug("Fullfilled order: {$order['id']}");
            }
        }

        $page_title = 'print_label';
        if ($is_return) {
            $page_title = 'return_label';
        }
        if ($fulfill_order) {
            $page_title = 'print_label_fulfill';
        }

        $print_all_url_params = [
            'shop' => $shop->shop_origin,
            'is_return' => $is_return ? '1' : '0',
        ];

        $print_all_url_params['hmac'] = createShopifyHMAC($print_all_url_params);
        $hmac_print_all_url = http_build_query($print_all_url_params);

        return view('app.print-labels', [
            'shop' => $shop,
            'orders' => $shipments,
            'orders_url' => 'https://' . $shop->shop_origin . '/admin/orders',
            'print_all_params' => $hmac_print_all_url,
            'page_title' => $page_title,
            'is_return' => $is_return,
            'tracking_url' => $this->tracking_url,
            'type' => $this->type
        ]);
    }

    public function fulfillmentProcess(Request $request) {
        Logg::debug(var_export($request->all()));
    }

    public function customShipment(Request $request)
    {
        $shop = request()->get('shop');
        if (!$shop->api_token || $shop->api_token->expires_in < time()){
            return view('settings.api', [
                'shop' => $shop,
                'api_valid' => true,
                'type' => $this->type,
                'error_message' => trans('app.messages.invalid_credentials')
            ]);
        }

        $order_id = request()->get('id');
        $this->client = $this->getShopifyClient();
        try {
            $order = $this->client->call(
                'GET',
                'admin',
                "/orders/{$order_id}.json",
            );
        } catch (ShopifyApiException $sae) {
            Log::debug('Unauthorized.');
            return redirect()->route('install-link', request()->all());
        }

        $pk_client = $this->getPakketikauppaClient($shop);
        $shipping_methods = $pk_client->listShippingMethods();
        $services = array_keys(json_decode($shop->settings, true));
        $services[] = $shop->default_service_code;
        foreach (unserialize($shop->shipping_settings) as $setting)
        {
            if($setting['product_code'])
                $services[] = $setting['product_code'];
        }

        // Remove inactive services
        foreach ($shipping_methods as $key => $shipping_method) {
            if (!in_array($shipping_method->shipping_method_code, $services))
            {
                unset($shipping_methods[$key]);
            }
        }
        if (!is_array($shipping_methods)) {
            $shipping_methods = array();
        }

        // Make sure that child elements are not objects.
        $shipping_methods = json_decode(json_encode($shipping_methods), true);

        $api_valid = isset($shipping_methods);
        if ($api_valid) {
            $shipping_methods = array_group_by($shipping_methods, function ($i) {
                return $i['service_provider'];
            });
            ksort($shipping_methods);
        }
        $hmac = $request->get('hmac');
        $shipping_address = $this->getShippingAddressFromOrder($order);
        return view('app.custom-shipment', [
            'hmac' => $hmac,
            'shop' => $shop,
            'shipping_methods' => $shipping_methods,
            'order_id' => $order_id,
            'type' => $this->type,
            'shipping_address' => $shipping_address,
            'email' => $order['email']
        ]);
    }

    public function getShippingAddressFromOrder($order)
    {
        return [
            'first_name' => $order['shipping_address']['first_name'],
            'last_name' => $order['shipping_address']['last_name'],
            'company' => $order['shipping_address']['company'],
            'address1' => $order['shipping_address']['address1'],
            'address2' => $order['shipping_address']['address2'],
            'zip' => $order['shipping_address']['zip'],
            'city' => $order['shipping_address']['city'],
            'country_code' => $order['shipping_address']['country_code'],
            'phone' => $order['shipping_address']['phone'],
        ];
    }

    public function ajaxLoadPickups()
    {
        $shop = Shop::where('shop_origin', request()->get('shop')->shop_origin)->first();
        if ($shop == null) {
            return response()->json([
                'message' => 'Could not get shop object.',
                'status' => 'error',
            ]);
        }

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

        $pk_client = new Client($pk_client_params, $pk_use_config);
        if ($pk_use_config == "posti_config"){
            $token = $pk_client->getToken();
            if (isset($token->access_token)){
                $pk_client->setAccessToken($token->access_token);
            }
        }

        // test if pickup points are available in settings
        if (!(isset($shop->pickuppoints_count) && $shop->pickuppoints_count > 0)) {
            Log::debug("no pickup point counts");
            return;
        }

        if ($shop->settings == null) {
            $shop->settings = '{}';
        }
        $this->pickupPointSettings = json_decode($shop->settings, true);
        $order_id = request()->get('order_id');
        $this->client = $this->getShopifyClient();
        try {
            $order = $this->client->call(
                'GET',
                'admin',
                "/orders/{$order_id}.json",
            );
        } catch (ShopifyApiException $sae) {
            Log::debug('Unauthorized.');
            return redirect()->route('install-link', request()->all());
        }

        $rates = [];
        if (count($this->pickupPointSettings) > 0) {
            // calculate total value of the cart
            $totalValue = $order['total_price'];
            $totalWeightInGrams = $order['total_weight'];
            Log::debug('TotalWeight: '. $totalWeightInGrams);
            //if weight is more than 35kg, do not return
            if ($totalWeightInGrams > 35000){
                $json = json_encode(['rates' => $rates]);
                Log::debug($json);
                echo $json;
                return;
            }
            $service_id = request()->get('shipping_method');
            // search nearest pickup locations
            $pickupPoints = $pk_client->searchPickupPoints(
                request()->get('zip'),
                request()->get('address1'),
                request()->get('country'),
                $service_id,
                $shop->pickuppoints_count
            );

            if (empty($pickupPoints) && (request()->get('country') == 'LT' || request()->get('country') == 'AX' || request()->get('country') == 'FI')) {
                // search some pickup points if no pickup locations was found
                $pickupPoints = $pk_client->searchPickupPoints(
                    '00100',
                    null,
                    'FI',
                    $service_id,
                    $shop->pickuppoints_count
                );
            }
            // generate custom carrier service response
            try {
                foreach ($pickupPoints as $_pickupPoint) {
                    $_pickupPointName = ucwords(mb_strtolower($_pickupPoint->name));

                    $_pickupPoint->provider_service = 0;
                    if(isset($_pickupPoint->service->service_code) && $_pickupPoint->service->service_code)
                    {
                        $_pickupPoint->provider_service = $_pickupPoint->service->service_code;
                    }
                    else if(isset($_pickupPoint->service_code) && $_pickupPoint->service_code)
                    {
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
            }
        }
        return response()->json([
            'pickups' => $rates
        ]);
    }

    public function createCustomShipment()
    {
        $order_id = request()->get('order_id');
        if (!$order_id) {
            Log::debug('No id found');
            throw new NotFoundHttpException();
        }
        $shipping_lines = [];

        $shop = request()->get('shop');
        // api check
        $this->pk_client = $this->getPakketikauppaClient($shop);
        $result = $this->pk_client->listShippingMethods();
        //var_dump($result);
        if (!is_array($result)) {
            return response()->json([
                'message' => trans('app.messages.invalid_credentials'),
                'status' => 'error',
            ]);
        }

        $this->client = $this->getShopifyClient();
        try {
            $orders = $this->client->getOrders([$order_id]);
            if (!isset($orders['orders']['edges']) || !count($orders['orders']['edges'])){
                //throw new ShopifyApiException();
            }
            $order = $orders['orders']['edges'][0];
            $order = $order['node'];
            /*
            $order = $this->client->call(
                'GET',
                'admin',
                "/orders/{$order_id}.json",
            );
             * 
             */
        } catch (ShopifyApiException $sae) {
            Log::debug('Unauthorized thingie');
            return redirect()->route('install-link', request()->all());
        }

        if(request()->get('pickup'))
        {
            $pickup = json_decode(trim(request()->get('pickup'), '"'), true);
            $shipping_lines[] = [
                'code' => $pickup['service_code'],
                'price' => $pickup['total_price'] / 100.0,
                'discounted_price' => $pickup['total_price'] / 100.0,
                'title' => $pickup['service_name']
            ];
            $order['shipping_lines'] = $shipping_lines;
        }
        else
        {
            return response()->json([
                'message' => trans('app.custom_shipment.not_selected'),
                'status' => 'error',
            ]);
        }
        
        $shipment = [];
        $shipment['fulfillment_status'] = !empty($order['fulfillments']) ? $order['fulfillments'][0]['status'] : '';
        $shipment['line_items'] = [];
        foreach ($order['lineItems']['edges'] as $line_item){
            if ($line_item['node']['requiresShipping']) {
                $shipment['line_items'][] = $line_item['node'];
            }
        }

        $shipment['id'] = $order['legacyResourceId'];
        $shipment['admin_order_url'] = 'https://' . $shop->shop_origin . '/admin/orders/' . $order['legacyResourceId'];
        $url_params = [
            'shop' => $shop->shop_origin,
        ];
        $url_params['hmac'] = createShopifyHMAC($url_params);
        $shipment['hmac_print_url'] = http_build_query($url_params);

        $senderInfo = [
            'name' => $shop->business_name,
            'company' => '',
            'address' => $shop->address,
            'postcode' => $shop->postcode,
            'city' => $shop->city,
            'country' => $shop->country,
            'phone' => $shop->phone,
            'email' => $shop->email,
        ];

        $receiverPhone = request()->get('phone');

        if (empty($receiverPhone) and isset($order['billingAddress']['phone'])) {
            $receiverPhone = $order['billingAddress']['phone'];
        }

        if (empty($receiverPhone)) {
            $receiverPhone = $order['phone'];
        }

        if (empty($receiverPhone) and isset($order['customer']['phone'])) {
            $receiverPhone = $order['customer']['phone'];
        }

        $receiverName = request()->get('first_name') . " " . request()->get('last_name');
        $receiverCompany = request()->get('company');
        if (empty($receiverCompany)) {
            $receiverCompany = null;
        }
        $receiverAddress = request()->get('address1');
        $receiverAddress2 = empty(request()->get('address2')) ? null : request()->get('address2');

        $receiverZip = request()->get('zip');
        $receiverCity = request()->get('city');
        $receiverCountry = request()->get('country');

        $receiverInfo = [
            'name' => $receiverName,
            'company' => $receiverCompany,
            'address' => $receiverAddress,
            'address2' => $receiverAddress2,
            'postcode' => $receiverZip,
            'city' => $receiverCity,
            'country' => $receiverCountry,
            'phone' => $receiverPhone,
            'email' => request()->get('email') ?? $order['email'],
        ];

        $contents = $shipment['line_items'];

        $order['packets'] = request()->get('packets');
        $_shipment = $shop->sendShipment(
            $this->pk_client,
            $order,
            $senderInfo,
            $receiverInfo,
            $contents,
        );
        $shipment['status'] = $_shipment['status'];

        if (
            !empty($this->pk_client->getResponse()->{'response.trackingcode'}['labelcode']) and
            $shop->create_activation_code === true
        ) {
            try {
                if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                    sleep(2);
                }

                $this->client->call(
                    'PUT',
                    'admin',
                    '/orders/' . $order['id'] . '.json',
                    [
                        'order' => [
                            'id' => $order['id'],
                            'note' => sprintf('%s: %s', trans('app.settings.activation_code'), $this->pk_client->getResponse()->{'response.trackingcode'}['labelcode'])
                        ]
                    ]
                );
            } catch (\Exception $e) {
                Log::debug($e->getMessage());
                Log::debug($e->getTraceAsString());
            }
        }

        if (isset($_shipment['error_message'])) {
            $shipment['error_message'] = $_shipment['error_message'];
        }

        $page_title = 'print_label';
        $print_all_url_params = [
            'shop' => $shop->shop_origin,
        ];

        $print_all_url_params['hmac'] = createShopifyHMAC($print_all_url_params);
        $hmac_print_all_url = http_build_query($print_all_url_params);

        $tracking_codes = [];
        if(isset($_shipment['tracking_code']) && is_array($_shipment['tracking_code']))
        {
            $tracking_codes = [];
            foreach ($_shipment['tracking_code'] as $tracking_code)
            {
                $tracking_codes[] = $tracking_code;
            }
            $shipment['tracking_code'] = implode(', ', $tracking_codes);
        }
        else if (isset($_shipment['tracking_code'])) {
            $shipment['tracking_code'] = $_shipment['tracking_code'];
            $tracking_codes[] = $_shipment['tracking_code'];
        }

        return response()->json([
            'html' => view('app.custom-labels', [
                'shop' => $shop,
                'shipment' => $shipment,
                'tracking_codes' => $tracking_codes,
                'orders_url' => 'https://' . $shop->shop_origin . '/admin/orders',
                'print_all_params' => $hmac_print_all_url,
                'page_title' => $page_title,
                'is_return' => false,
                'tracking_url' => $this->tracking_url,
                'type' => $this->type
            ])->toHtml()
        ]);
    }


    public function latestNews() {
        $feed_dir = "pakettikauppa";
        if ($this->type == "posti" || $this->type == "itella") {
            $feed_dir = $this->type;
        }

        $rssFeed = simplexml_load_string(Storage::get(config('shopify.storage_path') . '/' . $feed_dir . '/feed.xml'));

        return view('app.latest-news', [
            'feed' => $rssFeed->channel,
            'type' => $this->type
        ]);
    }

    public function returnLabel(Request $request) {
        $this->is_return = true;
        return $this->printLabels($request);
    }

    public function printLabelsFulfill(Request $request) {
        $this->fullfill_order = true;
        return $this->printLabels($request);
    }

    public function getLabels(Request $request) {
        if (empty(request()->get('tracking_codes'))) {
            Log::debug('Tracking codes not found');
            throw new NotFoundHttpException();
        }

        $shop = request()->get('shop');
        $this->pk_client = $this->getPakketikauppaClient($shop);

        $tracking_codes = request()->get('tracking_codes');
        if(count($tracking_codes) == 1 && strpos($tracking_codes[0], ','))
        {
            $tracking_codes = explode(', ', $tracking_codes[0]);
        }
        $xml = $this->pk_client->fetchShippingLabels($tracking_codes);

        $pdf = base64_decode($xml->{'response.file'});

        return Response::make($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="multiple-shipping-labels.pdf"'
        ]);
    }

    public function getLabel(Request $request, $order_id, $tracking_code = null) {
        $shop = request()->get('shop');
        $this->pk_client = $this->getPakketikauppaClient($shop);
        $is_return = isset($request->is_return) ? $request->is_return : false;
        if($tracking_code)
        {
            $shipment = ShopifyShipment::where('shop_id', $shop->id)
                ->where('order_id', $order_id)
                ->where('tracking_code', $tracking_code)
                ->orWhere('tracking_code', 'like', "%{$tracking_code}%")
                ->first();
        }
        else
        {
            $shipment = ShopifyShipment::where('shop_id', $shop->id)
                ->where('order_id', $order_id)
                ->where('test_mode', $shop->test_mode)
                ->where('return', $is_return)
                ->first();
        }

        if (!isset($shipment)) {
            Log::debug("Could not find shipment");
            throw new NotFoundHttpException();
        }

        $pk_shipment = new Shipment();
        if($tracking_code)
            $pk_shipment->setTrackingCode($tracking_code);
        else
            $pk_shipment->setTrackingCode($shipment->tracking_code);
        $pk_shipment->setReference($shipment->reference);

        $this->pk_client->fetchShippingLabel($pk_shipment);

        $pdf_content = base64_decode($pk_shipment->getPdf());

        return Response::make($pdf_content, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $shipment->tracking_code . '.pdf"'
        ]);
    }

    public function trackShipment(Request $request) {
        $shop = request()->get('shop');
        $this->pk_client = $this->getPakketikauppaClient($shop);
        $is_return = isset($request->is_return) ? $request->is_return : false;

        $shipment = ShopifyShipment::where('shop_id', $shop->id)
                ->where('order_id', $request->id)
                ->where('return', $is_return)
                ->first();

        if (!isset($shipment)) {
            return view('app.alert', [
                'shop' => $shop,
                'type' => 'error',
                'title' => trans('app.messages.no_tracking_info'),
                'message' => '',
            ]);
        }

        $tracking_code = $shipment->test_mode ? 'JJFITESTLABEL100' : $shipment->tracking_code;

        $statuses = $this->pk_client->getShipmentStatus($tracking_code);

        if (!is_array($statuses) || count($statuses) == 0) {
            return view('app.alert', [
                'shop' => $shop,
                'type' => 'error',
                'title' => trans('app.messages.no_tracking_info'),
                'message' => '',
            ]);
        }

        $admin_order_url = 'https://' . $shop->shop_origin . '/admin/orders/' . $shipment->order_id;
        $admin_orders_url = 'https://' . $shop->shop_origin . '/admin/orders';

        return view('app.shipment-status', [
            'shop' => $shop,
            'statuses' => $statuses,
            'current_shipment' => $shipment,
            'order_url' => $admin_order_url,
            'orders_url' => $admin_orders_url,
        ]);
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
