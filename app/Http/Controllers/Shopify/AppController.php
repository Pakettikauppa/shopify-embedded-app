<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyApiException;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Shopify\Shop;
use App\Models\Shopify\Shipment as ShopifyShipment;
use Pakettikauppa\Client;
use Pakettikauppa\Shipment;
use Psy\Exception\FatalErrorException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Log;

/**
 * @property \App\Models\Shopify\Shop $shop
 */
class AppController extends Controller
{
    /**
     * @var ShopifyClient
     */
    private $client;
    private $shop;
    /**
     * @var Client
     */
    private $pk_client;

    public function __construct(Request $request)
    {
        $this->middleware(function ($request, $next) {

            if(!session()->has('shop')){
                session()->put('init_request', $request->fullUrl());

                $params = $request->all();
                $params['_pk_s']=1;

                return redirect()->route('shopify.auth.index', $params);
            }

            $shop_origin = session()->get('shop');
            $shop = Shop::where('shop_origin', $shop_origin)->first();

            if(empty($shop)){
                session()->put('init_request', $request->fullUrl());
                return redirect()->route('shopify.auth.index', request()->all());
            }

            $this->shop = $shop;
            if ($shop->settings == null) {
                $shop->settings = '{}';
            }
            $this->pickupPointSettings = json_decode($shop->settings, true);

            $this->client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

            // set pk_client
            if($this->shop->test_mode){
                $pk_client_params = [
                    'test_mode' => true,
                ];
            }else{
                if(isset($this->shop->api_key) && isset($this->shop->api_secret)){
                    $pk_client_params = [
                        'api_key' => $this->shop->api_key,
                        'secret' => $this->shop->api_secret,
                    ];
                }
            }

            if(is_array($pk_client_params)){
                $this->pk_client = new Client($pk_client_params);
            }

            $this->pk_client->setComment('Created from Shopify App');

            \App::setLocale($this->shop->locale);

            return $next($request);
        });
    }

    /**
     * Shopify has a bug and this function is used to handle that
     *
     * @param array $arr
     * @return array
     */
    private function flattenArray($arr) {
        $values=[];
        foreach($arr as $item) {
            if(is_array($item)) {
                $values = array_merge($values, flattenArray($item));
            } else {
                $values[] = $item;
            }
        }
        return $values;
    }

