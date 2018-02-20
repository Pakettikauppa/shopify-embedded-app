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
    private $pickupPointSettings;

    public function list(Request $request)
    {
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
        if ($shop->settings == null) {
            $shop->settings = '{}';
        }
        $this->pickupPointSettings = json_decode($shop->settings, true);

        $rates = array();
        if(count($this->pickupPointSettings) > 0) {
            // get destination address
            $requestBody = json_decode($request->getContent());
            $destination = $requestBody->rate->destination;

            // calculate total value of the cart
            $totalValue = 0;
            foreach($requestBody->rate->items as $_item) {
                $totalValue += $_item->price;
            }
            $pickupPointProviders = array();

            foreach($this->pickupPointSettings as $_provider => $_settings) {
                if ($_settings['active'] == 'true') {
                    $pickupPointProviders[] = $_provider;
                }
            }

            // convert array to string
            $pickupPointProviders = implode(",", $pickupPointProviders);

            // search nearest pickup locations
            $pickupPoints = json_decode($pk_client->searchPickupPoints($destination->postal_code, $destination->address1, $destination->country, $pickupPointProviders, $shop->pickuppoints_count ));

            // generate custom carrier service response
            try {
            foreach($pickupPoints as $_pickupPoint) {
                $rates[] = array(
                        'service_name' => "{$_pickupPoint->name}, {$_pickupPoint->street_address}, {$_pickupPoint->postcode}, {$_pickupPoint->city}",
                        'description' => ($_pickupPoint->description==null?'':$_pickupPoint->description),
                        'service_code' => "{$_pickupPoint->provider}:{$_pickupPoint->pickup_point_id}",
                        'currency' => 'EUR',
                        'total_price' => $this->priceForPickupPoint($_pickupPoint->provider, $totalValue)
                );
            }
            } catch (\Exception $e) {
                Log::debug(var_export($pickupPoints, true));
            }
        }
        
        $customCarrierServices = array('rates' => $rates);

        echo json_encode($customCarrierServices);
    }

    private function priceForPickupPoint($provider, $totalValue)
    {
        $pickupPointSettings = $this->pickupPointSettings[$provider];

        if ($pickupPointSettings['trigger_price'] <= $totalValue) {
            return (int)($pickupPointSettings['triggered_price'] * 100);
        }

        return (int)($pickupPointSettings['base_price'] * 100);
    }
}
