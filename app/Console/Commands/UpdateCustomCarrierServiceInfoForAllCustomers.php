<?php

namespace App\Console\Commands;

use App\Models\Shopify\Shop;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class UpdateCustomCarrierServiceInfoForAllCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:update-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all customers carrier services';
    
    
    private $type;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->type = config('shopify.type');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shops = Shop::whereNotNull('shop_origin')->get();

        $carrierName = "Pakettikauppa";
        if ($this->type == "itella"){
            $carrierName = "Itella";
        }
        if ($this->type == "posti"){
            $carrierName = "Posti";
        }
        $carrierServiceName = $carrierName.': Noutopisteet / Pickup points';
        
        $carrierServiceData = array(
            'carrier_service' => array(
                'name' => $carrierServiceName,
                'callback_url' => route('shopify.pickuppoints.list'),
                'service_discovery' => true,
            )
        );

        foreach ($shops as $shop) {
            $_client = new ShopifyClient(
                $shop->shop_origin,
                $shop->token,
                ENV('SHOPIFY_API_KEY'),
                ENV('SHOPIFY_SECRET')
            );

            try {
                $_client->call(
                    'PUT',
                    'admin',
                    '/carrier_services/' . $shop->carrier_service_id . '.json',
                    $carrierServiceData
                );
            } catch (\Exception $e) {
                $e->getMessage();
            }
        }
    }
}