    public function printLabels(Request $request)
    {
        if(!isset($request->ids) && !isset($request->id)){
            Log::debug('No id found');
            throw new NotFoundHttpException();
        }
        $is_return = isset($request->is_return) ? $request->is_return : false;
        $fulfill_order = isset($request->fulfill_order) ? $request->fulfill_order : false;

        // api check
        $result = $this->pk_client->listShippingMethods();
        Log::debug("ListShippingMethods Result:". $result);
        $result = json_decode($result);
        if(!is_array($result)){
            Log::debug("List Shipping Methods error!");

            return view('app.alert', [
                'type' => 'error',
                'title' => trans('app.messages.invalid_credentials'),
                'message' => trans('app.messages.no_api_set_error', ['settings_url' => route('shopify.settings')]),
            ]);
        }

        if(isset($request->ids)){
            $order_ids = $request->ids;
        }else{
            $order_ids = [$request->id];
        }

        $orders = $this->client->call('GET', '/admin/orders.json', ['ids' => implode(',', $order_ids), 'status' => 'any']);

        $shipments = [];

        foreach($orders as $order){
            $shipment = [];
            $shipment['fulfillment_status'] = $order['fulfillment_status'];
            $shipment['line_items'] = $order['line_items'];
            $shipment['id'] = $order['id'];
            $shipment['admin_order_url'] = 'https://' . $this->shop->shop_origin . '/admin/orders/' . $order['id'];

            $done_shipment = ShopifyShipment::where('shop_id', $this->shop->id)
                ->where('order_id', $order['id'])
                ->where('test_mode', $this->shop->test_mode)
                ->where('return', $is_return)
                ->first();

            if($done_shipment){
                $shipment['status'] = 'sent';
                $shipment['tracking_code'] = $done_shipment->tracking_code;
                $shipments[] = $shipment;
                continue;
            }
            if(!isset($order['shipping_address'])){
                $shipment['status'] = 'need_shipping_address';
                $shipments[] = $shipment;
                continue;
            }

            if($order['gateway'] == 'Cash on Delivery (COD)') {

            }

            $shipping_address = $order['shipping_address'];

            $senderInfo = [
                'name' => $this->shop->business_name,
                'company' => '',
                'address' => $this->shop->address,
                'postcode' => $this->shop->postcode,
                'city' => $this->shop->city,
                'country' => $this->shop->country,
                'phone' => $this->shop->phone,
                'email' => $this->shop->email,
            ];

            $receiverPhone = $shipping_address['phone'];

            if (empty ($receiverPhone) and isset($order['billing_address']['phone'])) {
                $receiverPhone = $order['billing_address']['phone'];
            }

            if (empty ($receiverPhone) ) {
                $receiverPhone = $order['phone'];
            }

            if (empty ($receiverPhone) and isset($order['customer']['phone'])) {
                $receiverPhone = $order['customer']['phone'];
            }

            $receiverInfo = [
                'name' => $shipping_address['first_name'] . " ".$shipping_address['last_name'],
                'company' => ($shipping_address['company']==null?'':$shipping_address['company']),
                'address' => $shipping_address['address1'],
                'postcode' => $shipping_address['zip'],
                'city' => $shipping_address['city'],
                'country' => $shipping_address['country_code'],
                'phone' => $receiverPhone,
                'email' => $order['email'],
            ];

            if($is_return){
                $tmp = $receiverInfo;
                $receiverInfo = $senderInfo;
                $senderInfo = $tmp;
            }

            $contents = $shipment['line_items'];

            $_shipment = $this->shop->sendShipment($this->pk_client, $order, $senderInfo, $receiverInfo, $contents, $is_return);
            $shipment['status'] = $_shipment['status'];

            $shipment['tracking_code'] = '';
            if (isset($_shipment['tracking_code'])) {
                $shipment['tracking_code'] = $_shipment['tracking_code'];
            }

            if(!empty($this->pk_client->getResponse()->{'response.trackingcode'}['labelcode']) and $this->shop->create_activation_code === true) {
                try {
                    $this->client->call('PUT', '/admin/orders/'.$order['id'].'.json', [
                        'order' => [
                            'id' => $order['id'],
                            'note' => sprintf('%s: %s', trans('app.settings.activation_code'), $this->pk_client->getResponse()->{'response.trackingcode'}['labelcode'])
                        ]
                    ]);
                } catch(\Exception $e) {
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

        if($fulfill_order){

            foreach($shipments as $orderKey => $order) {
                Log::debug("Fullfilling order: {$order['tracking_code']} - {$order['id']}");

                if($order['fulfillment_status'] == 'fulfilled') continue;
                if($order['status'] == 'custom_error') continue;
                if($order['status'] == 'need_shipping_address') continue;

                $services = [];

                foreach($order['line_items'] as $item){
                    $variantId = $item['variant_id'];

                    try {
                        $variants = $this->client->call('GET', '/admin/variants/'.$variantId.'.json');

                        $inventoryId = $variants['inventory_item_id'];

                        // TODO: not the most efficient way to do this
                        $inventoryLevels = $this->client->call('GET', '/admin/inventory_levels.json', ['inventory_item_ids' => $inventoryId]);

                        $makeNull = true;

                        foreach($inventoryLevels as $_inventory) {
                            if($_inventory['available'] > 0 || $_inventory['available'] == NULL){
                                $service = $item['fulfillment_service'];
                                $services[$service][$_inventory['location_id']][] = ['id' => $item['id']];
                                $makeNull = false;
                            } else {
                                $shipments[$orderKey]['status'] = 'not_in_inventory';
                            }
                        }

                        if($makeNull) {
                            Log::debug("NULL item: {$item['id']} - ". var_export($inventoryLevels, true));
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

                foreach($services as $line_items){
                    foreach($line_items as $locationId => $items) {
                        $fulfillment = [
                            'tracking_number' => $order['tracking_code'],
                            'location_id' => $locationId,
                            'tracking_company' => trans('app.settings.company_name'),
                            'tracking_url' => 'https://www.pakettikauppa.fi/seuranta/?' . $order['tracking_code'],
                            'line_items' => $items,
                        ];

                        try {
                            $result = $this->client->call('POST', '/admin/orders/' . $order['id'] . '/fulfillments.json', ['fulfillment' => $fulfillment]);
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
        if($is_return) $page_title = 'return_label';
        if($fulfill_order) $page_title = 'print_label_fulfill';

        return view('app.print-labels', [
            'shop' => $this->shop,
            'orders' => $shipments,
            'orders_url' => 'https://' . $this->shop->shop_origin . '/admin/orders',
            'page_title' => $page_title,
            'is_return' => $is_return,
        ]);
    }

    public function latestNews() {
        $folder_path = storage_path('rss');
        $rssFeed = simplexml_load_file($folder_path.'/feed.xml');

        return view('app.latest-news', [
            'feed' => $rssFeed->channel,
            'shop' => $this->shop,
        ]);
    }
    public function returnLabel(Request $request){
        $params = $request->all();
        $params['is_return'] = true;
        $request = Request::create('print-labels', 'GET', $params);
        \Request::replace($request->input());
        $response = \Route::dispatch($request);
        return $response;
    }

    public function printLabelsFulfill(Request $request){
        $params = $request->all();
        $params['fulfill_order'] = true;
        $request = Request::create('print-labels', 'GET', $params);
        \Request::replace($request->input());
        $response = \Route::dispatch($request);
        return $response;
    }

    public function getLabels(Request $request)
    {
        if(empty($request->tracking_codes)){
            throw new NotFoundHttpException();
        }

        $xml = $this->pk_client->fetchShippingLabels($request->tracking_codes);

        $pdf = base64_decode($xml->{'response.file'});

        return Response::make($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="multiple-shipping-labels.pdf"'
        ]);
    }

    public function getLabel(Request $request, $order_id)
    {
        $is_return = isset($request->is_return) ? $request->is_return : false;

        $shipment = ShopifyShipment::where('shop_id', $this->shop->id)
            ->where('order_id', $order_id)
            ->where('test_mode', $this->shop->test_mode)
            ->where('return', $is_return)
            ->first();

        if(!isset($shipment)){
            throw new NotFoundHttpException();
        }

        $pk_shipment = new Shipment();
        $pk_shipment->setTrackingCode($shipment->tracking_code);
        $pk_shipment->setReference($shipment->reference);

        $this->pk_client->fetchShippingLabel($pk_shipment);

        $pdf_content = base64_decode($pk_shipment->getPdf());

        return Response::make($pdf_content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$shipment->tracking_code.'.pdf"'
        ]);
    }

    public function trackShipment(Request $request){
        $is_return = isset($request->is_return) ? $request->is_return : false;
        $shipment = ShopifyShipment::where('shop_id', $this->shop->id)
            ->where('order_id', $request->id)
            ->where('return', $is_return)
            ->first();

        if(!isset($shipment)){
            return view('app.alert', [
                'type' => 'error',
                'title' => trans('app.messages.no_tracking_info'),
                'message' => '',
            ]);
        }

        $tracking_code = $shipment->test_mode ? 'JJFITESTLABEL100' : $shipment->tracking_code;

        $statuses = json_decode($this->pk_client->getShipmentStatus($tracking_code));

        if(!is_array($statuses) || count($statuses) == 0){
            return view('app.alert', [
                'type' => 'error',
                'title' => trans('app.messages.no_tracking_info'),
                'message' => '',
            ]);
        }

        $admin_order_url = 'https://' . $this->shop->shop_origin . '/admin/orders/' . $shipment->order_id;
        $admin_orders_url = 'https://' . $this->shop->shop_origin . '/admin/orders';

        return view('app.shipment-status', [
            'statuses' => $statuses,
            'current_shipment' => $shipment,
            'order_url' => $admin_order_url,
            'orders_url' => $admin_orders_url,
        ]);
    }
}
