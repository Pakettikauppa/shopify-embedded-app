<?php

namespace App\Console\Commands\Shopify;

use Illuminate\Console\Command;
use Storage;
use File;

class FetchLatestNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:fetch-latest-news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches latest news to be displayed on app page';

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
        $rssFeed = file_get_contents(config('shopify.rss_feed_url'));
        
        $type = config('shopify.type');
        $feed_dir = "pakettikauppa";
        
        if ($type == "posti" || $type == "itella"){
            $feed_dir = $type;
        }
        
        if(!File::isDirectory(config('shopify.storage_path') . '/' . $feed_dir)){
            Storage::makeDirectory(config('shopify.storage_path') . '/' . $feed_dir);
        }

        Storage::put(config('shopify.storage_path'). '/' . $feed_dir . '/feed.xml', $rssFeed);
    }
}
