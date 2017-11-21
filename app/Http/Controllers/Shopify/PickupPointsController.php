<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use Oak\Models\Shopify\Shop;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PickupPointsController extends Controller
{
    public function list(Request $request)
    {
        // SETUP EVERYTHING
        // setup and validate Shop
        $found_shop = Shop::where('shop_origin', $request->header('HTTP_X_SHOPIFY_SHOP_DOMAIN'))->first();

        if(isset($found_shop)){
            $shop = $found_shop;
        }else{
            throw new FatalErrorException();
        }

        $client = new ShopifyClient($request->shop, '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        if(!$client->validateSignature($request->all())){
            throw new UnprocessableEntityHttpException();
        }

        // setup Pakettikauppa Client
        if($shop->test_mode){
            $pk_client_params = [
                'test_mode' => true,
            ];
        }else{
            if(isset($shop->api_key) && isset($shop->api_secret)){
                $pk_client_params = [
                    'api_key' => $shop->api_key,
                    'secret' => $shop->api_secret,
                ];
            }
        }

        if(is_array($pk_client_params)){
            $pk_client = new Client($pk_client_params);
        } else {
            throw new FatalErrorException();            
        }

        // test if pickup points are available in settings
        if (!(isset($shop->settings->pickuppoints_count) && $shop->settings->pickuppoints_count > 0)) {
            return;
        }

        // fetch pickup points
        // fetch shipping providers
        $shipping_settings = unserialize($shop->shipping_settings);

        $providers = array();
        foreach($shipping_settings as $_setting) {
            $providers[$_setting['service_provider']] = $_setting['service_provider'];
        }

        // get destination address
        $destination = json_decode($requestBody)->rate->destination;

        // search nearest pickup locations
        $pickupPoints = json_decode($pk_client->searchPickupPoints($destination->postal_code, $destination->address1, $destination->country, implode(',', $providers), $shop->pickuppoints_count ));

        // generate custom carrier service response
        $rates = array();
        foreach($pickupPoints as $_pickupPoint) {
                $rates[] = array(
                        'service_name' => "{$_pickupPoint->name}, {$_pickupPoint->street_address}, {$_pickupPoint->postcode}, {$_pickupPoint->city}",
                        'description' => ($_pickupPoint->description==null?'':$_pickupPoint->description),
                        'service_code' => "{$_pickupPoint->provider}:{$_pickupPoint->pickup_point_id}",
                        'currency' => 'EUR',
                        'total_price' => 0
                );
        }

        $customCarrierServices = array('rates' => $rates);

        echo json_encode($customCarrierServices);
    }
}
