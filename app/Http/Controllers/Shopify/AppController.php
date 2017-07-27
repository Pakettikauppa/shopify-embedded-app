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


/**
 * @property \App\Models\Shopify\Shop $shop
 */
class AppController extends Controller
{
    private $client;
    private $shop;
    private $pk_client;

    public function __construct(Request $request)
    {
        $this->middleware(function ($request, $next) {

            if(!session()->has('shop')){
                session()->put('init_request', $request->fullUrl());
                return redirect()->route('shopify.auth.index', request()->all());
            }

            $shop_origin = session()->get('shop');
            $shop = Shop::where('shop_origin', $shop_origin)->first();
            if(!isset($shop)){
                session()->put('init_request', $request->fullUrl());
                return redirect()->route('shopify.auth.index', request()->all());
            }

            $this->shop = $shop;
            $this->client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

            // check shopify API
            if(\Route::currentRouteName() == 'shopify.settings'){
                try{
                    $this->client->call('GET', '/admin/shop.json');
                }catch(ShopifyApiException $e){
                    session()->put('init_request', $request->fullUrl());
                    return redirect()->route('shopify.auth.index', request()->all());
                }
            }

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

            return $next($request);
        });
    }

    public function settings()
    {
        $shipping_zones = $this->client->call('GET', '/admin/shipping_zones.json');

//        dd($shipping_zones);

        $shipping_rates = $shipping_zones[0]['weight_based_shipping_rates'];
        $shipping_settings = unserialize($this->shop->shipping_settings);

        $result_rates = [];
        foreach($shipping_rates as $rate){
            $arr = [];
            $arr['id'] = $rate['id'];
            $arr['name'] = $rate['name'];
            $arr['product_code'] = '';
            foreach($shipping_settings as $item){
                if($item['shipping_rate_id'] == $rate['name']){
                    $arr['product_code'] = $item['product_code'];
                }
            }
            $result_rates[] = $arr;
        }
        $grouped_services = [];

        try {
            $resp = $this->pk_client->listShippingMethods();
            $products = json_decode($resp, true);
        } catch (\Exception $ex)  {
            throw new FatalErrorException();
        }
        $api_valid = isset($products);
        if($api_valid){
            $grouped_services = array_group_by($products, function($i){  return $i['service_provider']; });
            ksort($grouped_services);
        }

//        dd($result_rates);

        return view('app.settings', [
            'shipping_methods' => $grouped_services,
            'shop' => $this->shop,
            'additional_services' => unserialize($this->shop->additional_services),
            'api_valid' => $api_valid,
            'shipping_rates' => $result_rates
        ]);
    }

    public function updateSettings(Request $request)
    {
//        dd($request->all());
//        $additional_services = [];

        $shipping_settings = [];
        foreach($request->shipping_method as $key => $code){
            $shipping_settings[] = [
                'shipping_rate_id' => $key,
                'product_code' => $code
             ];
        }
//        if(isset($request->additional_services)){
//            $additional_services = $request->additional_services;
//        }
//        $this->shop->additional_services = serialize($additional_services);

        if(isset($this->shop->api_key) && isset($this->shop->api_secret)){
            $this->shop->test_mode = (bool) $request->test_mode;
        }
//        $this->shop->shipping_method_code = $request->shipping_method;
        $this->shop->shipping_settings = serialize($shipping_settings);
        $this->shop->business_name = $request->business_name;
        $this->shop->address = $request->address;
        $this->shop->postcode = $request->postcode;
        $this->shop->city = $request->city;
        $this->shop->country= $request->country;
        $this->shop->email = $request->email;
        $this->shop->phone= $request->phone;
        $this->shop->iban = $request->iban;
        $this->shop->bic= $request->bic;
        $this->shop->save();

        return redirect()->route('shopify.settings');
    }

    public function setApiCredentials(Request $request){
        if(!isset($request->api_key) || !isset($request->api_secret)) {
            $result = [
                'status' => 'error',
                'message' => trans('app.messages.invalid_credentials'),
            ];

            return response()->json($result);
        }

        // api check
        // @todo uncomment on production to check api credentials

//        $client = new Client([
//            'api_key' => $request->api_key,
//            'secret' => $request->api_secret,
//        ]);

//        $result = json_decode($client->listShippingMethods());
//        if(!is_array($result)){
//
//            $result = [
//                'status' => 'error',
//                'message' => trans('app.messages.invalid_credentials'),
//            ];
//            return response()->json($result);
//        }

        $this->shop->api_key = $request->api_key;
        $this->shop->api_secret = $request->api_secret;
        if(isset($request->customer_id)){
            $this->shop->customer_id = $request->customer_id;
        }
        $this->shop->save();

        $result = [
            'status' => 'ok'
        ];

        return response()->json($result);
    }

