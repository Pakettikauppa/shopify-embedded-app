<?php

namespace App\Console\Commands;

use App\Models\Shopify\Shop;
use App\Models\Shopify\ShopifyClient;
use Illuminate\Console\Command;

class UpdatePickupPointSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:update-pickup-point-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all customers carrier serives';

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
            foreach ($settings as $name => $setting) {
                switch ($name) {
                    case 'Posti':
                        $newJson->{'2103'} = $setting;
                        break;
                    case 'Matkahuolto':
                        $newJson->{'90080'} = $setting;
                        break;
                    case 'DB Schenker':
                        $newJson->{'80010'} = $setting;
                        break;
                }
            }

            $shop->settings = json_encode($newJson);
            $shop->save();
        }
    }
}
