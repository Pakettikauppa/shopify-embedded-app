<?php

return [
  'storage_path' => env('STORAGE_PATH'),
  'api_key' => env('SHOPIFY_API_KEY'),
  'secret' => env('SHOPIFY_SECRET'),
  'scope' => env('SHOPIFY_SCOPE'),
  'rss_feed_url' => env('RSS_FEED_URL'),
  'type' => env('SHOPIFY_TYPE', 'pakettikauppa'),
  'app_host_name' => env('APP_URL'),
  'test_mode' => env('SHOPIFY_TEST_MODE', false),
  'tracking_url' => env('TRACKING_URL', ''),
  'carrier_name' => env('CARRIER_NAME', ''),
  'support_url' => env('SUPPORT_URL', ''),
];