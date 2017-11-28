<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use Pakettikauppa\Client;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Psy\Exception\FatalErrorException;
use Log;

class PickupPointsController extends Controller
{
    public function list(Request $request)
    {
        Log::error(var_export($request, true));

        // SETUP EVERYTHING
        // setup and validate Shop
        $found_shop = Shop::where('shop_origin', $request->header('x-shopify-shop-domain'))->first();

        if(isset($found_shop)){
            $shop = $found_shop;
        }else{
            throw new FatalErrorException();
        }

        $client = new ShopifyClient($request->header('x-shopify-shop-domain'), '', ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        $calculatedMac = base64_encode(hash_hmac('sha256', $request->getContent(), ENV('SHOPIFY_SECRET'), true));
        if(!hash_equals($calculatedMac, $request->header('x-shopify-hmac-sha256'))) {
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
        if (!(isset($shop->pickuppoints_count) && $shop->pickuppoints_count > 0)) {
            return;
        }

        // fetch pickup points
        // fetch shipping providers
        $shipping_settings = unserialize($shop->shipping_settings);

        $rates = array();
        if(count($providers) > 0) {
            // get destination address
            $requestBody = $request->getContent();
            $destination = json_decode($requestBody)->rate->destination;

            // search nearest pickup locations
            $pickupPoints = json_decode($pk_client->searchPickupPoints($destination->postal_code, $destination->address1, $destination->country, $shop->pickuppoint_providers, $shop->pickuppoints_count ));

            // generate custom carrier service response
            foreach($pickupPoints as $_pickupPoint) {
                $rates[] = array(
                        'service_name' => "{$_pickupPoint->name}, {$_pickupPoint->street_address}, {$_pickupPoint->postcode}, {$_pickupPoint->city}",
                        'description' => ($_pickupPoint->description==null?'':$_pickupPoint->description),
                        'service_code' => "{$_pickupPoint->provider}:{$_pickupPoint->pickup_point_id}",
                        'currency' => 'EUR',
                        'total_price' => 0
                );
            }
        }
        
        $customCarrierServices = array('rates' => $rates);

        echo json_encode($customCarrierServices);
    }
}
