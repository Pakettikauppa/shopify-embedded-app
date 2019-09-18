<?php

namespace App\Console\Commands\Shopify;

use Illuminate\Console\Command;
use Storage;

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
        $rssFeed = file_get_contents(env('RSS_FEED_URL'));

        Storage::put(config('shopify.storage_path').'/feed.xml', $rssFeed);
    }
}