    public function printLabels(Request $request)
    {
        if(!isset($request->ids) && !isset($request->id)){
            throw new NotFoundHttpException();
        }
        $is_return = isset($request->is_return) ? $request->is_return : false;
        $fulfill_order = isset($request->fulfill_order) ? $request->fulfill_order : false;

        // api check
        $result = json_decode($this->pk_client->listShippingMethods());
        if(!is_array($result)){
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

//        dd($orders);

        foreach($orders as &$order){
            $order['admin_order_url'] = 'https://' . $this->shop->shop_origin . '/admin/orders/' . $order['id'];

            $done_shipment = ShopifyShipment::where('shop_id', $this->shop->id)
                ->where('order_id', $order['id'])
                ->where('test_mode', $this->shop->test_mode)
                ->where('return', $is_return)
                ->first();

            if($done_shipment){
                $order['status'] = 'sent';
                $order['tracking_code'] = $done_shipment->tracking_code;
                continue;
            }
            if(!isset($order['shipping_address'])){
                $order['status'] = 'need_shipping_address';
                continue;
            }
            $shipping_address = $order['shipping_address'];

            $senderInfo = [
                'name' => $this->shop->business_name,
                'address' => $this->shop->address,
                'postcode' => $this->shop->postcode,
                'city' => $this->shop->city,
                'country' => $this->shop->country,
                'phone' => $this->shop->phone,
                'email' => $this->shop->email,
            ];

            $receiverInfo = [
                'name' => $shipping_address['first_name'] . " ".$shipping_address['last_name'],
                'address' => $shipping_address['address1'],
                'postcode' => $shipping_address['zip'],
                'city' => $shipping_address['city'],
                'country' => $shipping_address['country_code'],
                'phone' => $shipping_address['phone'],
                'email' => $order['email'],
            ];

            if($is_return){
                $tmp = $receiverInfo;
                $receiverInfo = $senderInfo;
                $senderInfo = $tmp;
            }

            $order = $this->shop->sendShipment($this->pk_client, $order, $senderInfo, $receiverInfo, $is_return);
        }

        if($fulfill_order){
            foreach($orders as &$order){
                if($order['fulfillment_status'] == 'fulfilled') continue;
                $services = [];
                foreach($order['line_items'] as $item){
                    if($item['fulfillable_quantity'] > 0){
                        $service = $item['fulfillment_service'];
                        $services[$service][] = ['id' => $item['id']];
                    }
                }
                foreach($services as $line_items){
                    $fulfillment = [
                        'tracking_number' => $order['tracking_code'],
                        'tracking_company' => trans('app.settings.company_name'),
                        'tracking_url' => route('shopify.track-shipment', ['id' => $order['id']]),
                        'line_items' => $line_items,
                    ];

                    $this->client->call('POST', '/admin/orders/'. $order['id'] . '/fulfillments.json', ['fulfillment' => $fulfillment]);
                }
            }
        }

        $page_title = 'print_label';
        if($is_return) $page_title = 'return_label';
        if($fulfill_order) $page_title = 'print_label_fulfill';

        return view('app.print-labels', [
            'orders' => $orders,
            'page_title' => $page_title,
            'is_return' => $is_return,
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
        // unfulfill
//        foreach($request->ids as $order_id) {
//            $fulfillment = $this->client->call('GET', '/admin/orders/' . $order_id . '/fulfillments.json');
//
//            foreach ($fulfillment as $item) {
//                if ($item['status'] == 'success') {
//                    $this->client->call('POST', '/admin/orders/' . $order_id . '/fulfillments/' . $item['id'] . '/cancel.json');
//                }
//            }
//        }
//        dd('unfulfilled');

        $params = $request->all();
        $params['fulfill_order'] = true;
        $request = Request::create('print-labels', 'GET', $params);
        \Request::replace($request->input());
        $response = \Route::dispatch($request);
        return $response;
    }

    public function getLabel($order_id)
    {
        $shipment = ShopifyShipment::where('shop_id', $this->shop->id)->where('order_id', $order_id)->first();

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

        return view('app.shipment-status', [
            'statuses' => $statuses,
            'current_shipment' => $shipment,
            'order_url' => $admin_order_url,
        ]);
    }

    public function setupWizard(){
        return view('app.setup-wizard', [
            'shop' => $this->shop
        ]);
    }

    public function signContractLink(Request $request){
        if(!isset($this->shop->customer_id)){
            throw new FatalErrorException();
        }

        $base_url = 'https://oak.dev/sign-contract?';

        $timestamp = time();
        $hash = hash_hmac('sha256', $this->shop->customer_id . "&" . $timestamp, env('CHECKOUT_TOKEN_API_SECRET'));

        $args = [
            'customer'   => $this->shop->customer_id,
            'timestamp'     => $timestamp,
            'hash'          => $hash,
        ];

        $link = $base_url . http_build_query($args);

        return  response($link);

    }
}
