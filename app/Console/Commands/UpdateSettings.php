<?php

namespace App\Console\Commands;

use App\Models\Shopify\Shop;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class UpdateSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:update-settings-to-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all customers settings to json';

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
        $shops = Shop::whereNotNull('shop_origin')->get();

        foreach ($shops as $shop) {
            if ($shop->settings == null) {
                $shop->settings = '{}';
            }

            $settings = json_decode($shop->settings);

            $newJson = new \StdClass();
            $newJson->{'pickup_points'} = $settings;
            $newJson->{'shipping'} = unserialize($this->shop->shipping_settings);
            $shop->settings = json_encode($newJson);
            $shop->save();
        }
    }
}
