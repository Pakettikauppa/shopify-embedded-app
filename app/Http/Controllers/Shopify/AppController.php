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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
//use Log;
use Storage;

/**
 * @property \App\Models\Shopify\Shop $shop
 */
class AppController extends Controller
{
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

    public function __construct(Request $request)
    {
        //
    }

    /**
     * Shopify has a bug and this function is used to handle that
     *
     * @param array $arr
     * @return array
     */
    private function flattenArray($arr)
    {
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
    public function getPakketikauppaClient($shop)
    {
        $config = [
            'test_mode' => true
        ];

        if (!$shop->test_mode) {
            $config = [
                'api_key' => $shop->api_key,
                'secret' => $shop->api_secret,
            ];
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
    public function getShopifyClient($getNew = false)
    {
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

    public function printLabels(Request $request)
    {
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
            $orders = $this->client->call(
                'GET',
                'admin',
                '/orders.json',
                ['ids' => implode(',', $order_ids), 'status' => 'any']
            );
        } catch (ShopifyApiException $sae) {
            Log::debug('Unauthorized thingie');

            return redirect()->route('install-link', request()->all());
        }

        $shipments = [];

        foreach ($orders as $order) {
            $shipment = [];
            $shipment['fulfillment_status'] = $order['fulfillment_status'];
            $shipment['line_items'] = [];
            foreach ($order['line_items'] as $line_item) {
                if ($line_item['requires_shipping']) {
                    $shipment['line_items'][] = $line_item;
                }
            }
            $shipment['id'] = $order['id'];
            $shipment['admin_order_url'] = 'https://' . $shop->shop_origin . '/admin/orders/' . $order['id'];
            $url_params = [
                'shop' => $shop->shop_origin,
                'is_return' => $is_return?'1':'0',
            ];

            $url_params['hmac'] = createShopifyHMAC($url_params);
            $shipment['hmac_print_url'] = http_build_query($url_params);

            if (empty($shipment['line_items'])) {
                $shipment['status'] = 'nothing_to_ship';
                $shipments[] = $shipment;
                continue;
            }

            $done_shipment = ShopifyShipment::where('shop_id', $shop->id)
                ->where('order_id', $order['id'])
                ->where('test_mode', $shop->test_mode)
                ->where('return', $is_return)
                ->first();

            if ($done_shipment) {
                $shipment['status'] = 'sent';
                $shipment['tracking_code'] = $done_shipment->tracking_code;
                $shipments[] = $shipment;
                continue;
            }
            if (!isset($order['shipping_address']) and !isset($order['billing_address'])) {
                $shipment['status'] = 'need_shipping_address';
                $shipments[] = $shipment;
                continue;
            }

            if ($order['gateway'] == 'Cash on Delivery (COD)') {
            }

            if (isset($order['shipping_address'])) {
                $shipping_address = $order['shipping_address'];
            } else {
                $shipping_address = $order['billing_address'];
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

            if (empty($receiverPhone) and isset($order['billing_address']['phone'])) {
                $receiverPhone = $order['billing_address']['phone'];
            }

            if (empty($receiverPhone)) {
                $receiverPhone = $order['phone'];
            }

            if (empty($receiverPhone) and isset($order['customer']['phone'])) {
                $receiverPhone = $order['customer']['phone'];
            }

            $receiverName = $shipping_address['first_name'] . " " . $shipping_address['last_name'];
            $receiverCompany = $shipping_address['company'];
            if (empty($receiverCompany)) {
                $receiverCompany = null;
            }
            $receiverAddress = $shipping_address['address1'];
            $receiverAddress2 = empty($shipping_address['address2']) ? null : $shipping_address['address2'];

            $receiverZip = $shipping_address['zip'];
            $receiverCity = $shipping_address['city'];
            $receiverCountry = $shipping_address['country_code'];

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
                $shipment['tracking_code'] = $_shipment['tracking_code'];
            }

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

            $shipments[] = $shipment;

            Log::debug("Processed order: {$shipment['tracking_code']} - {$order['id']}");
        }

        if ($fulfill_order) {
            foreach ($shipments as $orderKey => $order) {
                if (empty($order['tracking_code'])) {
                    continue;
                }

                Log::debug("Fullfilling order: {$order['tracking_code']} - {$order['id']}");

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
                    $variantId = $item['variant_id'];

                    try {
                        if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                            sleep(2);
                        }

                        $variants = $this->client->call('GET', 'admin', '/variants/' . $variantId . '.json');

                        $inventoryId = $variants['inventory_item_id'];

                        if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                            sleep(2);
                        }
                        // TODO: not the most efficient way to do this
                        $inventoryLevels = $this->client->call(
                            'GET',
                            'admin',
                            '/inventory_levels.json',
                            [
                                'inventory_item_ids' => $inventoryId
                            ]
                        );

                        $makeNull = true;

                        foreach ($inventoryLevels as $_inventory) {
                            if ($_inventory['available'] > 0 || $_inventory['available'] == null) {
                                $service = $item['fulfillment_service'];
                                $services[$service][$_inventory['location_id']][] = ['id' => $item['id']];
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
                            'tracking_number' => $order['tracking_code'],
                            'location_id' => $locationId,
                            'tracking_company' => trans('app.settings.company_name'),
                            'tracking_url' => 'https://www.pakettikauppa.fi/seuranta/?' . $order['tracking_code'],
                            'line_items' => $items,
                        ];

                        try {
                            if ($this->client->callsLeft() > 0 and $this->client->callLimit() == $this->client->callsLeft()) {
                                sleep(2);
                            }

                            $result = $this->client->call(
                                'POST',
                                'admin',
                                '/orders/' . $order['id'] . '/fulfillments.json',
                                [
                                    'fulfillment' => $fulfillment
                                ]
                            );
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
            'is_return' => $is_return?'1':'0',
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
        ]);
    }

    public function latestNews()
    {

        $rssFeed = simplexml_load_string(Storage::get(config('shopify.storage_path') . '/feed.xml'));

        return view('app.latest-news', [
            'feed' => $rssFeed->channel
        ]);
    }

    public function returnLabel(Request $request)
    {
        $this->is_return = true;
        return $this->printLabels($request);
    }

    public function printLabelsFulfill(Request $request)
    {
        $this->fullfill_order = true;
        return $this->printLabels($request);
    }

    public function getLabels(Request $request)
    {
        if (empty(request()->get('tracking_codes'))) {
            throw new NotFoundHttpException();
        }

        $shop = request()->get('shop');
        $this->pk_client = $this->getPakketikauppaClient($shop);

        $xml = $this->pk_client->fetchShippingLabels(request()->get('tracking_codes'));

        $pdf = base64_decode($xml->{'response.file'});

        return Response::make($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="multiple-shipping-labels.pdf"'
        ]);
    }

    public function getLabel(Request $request, $order_id)
    {
        $shop = request()->get('shop');
        $this->pk_client = $this->getPakketikauppaClient($shop);
        $is_return = isset($request->is_return) ? $request->is_return : false;

        $shipment = ShopifyShipment::where('shop_id', $shop->id)
            ->where('order_id', $order_id)
            ->where('test_mode', $shop->test_mode)
            ->where('return', $is_return)
            ->first();

        if (!isset($shipment)) {
            throw new NotFoundHttpException();
        }

        $pk_shipment = new Shipment();
        $pk_shipment->setTrackingCode($shipment->tracking_code);
        $pk_shipment->setReference($shipment->reference);

        $this->pk_client->fetchShippingLabel($pk_shipment);

        $pdf_content = base64_decode($pk_shipment->getPdf());

        return Response::make($pdf_content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $shipment->tracking_code . '.pdf"'
        ]);
    }

    public function trackShipment(Request $request)
    {
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
}
