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
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Sender;
use Psy\Exception\FatalErrorException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppController extends Controller
{
    private $client;
    private $shop;
    private $pk_client;
    private $shop_info;
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
                    $this->shop_info = $this->client->call('GET', '/admin/shop.json');
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
                }else{
                    $pk_client_params = false;
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
        $grouped_services = [];

        if(isset($this->pk_client)){
            try {
                $resp = $this->pk_client->listShippingMethods();
                $products = json_decode($resp, true);
            } catch (\Exception $ex)  {
                throw new FatalErrorException();
            }
            $grouped_services = array_group_by($products, function($i){  return $i['service_provider']; });
            ksort($grouped_services);
        }

        return view('app.settings', [
            'shipping_methods' => $grouped_services,
            'shop' => $this->shop
        ]);
    }

    public function updateSettings(Request $request)
    {
        if(isset($this->shop->api_key) && isset($this->shop->api_secret)){
            $this->shop->test_mode = $request->test_mode;
        }
        $this->shop->shipping_method_code = $request->shipping_method;
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

        $client = new Client([
            'api_key' => $request->api_key,
            'secret' => $request->api_secret,
        ]);

        // api check
        // @todo uncomment on production to check api credentials
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
        if(!isset($this->pk_client)){
            return view('app.alert', [
                'type' => 'error',
                'title' => trans('app.messages.no_api'),
                'message' => trans('app.messages.no_api_set_error', ['settings_url' => route('shopify.settings')]),
            ]);
        }

        $pk_client = $this->pk_client;

        // api check
        $result = json_decode($pk_client->listShippingMethods());
        if(!is_array($result)){
            return view('app.alert', [
                'type' => 'error',
                'title' => trans('app.messages.no_api'),
                'message' => trans('app.messages.no_api_set_error', ['settings_url' => route('shopify.settings')]),
            ]);
        }

        if(isset($request->ids)){
            $order_ids = $request->ids;
        }else{
            $order_ids = [$request->id];
        }

        $orders = $this->client->call('GET', '/admin/orders.json', ['ids' => implode(',', $order_ids), 'status' => 'any']);
        $settings = $this->shop_info;

        foreach($orders as &$order){
            $order['admin_order_url'] = 'https://' . $this->shop->shop_origin . '/admin/orders/' . $order['id'];

            if(!isset($order['shipping_address'])){
                $order['status'] = 'need_shipping_address';
                continue;
            }
            $done_shipment = ShopifyShipment::where('shop_id', $this->shop->id)->where('order_id', $order['id'])->first();
            if($done_shipment){
                $order['status'] = $done_shipment->status;
                $order['tracking_code'] = $done_shipment->tracking_code;
                continue;
            }

            $shipping_address = $order['shipping_address'];

            $sender = new Sender();
            $sender->setName1($settings['shop_owner']);
            $sender->setAddr1($settings['address1'] . ' ' . $settings['address2']);
            $sender->setPostcode($settings['zip']);
            $sender->setCity($settings['city']);
            $sender->setCountry($settings['country']);

            $receiver = new Receiver();
            $receiver->setName1($shipping_address['first_name'] . " ".$shipping_address['last_name']);
            $receiver->setAddr1($shipping_address['address1']);
            $receiver->setPostcode($shipping_address['zip']);
            $receiver->setCity($shipping_address['city']);
            $receiver->setCountry($shipping_address['country_code']);
            $receiver->setEmail($order['email']);
            $receiver->setPhone($shipping_address['phone']);

            $info = new Info();
            $info->setReference($order['id']);

            $volume = $order['total_weight'] * 0.001;

            $parcel = new Parcel();
            $parcel->setReference($order['id']);
            $parcel->setWeight($order['total_weight']); // kg
            $parcel->setVolume($volume); // m3
            $parcel->setContents('');

            $shipment = new Shipment();
            $shipment->setShippingMethod($this->shop->shipping_method_code); // shipping_method_code that you can get by using listShippingMethods()
            $shipment->setSender($sender);
            $shipment->setReceiver($receiver);
            $shipment->setShipmentInfo($info);
            $shipment->addParcel($parcel);

    //$additional_service = new AdditionalService();
    //$additional_service->setServiceCode(3104); // fragile
    //$shipment->addAdditionalService($additional_service);

            try {
                $pk_client->createTrackingCode($shipment);
                $pk_client->fetchShippingLabel($shipment);

                $tracking_code = $shipment->getTrackingCode();
                $reference = $shipment->getReference();

                $shopify_shipment = new ShopifyShipment();
                $shopify_shipment->shop_id = $this->shop->id;
                $shopify_shipment->order_id = $order['id'];
                $shopify_shipment->status = 'sent';
                $shopify_shipment->tracking_code = $tracking_code;
                $shopify_shipment->reference = $reference;
                $shopify_shipment->save();

            } catch (\Exception $ex)  {
//                echo $ex->getMessage();
//                exit;
                throw new FatalErrorException();
            }

            $order['status'] = 'created';
            $order['tracking_code'] = $tracking_code;
        }

        return view('app.print-labels', [
            'orders' => $orders
        ]);
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
        $shipment = ShopifyShipment::where('shop_id', $this->shop->id)->where('order_id', $request->id)->first();

        if(!isset($shipment)){
            return view('app.alert', [
                'type' => 'error',
                'title' => trans('app.messages.no_tracking_info'),
                'message' => '',
            ]);
        }

        $tracking_code = $this->shop->test_mode ? 'JJFITESTLABEL100' : $shipment->tracking_code;

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
