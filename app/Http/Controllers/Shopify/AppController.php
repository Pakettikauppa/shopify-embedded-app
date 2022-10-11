<?php

namespace App\Http\Controllers\Shopify;

use App\Exceptions\ShopifyApiException;
use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
                'message_type' => 'error',
                'type' => $this->type,
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
            $params = request()->all();
            $params['shopify_redirect_url'] = $request->getRequestUri();
            return redirect()->route('install-link', $params);
        } catch (\Exception $sae) {
            Log::debug($sae->getMessage());
            $params = request()->all();
            $params['shopify_redirect_url'] = $request->getRequestUri();
            return redirect()->route('install-link', $params);
        }

        $shipments = [];

        foreach ($orders['orders']['edges'] as $orderNode) {
            $tracking_codes = [];
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

            $shipment = DB::transaction(function () use ($shop, $order, $is_return, $shipment){
                $done_shipment = ShopifyShipment::lockForUpdate()->where('shop_id', $shop->id)
                        ->where('order_id', $order['legacyResourceId'])
                        ->where('test_mode', $shop->test_mode)
                        ->where('return', $is_return)
                        ->first();

                if ($done_shipment) {
                    $shipment['status'] = 'sent';

                    if (strpos($done_shipment->tracking_code, ',')) {
                        $tracking_codes = explode(', ', $done_shipment->tracking_code);
                    } else {
                        $tracking_codes[] = $done_shipment->tracking_code;
                    }
                    $shipment['tracking_codes'] = $tracking_codes;
                    // $shipments[] = $shipment;
                    // continue;
                    return $shipment;
                }

                if (!isset($order['shippingAddress']) and!isset($order['billingAddress'])) {
                    $shipment['status'] = 'need_shipping_address';
                    // $shipments[] = $shipment;
                    // continue;
                    return $shipment;
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
                $shipment['tracking_codes'] = [];
                
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
                    $shipment['tracking_code'] = end($shipment['tracking_codes']);
                }
                
                if (
                        !empty($this->pk_client->getResponse()->{'response.trackingcode'}['labelcode']) && $shop->create_activation_code === true
                ) {
                    try {
                        $query_params = $this->client->buildGraphQLInput(['id' => $order['gid'], 'note' => sprintf('%s: %s', trans('app.settings.activation_code'), $this->pk_client->getResponse()->{'response.trackingcode'}['labelcode'])]);
                        $query = <<<GQL
                                mutation UpdateOrder {
                                    orderUpdate(input: $query_params){
                                        userErrors {
                                        field
                                        message
                                        }
                                        order {
                                        id
                                        }
                                    }
                                }        
                                GQL;
                        $this->client->call($query);
                    } catch (\Exception $e) {
                        Log::debug($e->getMessage());
                        Log::debug($e->getTraceAsString());
                    }
                }

                if (isset($_shipment['error_message'])) {
                    $shipment['error_message'] = $_shipment['error_message'];
                }

                return $shipment;

            });

            $shipments[] = $shipment;

            Log::debug("Processed order: " . implode(', ', $shipment['tracking_codes']) . " - {$order['id']}");
        }

        if ($fulfill_order) {
            foreach ($shipments as $orderKey => $order) {
                if (empty($order['tracking_codes'])) {
                    continue;
                }

                Log::debug("Fullfilling order: " . implode(', ', $order['tracking_codes']) . " - {$order['id']}");

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
                $filtered_services = [];
                $has_missing_products = false;
                
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
                        //producte deleted and variant null, skip
                        if ($item['variant'] === null){
                            $has_missing_products = true;
                            continue;
                        } 
                        $inventoryLevels = $item['variant']['inventoryItem']['inventoryLevels']['edges'];
                        foreach ($inventoryLevels as $_inventory) {
                            //do not look at inventory quantity
                            $service = $item['variant']['fulfillmentService']['type'];
                            if (!isset($services[$service][$_inventory['node']['location']['id']] )){
                                $services[$service][$_inventory['node']['location']['id']] = [];
                            }
                            $services[$service][$_inventory['node']['location']['id']][] = ['id' => $item['id'], 'quantity' => (int)$item['quantity']];
                            $makeNull = false;
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
                        Log::debug('Fullfillment Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
                    }
                }
                //filter services to check if found all available quantities in one warehouse
                foreach ($services as $fullfilment => $line_items) {
                    foreach ($line_items as $locationId => $items) {
                        if (count($items) == count($order['line_items'])){
                            $filtered_services[$fullfilment][$locationId] = $items;
                            break;
                        }
                    }
                }
                
                if (!empty($filtered_services)){
                    foreach ($filtered_services as $line_items) {
                        foreach ($line_items as $locationId => $items) {
                            $fulfillment = [
                                'orderId' => $order['gid'],
                                'trackingNumbers' => implode(', ', $order['tracking_codes']),
                                'locationId' => $locationId,
                                'notifyCustomer' => true,
                                'trackingCompany' => trans('app.settings.company_name_' . $this->type),
                                'trackingUrls' => $this->tracking_url . end($order['tracking_codes']),
                                'lineItems' => $items,
                            ];

                            try {
                                /*
                                  if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                                  sleep(2);
                                  }
                                 * 
                                 */
                                $query_params = $this->buildGraphQLInput($fulfillment);
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
                                Log::debug('Fullfillment Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
                            }
                        }
                    }
                } else if ($has_missing_products){
                    $shipments[$orderKey]['status'] = 'product_deleted';
                } else {
                    $shipments[$orderKey]['status'] = 'not_in_inventory';
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

    public function customShipment(Request $request) {
        $shop = request()->get('shop');

        // Create client config and refresh token if necessary.
        $this->pk_client = $this->getPakketikauppaClient($shop);

        // Something went horribly wrong.
        if (!$this->pk_client)
        {
            Log::debug("Custom shipment: client initialization error.");
            throw new FatalErrorException();
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

        // Get unfulfiled items.
        $unfulfiled_items = [];
        foreach($order['line_items'] as $item)
        {
            if(!$item['fulfillment_status'] || $item['fulfillable_quantity'] > 0)
            {
                $unfulfiled_items[] = $item;       
            }
        }
        $shipping_methods = $this->pk_client->listShippingMethods();
        //in case no settings, check
        $shop_settings = json_decode($shop->settings, true);
        if (is_array($shop_settings)) {
            $services = array_keys($shop_settings);
        } else {
            $services = array();
        }
        $services[] = $shop->default_service_code;
        foreach (unserialize($shop->shipping_settings) as $setting) {
            if ($setting['product_code'])
                $services[] = $setting['product_code'];
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

        if (!empty($order['shipping_lines'])) {
            $shipping_line = $order['shipping_lines'][0];
            $method_codes = explode(':', $shipping_line['code']);
            $selected_method = $method_codes[0] ?? null;
        } else {
            $selected_method = null;
        }

        return view('app.custom-shipment', [
            'selected_method' => $selected_method,
            'hmac' => $hmac,
            'shop' => $shop,
            'shipping_methods' => $shipping_methods,
            'order_id' => $order_id,
            'type' => $this->type,
            'shipping_address' => $shipping_address,
            'email' => $order['email'],
            'unfulfiled_items' => $unfulfiled_items
        ]);
    }

    public function listShipments(Request $request)
    {
        $shop = request()->get('shop');

        // Create client config and refresh token if necessary.
        $this->pk_client = $this->getPakketikauppaClient($shop);

        // Something went horribly wrong.
        if (!$this->pk_client)
        {
            Log::debug("Custom shipment: client initialization error.");
            throw new FatalErrorException();
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

        $fulfillments = [];
        foreach($order['fulfillments'] as $fulfillment)
        {
            if(isset($fulfillment['tracking_number']))
            {
                $fulfillments[$fulfillment['tracking_number']] = $fulfillment;
            }
        }

        $shipments = ShopifyShipment::where('shop_id', $shop->id)
            ->where('order_id', $request->id)
            ->orderBy('created_at', 'desc')
            ->get();
        $hmac = $request->get('hmac');
        $url_params = [
            'shop' => $shop->shop_origin,
        ];
        $url_params['hmac'] = createShopifyHMAC($url_params);

        $hmac_print_url = http_build_query($url_params);
        $shipping_methods = $this->pk_client->listShippingMethods();
        $shipment_methods_names = [];
        foreach($shipping_methods as $shipment_method)
        {
            $shipment_methods_names[$shipment_method->shipping_method_code] = $shipment_method->name;   
        }

        return view('app.list-shipments', [
            'hmac' => $hmac,
            'shop' => $shop,
            'type' => $this->type,
            'shipments' => $shipments,
            'tracking_url' => $this->tracking_url,
            'fulfillments' => $fulfillments,
            'hmac_print_url' => $hmac_print_url,
            'shipment_methods' => $shipment_methods_names
        ]);
    }
    
    private function prepareCustomShipmentProducts($products, $order_id, $ship_products = false){
        $prepared = [];
        $shipment_products = [];
        if (empty($products) || empty($order_id)){
            return $prepared;
        }
        $shop = request()->get('shop');
        $shipped = [];
        $_products = ShopifyShipment::where('shop_id', $shop->id)->where('order_id', $order_id)->whereNotNull('products')->get('products');
        foreach ($_products as $_product){
            $shipped = array_merge($shipped, $_product->products);
        }
        
        if (isset($products['edges'])){
            $products = $products['edges'];
        }
        foreach ($products as $product){
            //if got graphql object
            if( isset($product['node'])){
                if ($product['node']['requiresShipping'] !== true){
                    continue;
                }
                $product_id = $product['node']['product']['legacyResourceId'];
                $item = [
                    'name' => $product['node']['name'],
                    'total' => $product['node']['quantity'],
                    'shipped' => $this->countShippedProducts($product_id, $shipped)
                ];
                $item['remains'] = $item['total'] - $item['shipped'];
                $prepared[$product_id] = $item;
                if ($ship_products !== false){
                    if (isset($ship_products[$product_id])){
                        if ((int)$ship_products[$product_id] > $item['remains']){
                            $product['node']['quantity'] = $item['remains'];
                        } else {
                            $product['node']['quantity'] = (int)$ship_products[$product_id];
                        }
                        if ($product['node']['quantity'] > 0){
                            $shipment_products[] = $product['node'];
                        }
                    }
                }
            } else {
                if ($product['requires_shipping'] !== true){
                    continue;
                }
                $item = [
                    'name' => $product['name'],
                    'total' => $product['quantity'],
                    'shipped' => $this->countShippedProducts($product['product_id'], $shipped)
                ];
                $item['remains'] = $item['total'] - $item['shipped'];
                $prepared[$product['product_id']] = $item;
            }
        }
        if ($ship_products !== false){
            return $shipment_products;
        }
        return $prepared;
    }
    
    private function countShippedProducts($id, $data){
        $shipped = 0;
        if (empty($data)){
            return $shipped;
        }
        foreach ($data as $item){
            if ($item['id'] == $id){
                $shipped += $item['shipped'];
            }
        }
        return $shipped;
    }

    public function getShippingAddressFromOrder($order) {
        return [
            'first_name' => $order['shipping_address']['first_name'] ?? '',
            'last_name' => $order['shipping_address']['last_name'] ?? '',
            'company' => $order['shipping_address']['company'] ?? '',
            'address1' => $order['shipping_address']['address1'] ?? '',
            'address2' => $order['shipping_address']['address2'] ?? '',
            'zip' => $order['shipping_address']['zip'] ?? '',
            'city' => $order['shipping_address']['city'] ?? '',
            'country_code' => $order['shipping_address']['country_code'] ?? '',
            'phone' => $order['shipping_address']['phone'] ?? '',
        ];
    }

    public function ajaxLoadPickups() {
        $shop = Shop::where('shop_origin', request()->get('shop')->shop_origin)->first();
        if ($shop == null) {
            return response()->json([
                        'message' => 'Could not get shop object.',
                        'status' => 'error',
            ]);
        }

        $pk_client = $this->getPakketikauppaClient($shop);

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
            Log::debug('TotalWeight: ' . $totalWeightInGrams);
            //if weight is more than 35kg, do not return
            if ($totalWeightInGrams > 35000) {
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
                    //get price to var, to check if it is not false in case settings not found
                    $total_rate_price = $this->priceForPickupPoint($_pickupPoint->provider_service, $totalValue);
                    //if price not found - skip
                    if ($total_rate_price === false) {
                        continue;
                    }
                    $rates[] = array(
                        'service_name' => "{$_pickupPointName}, " . "{$_pickupPoint->street_address}, {$_pickupPoint->postcode}, {$_pickupPoint->city}",
                        'description' => $_pickupPoint->provider . ' (' . ((is_object($_pickupPoint->service) && isset($_pickupPoint->service->name) && $_pickupPoint->service->name != null) ? $_pickupPoint->service->name : '') . ') ' . ($_pickupPoint->description == null ? '' : " ({$_pickupPoint->description})"),
                        'service_code' => "{$_pickupPoint->provider_service}:{$_pickupPoint->pickup_point_id}",
                        'currency' => 'EUR',
                        'total_price' => $total_rate_price
                    );
                }
            } catch (\Exception $e) {
                Log::debug($e->getMessage());
                Log::debug($e->getTraceAsString());
            }
        }
        if (!empty($order['shipping_lines'])) {
            $shipping_line = $order['shipping_lines'][0];
            $method_codes = explode(':', $shipping_line['code']);
            $selected_pickup = $method_codes[1] ?? null;
        } else {
            $selected_pickup = null;
        }
        return response()->json([
                    'pickups' => $rates,
                    'selected_pickup' => $selected_pickup
        ]);
    }

    public function createCustomShipment() {
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
        if (!is_array($result)) {
            return response()->json([
                        'message' => trans('app.messages.invalid_credentials'),
                        'status' => 'error',
            ]);
        }

        $this->client = $this->getShopifyClient();
        try {
            $orders = $this->client->getOrders([$order_id]);
            if (!isset($orders['orders']['edges']) || !count($orders['orders']['edges'])) {
                //throw new ShopifyApiException();
            }
            $order = $orders['orders']['edges'][0];
            $order = $order['node'];
        } catch (ShopifyApiException $sae) {
            Log::debug('Unauthorized thingie');
            return redirect()->route('install-link', request()->all());
        }

        $service_name = 'unnamed service';
        if (request()->get('pickup')) {
            $pickup = json_decode(trim(request()->get('pickup'), '"'), true);
            $code = $pickup['service_code'];
            if(isset($pickup['service_name']))
                $service_name = $pickup['service_name'];
        }
        elseif (request()->get('shipping_method'))
        {
            $code = request()->get('shipping_method');
            if(request()->get('service_name'))
                $service_name = request()->get('service_name');
        }
        else
        {
            return response()->json([
                'message' => trans('app.custom_shipment.not_selected'),
                'status' => 'error',
            ]);
        }

        // Does not really matter, besides code, which API will use. Shipping_lines is immutable in Shopify.
        $shipping_lines = [
            'code' => $code,
            'price' => 0,
            'discounted_price' => 0,
            'title' => $service_name,
        ];
        $order['shippingLine'] = $shipping_lines;

        $additional_services = request()->get('additional_services');
        if (is_array($additional_services) && count($additional_services)){
            $order['additional_services'] = $additional_services;
        }
        
        

        $order['gid'] = $order['id'];
        $order['id'] = $order['legacyResourceId'];
        $shipment = [];
        $shipment['fulfillment_status'] = !empty($order['fulfillments']) ? $order['fulfillments'][0]['status'] : '';
        $shipment['line_items'] = [];
        
        $fulfil = (bool) request()->get('fulfil');

        if($fulfil)
        {
            // Get unfulfiled items.
            $unfulfiled_items = [];
            $quantities_unfulfiled = request()->get('quantity');
            foreach ($order['lineItems']['edges'] as $line_item) {
                $node = $line_item['node'];
                $item = explode("/", $node['id']);
                $itemID = end($item);
                if ($node['requiresShipping'] && isset($quantities_unfulfiled[$itemID])) {

                    $qty_to_fulfil = $quantities_unfulfiled[$itemID];
                    // Set the unfulfiled quantity selected. Check if it is not zero or exceeds maximum, in case client decides to play around..
                    $order_quantity = $node['quantity'];
                    if($qty_to_fulfil > $order_quantity)
                        $qty_to_fulfil = $order_quantity;
                    if($qty_to_fulfil < 1)
                        continue;

                    $node['quantity'] = $qty_to_fulfil; 
                    $shipment['line_items'][] = $node;
                }
            }
        }
        else
        {
            foreach ($order['lineItems']['edges'] as $line_item) {
                if ($line_item['node']['requiresShipping']) {
                    $shipment['line_items'][] = $line_item['node'];
                }
            }    
        }

        $shipment['id'] = $order['legacyResourceId'];
        $shipment['gid'] = $order['gid'];
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
                false,
                true
        );
        $shipment['status'] = $_shipment['status'];

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
        if (isset($_shipment['tracking_code']) && is_array($_shipment['tracking_code'])) {
            $tracking_codes = [];
            foreach ($_shipment['tracking_code'] as $tracking_code) {
                $tracking_codes[] = $tracking_code;
            }
            $shipment['tracking_code'] = implode(', ', $tracking_codes);
        } else if (isset($_shipment['tracking_code'])) {
            $shipment['tracking_code'] = $_shipment['tracking_code'];
            $tracking_codes[] = $_shipment['tracking_code'];
        }

        if ($fulfil) {
            Log::debug("Fullfilling order: " . implode(', ', $tracking_codes) . " - {$order['id']}");

            $services = [];
            $filtered_services = [];
            $has_missing_products = false;
            
            foreach ($shipment['line_items'] as $item) {
                try {
                    $makeNull = true;
                    if ($item['variant'] === null){
                        $has_missing_products = true;
                        continue;
                    } 
                    $inventoryLevels = $item['variant']['inventoryItem']['inventoryLevels']['edges'];
                    foreach ($inventoryLevels as $_inventory) {
                        //do not look at inventory quantity
                        $service = $item['variant']['fulfillmentService']['type'];
                        if (!isset($services[$service][$_inventory['node']['location']['id']] )){
                            $services[$service][$_inventory['node']['location']['id']] = [];
                        }
                        $services[$service][$_inventory['node']['location']['id']][] = ['id' => $item['id'], 'quantity' => (int)$item['quantity']];
                        $makeNull = false;
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
                    Log::debug('Fullfillment Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
                }
            }


            //filter services to check if found all available quantities in one warehouse
            foreach ($services as $fullfilment => $line_items) {
                foreach ($line_items as $locationId => $items) {
                    if (count($items) == count($shipment['line_items'])){
                        $filtered_services[$fullfilment][$locationId] = $items;
                        break;
                    }
                }
            }
            
            if (!empty($filtered_services)){
                foreach ($filtered_services as $line_items) {
                    foreach ($line_items as $locationId => $items) {
                        $fulfillment = [
                            'orderId' => $order['gid'],
                            'trackingNumbers' => implode(', ', $tracking_codes),
                            'locationId' => $locationId,
                            'notifyCustomer' => true,
                            'trackingCompany' => trans('app.settings.company_name_' . $this->type),
                            'trackingUrls' => $this->tracking_url . end($tracking_codes),
                            'lineItems' => $items,
                        ];

                        try {
                            $query_params = $this->buildGraphQLInput($fulfillment);
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
                            Log::debug('Fullfillment Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
                        }
                    }
                }
            } else if ($has_missing_products){
                $shipments[$orderKey]['status'] = 'product_deleted';
            } else {
                $shipments[$orderKey]['status'] = 'not_in_inventory';
            }
            Log::debug("Fullfilled order: {$order['id']}");
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
        try {
            $shop = request()->get('shop');
            $this->pk_client = $this->getPakketikauppaClient($shop);

            $tracking_codes = request()->get('tracking_codes');
            if(count($tracking_codes) == 1 && strpos($tracking_codes[0], ','))
            {
                $tracking_codes = explode(', ', $tracking_codes[0]);
            }
            Log::debug('Fetching labels for ' . json_encode($tracking_codes));
            $xml = $this->pk_client->fetchShippingLabels($tracking_codes);

            $pdf = base64_decode($xml->{'response.file'});

            return Response::make($pdf, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="multiple-shipping-labels.pdf"'
            ]);
        } catch (\Exception $e){
            Log::debug('Failed to get labels: ' . json_encode(
                [
                    'http_request' => $this->pk_client->http_request,
                    'http_response_code' => $this->pk_client->http_response_code,
                    'http_error' => $this->pk_client->http_error,
                    'http_response' => $this->pk_client->http_response
                ]
            ));
            Log::debug($e->getMessage());
            throw new NotFoundHttpException();
        }
    }

    public function getLabel(Request $request, $order_id, $tracking_code = null) {
        $shop = request()->get('shop');
        $this->pk_client = $this->getPakketikauppaClient($shop);
        $is_return = isset($request->is_return) ? $request->is_return : false;
        if ($tracking_code) {
            $shipment = ShopifyShipment::where('shop_id', $shop->id)
                    ->where('order_id', $order_id)
                    ->where('tracking_code', $tracking_code)
                    ->orWhere('tracking_code', 'like', "%{$tracking_code}%")
                    ->first();
        } else {
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
        try {
            $pk_shipment = new Shipment();
            if($tracking_code){
                $pk_shipment->setTrackingCode($tracking_code);
            } else {
                $pk_shipment->setTrackingCode($shipment->tracking_code);
            }
            Log::debug('Fetching label for ' . $pk_shipment->getTrackingCode());
            $pk_shipment->setReference($shipment->reference);

            $this->pk_client->fetchShippingLabel($pk_shipment);

            $pdf_content = base64_decode($pk_shipment->getPdf());

            return Response::make($pdf_content, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="' . $shipment->tracking_code . '.pdf"'
            ]);
        } catch (\Exception $e){
            Log::debug('Failed to get label: ' . json_encode(
                [
                    'http_request' => $this->pk_client->http_request,
                    'http_response_code' => $this->pk_client->http_response_code,
                    'http_error' => $this->pk_client->http_error,
                    'http_response' => $this->pk_client->http_response
                ]
            ));
            Log::debug($e->getMessage());
            throw new NotFoundHttpException();
        }
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
                'message_type' => 'error',
                'type' => $this->type,
                'title' => trans('app.messages.no_tracking_info'),
                'message' => '',
            ]);
        }

        $tracking_code = $shipment->test_mode ? 'JJFITESTLABEL100' : $shipment->tracking_code;

        $statuses = $this->pk_client->getShipmentStatus($tracking_code);

        if (!is_array($statuses) || count($statuses) == 0) {
            return view('app.alert', [
                'shop' => $shop,
                'message_type' => 'error',
                'type' => $this->type,
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
            'type' => $this->type,
        ]);
    }

    private function priceForPickupPoint($provider, $totalValue) {
        if (!is_array($this->pickupPointSettings) || !isset($this->pickupPointSettings[$provider])) {
            return false;
        }

        $pickupPointSettings = $this->pickupPointSettings[$provider];

        if ($pickupPointSettings['trigger_price'] > 0 and $pickupPointSettings['trigger_price'] * 100 <= $totalValue) {
            return (int) round($pickupPointSettings['triggered_price'] * 100.0);
        }

        return (int) round($pickupPointSettings['base_price'] * 100.0);
    }
    
    private function buildGraphQLInput($array) {
        $output_as_array = false;
        $output = '';
        $total = count($array);
        $counter = 0;
        foreach ($array as $key => $value) {
            $counter++;
            if (is_array($value)) {
                if (is_int($key) ){
                    $output_as_array = true;
                    $output .= $this->buildGraphQLInput($value);
                } else {
                    $output .= $key . ': ' . $this->buildGraphQLInput($value);
                }
            } else {
                if (gettype($value) == "integer"){
                    $output .= $key . ': ' . $value . '';
                } else if (gettype($value) == "boolean"){
                    $output .= $key . ': ' . ($value?'true':'false') . '';
                } else {
                    $output .= $key . ': "' . $value . '"';
                }
            }
            if ($counter != $total) {
                $output .= ', ';
            }
        }
        if ($output_as_array){
            return '[' . $output . ']';
        }
        return '{' . $output . '}';
    }

    private function getGraphId($gid) {
        $data = explode('/', $gid);
        return end($data);
    }                        
                            
    public function ajaxLoadAdditionalServices(Request $request) {
        $shop = Shop::where('shop_origin', request()->get('shop')->shop_origin)->first();
        if ($shop == null) {
            return response()->json([
                        'message' => 'Could not get shop object.',
                        'status' => 'error',
            ]);
        }
        $service = $request->input('shipping_method');
        if (!$service) {
            return response()->json([
                        'message' => 'Shipping service not received.',
                        'status' => 'error',
            ]);
        }
        $client = $this->getPakketikauppaClient($shop);
        $methods = $client->listShippingMethods();
        foreach ($methods as $method) {
            if ($method->shipping_method_code == $service) {
                return response()->json([
                            'data' => $method->additional_services,
                            'status' => 'ok',
                ]);
            }
        }
        return response()->json([
                    'data' => [],
                    'status' => 'ok',
        ]);
    }

}
