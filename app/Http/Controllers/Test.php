<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shopify\Shop;

class Test extends Controller
{
    private function isValidHMAC($paramString)
    {
        $params = [];
        parse_str($paramString, $params);
        if (!isset($params['hmac'])) {
            return false;
        }
        $hmac = $params['hmac'];
        unset($params['hmac']);
        ksort($params);
        $computed_hmac = hash_hmac('sha256', http_build_query($params), config('shopify.secret'));

        return hash_equals($computed_hmac, $hmac);
    }

    public function index(Request $request)
    {
        // Check that shopdomain is valid
        $shop_domain = $request->input('shop', '');
        if (!$this->isValidShopDomain($shop_domain)) {
            dd('Shop domain is not valid. Must be like - shopname.myshopify.com');
        }

        $api_key = config('shopify.api_key');
        $scopes = config('shopify.scope');
        $redirect_uri = route('shopify.auth.callback');

        // Build install/approval URL to redirect to
        $install_url = "https://" . $shop_domain . "/admin/oauth/authorize?client_id=" . $api_key . "&scope=" . $scopes . "&redirect_uri=" . urlencode($redirect_uri);

        // Due to how shopify works redirection must be done on shopify end (as app is loaded inside iframe)
        return view('app.entry', ['shopOrigin' => $shop_domain, 'api_key' => $api_key, 'install_url' => $install_url]);
    }

    private function isValidShopDomain($shop)
    {
        $substring = explode('.', $shop);

        // 'domain.myshopify.com'
        if (count($substring) != 3) {
            return false;
        }

        // allow dashes and alphanumberic characters
        $substring[0] = str_replace('-', '', $substring[0]);
        return (ctype_alnum($substring[0]) && $substring[1] . '.' . $substring[2] == 'myshopify.com');
    }
}
