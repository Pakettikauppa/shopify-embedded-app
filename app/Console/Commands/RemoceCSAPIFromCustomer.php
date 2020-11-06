<?php

namespace App\Console\Commands;

use App\Models\Shopify\Shop;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class RemoceCSAPIFromCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:remove-cs-api {shop_origin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove Custome Carrier Service API from a customer';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shop = Shop::where('shop_origin', $this->argument('shop_origin'))->first();

        if (empty($shop)) {
            echo "No such shop_origin\n";
            return;
        }

        $_client = new ShopifyClient($shop->shop_origin, $shop->token, config('shopify.api_key'), config('shopify.secret'));

        try {
            $_client->call('DELETE', 'admin', '/carrier_services/' . $shop->carrier_service_id . '.json');
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        $shop->carrier_service_id = null;
        $shop->save();
    }
}
