<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shopify\Shop;
use App\Models\Shopify\Shipment;
use Log;

class GDPRController extends Controller
{
    //deletes shipment data with order id
    public function customersRedact(Request $request){
        $domain  = $request->input('shop_domain');
        Log::debug("Customer gdpr " . $domain);
        $ids = $request->input('orders_to_redact');
        if (!is_array($ids)){
            $ids = [$ids];
        }
        if ($domain) {
            $shop = Shop::where('shop_origin', $domain)->first();
            if ($shop){
                Shipment::where('shop_id', $shop->id)->whereIn('order_id', $ids)->delete();
            }
        }
        echo 'OK';
    }
    
    //deletes shipments and shop
    public function shopRedact(Request $request){
        $domain  = $request->input('shop_domain');
        Log::debug("Shop gdpr " . $domain);
        if ($domain) {
            $shop = Shop::where('shop_origin', $domain)->first();
            if ($shop){
                Shipment::where('shop_id', $shop->id)->delete();
                $shop->delete();
            }
        }
        echo 'OK';
    }
    
    //we do not save any customer info
    public function dataRequest(){
         Log::debug("GDPR info request");
         
         echo 'OK';
    }
}
