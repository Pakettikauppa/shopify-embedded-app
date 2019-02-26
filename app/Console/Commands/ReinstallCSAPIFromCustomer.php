<?php

namespace App\Console\Commands;

use App\Models\Shopify\Shop;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class ReinstallCSAPIFromCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:reinstall-cs-api {shop_origin}';

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

        $_client = new ShopifyClient($shop->shop_origin, $shop->token, ENV('SHOPIFY_API_KEY'), ENV('SHOPIFY_SECRET'));

        try {
            $_client->call('DELETE', '/admin/carrier_services/' . $shop->carrier_service_id . '.json');
        } catch (\Exception $e) {
            echo "Delete: ".$e->getMessage()."\n";
        }

        $shop->carrier_service_id = null;
        $shop->save();

        $carrierServiceName = 'Pakettikauppa: Noutopisteet / Pickup points';

        $carrierServiceData = array(
            'carrier_service' => array(
                'name' => $carrierServiceName,
                'callback_url' => 'http://209.50.56.85/api/pickup-points',
                'service_discovery' => true,
            )
        );

        // TODO: cache this result so we don't bug users with every request

        try {
            $carrierService = $_client->call('POST', '/admin/carrier_services.json', $carrierServiceData);

            // set carrier_service_id and set it's default count value
            $shop->carrier_service_id = $carrierService['id'];

            $shop->save();
        } catch (\Exception $sae) {
            echo "Add: ".$e->getMessage()."\n";
        }
    }
}
