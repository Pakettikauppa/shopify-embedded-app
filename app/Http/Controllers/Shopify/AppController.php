<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Oak\Models\Shopify\Shop;
use Oak\Models\Shopify\Shipment as ShopifyShipment;
use Pakettikauppa\Client;
use Pakettikauppa\Shipment;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Sender;
use Psy\Exception\FatalErrorException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AppController extends Controller
{
    private $client;
    private $shop;
    public function __construct(Request $request)
    {
        if(isset($request->hmac) && isset($request->shop)){
            // request from shopify
            $this->middleware(function ($request, $next) {
                $shop_origin = $request->shop;
                $shop = Shop::where('shop_origin', $shop_origin)->first();
                if(!isset($shop)){
                    throw new UnprocessableEntityHttpException();
                }
                $this->shop = $shop;
                $this->client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

                if(!$this->client->validateSignature($request->all())){
                    throw new UnprocessableEntityHttpException();
                }

                if(!session()->has('shop')){
                    session()->put('shop', $request->shop);
                }
                return $next($request);
            });
        }else{
            // request from the app
            $this->middleware(function ($request, $next) {
                if(!session()->has('shop')){
                    throw new UnprocessableEntityHttpException();
                }

                $shop_origin = session()->get('shop');
                $shop = Shop::where('shop_origin', $shop_origin)->first();
                if(!isset($shop)){
                    throw new UnprocessableEntityHttpException();
                }
                $this->shop = $shop;
                $this->client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

                return $next($request);
            });
        }
    }

    public function preferences(Request $request){
        $pk_client = new Client(array('test_mode' => true));

        try {
            $resp = $pk_client->listShippingMethods();
            $products = json_decode($resp, true);
        } catch (\Exception $ex)  {
            throw new FatalErrorException();
        }

        $grouped_services = array_group_by($products, function($i){  return $i['service_provider']; });
        ksort($grouped_services);

        return view('app.preferences', [
            'shipping_methods' => $grouped_services,
            'shop' => $this->shop
        ]);
    }

    public function updatePreferences(Request $request){
        $this->shop->shipping_method_code = $request->shipping_method;
        $this->shop->test_mode = $request->test_mode;
        $this->shop->save();

        return redirect()->route('shopify.preferences');
    }

    public function printOrders(Request $request){

        $orders = $this->client->call('GET', '/admin/orders.json', ['ids' => implode(',', $request->ids), 'status' => 'any']);
        $settings = $this->client->call('GET', '/admin/shop.json');

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
            $client = new Client(array('test_mode' => true));

            try {
                $client->createTrackingCode($shipment);
                $client->fetchShippingLabel($shipment);

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

    public function getLabel($order_id){
        $shipment = ShopifyShipment::where('shop_id', $this->shop->id)->where('order_id', $order_id)->first();

        if(!isset($shipment)){
            throw new NotFoundHttpException();
        }

        $pk_shipment = new Shipment();
        $pk_shipment->setTrackingCode($shipment->tracking_code);
        $pk_shipment->setReference($shipment->reference);

        $client = new Client(array('test_mode' => true));
        $client->fetchShippingLabel($pk_shipment);

        $pdf_content = base64_decode($pk_shipment->getPdf());

        return Response::make($pdf_content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$shipment->tracking_code.'.pdf"'
        ]);
    }
}
