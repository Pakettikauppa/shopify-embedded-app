<?php

/*
  The MIT License (MIT)

  Copyright (C) 2011 by Sandeep Shetty

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in
  all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  THE SOFTWARE.
 */

namespace App\Models\Shopify;

use App\Exceptions\ShopifyApiException;
use App\Exceptions\ShopifyCurlException;
use Exception;
use Log;
use Illuminate\Support\Facades\Http;

class ShopifyClient {

    public $shop_domain;
    private $token;
    private $api_key;
    private $secret;
    private $last_response_headers = null;
    private $api_version = '2021-07';
    private $api_calls = 0;

    public function __construct($shop_domain, $token, $api_key, $secret) {
        $this->name = "ShopifyClient";
        $this->shop_domain = $shop_domain;
        $this->token = $token;
        $this->api_key = $api_key;
        $this->secret = $secret;
    }

    // Get the URL required to request authorization
    public function getAuthorizeUrl($scope, $redirect_url, $nonce) {
        $url = "https://{$this->shop_domain}/admin/";
        $url .= "oauth/authorize?client_id={$this->api_key}";
        $url .= "&scope=" . urlencode($scope);
        $url .= "&redirect_uri=" . urlencode($redirect_url);
        $url .= "&state=" . urlencode($nonce);
        return $url;
    }

    public function getAuthorizeUrlArray($scope, $redirect_url, $nonce) {
        $url['domain'] = $this->shop_domain;
        $url['path'] = "/oauth/authorize?client_id={$this->api_key}" .
                "&scope={$scope}" .
                "&redirect_uri={$redirect_url}" .
                "&state={$nonce}";

        return $url;
    }

    // Once the User has authorized the app, call this with the code to get the access token
    public function getAccessToken($code) {
        // POST to  POST https://SHOP_NAME.myshopify.com/admin/oauth/access_token
        $url = "https://{$this->shop_domain}/admin/oauth/access_token";
        $payload = "client_id={$this->api_key}&client_secret={$this->secret}&code=$code";
        $response = $this->curlHttpApiRequest('POST', $url, '', $payload, array());
        $response = json_decode($response, true);
        if (isset($response['access_token'])) {
            return $response['access_token'];
        }
        return '';
    }

    public function callsMade() {
        return $this->shopApiCallLimitParam(0);
    }

    public function callLimit() {
        return $this->shopApiCallLimitParam(1);
    }

    public function callsLeft() {
        return $this->callLimit() - $this->callsMade();
    }

    public function call($method, $section = false, $path = false, $params = array()) {
        if (!$section && !$path) {
            return $this->callGraphQL($method);
        }
        $this->api_calls++;
        $baseURL = "https://{$this->shop_domain}/{$section}/api/{$this->api_version}/";

        $url = $baseURL . ltrim($path, '/');
        $query = in_array($method, array('GET', 'DELETE')) ? $params : array();
        $payload = in_array($method, array('POST', 'PUT')) ? json_encode($params) : array();
        $request_headers = in_array($method, array('POST', 'PUT')) ?
                array("Content-Type: application/json; charset=utf-8", 'Expect:') :
                array();

        // add auth headers
        $request_headers[] = 'X-Shopify-Access-Token: ' . $this->token;

        $response = $this->curlHttpApiRequest($method, $url, $query, $payload, $request_headers);
        $response = json_decode($response, true);
        Log::debug('REST ' . $method . ' call to ' . $url . ' Times called in request: ' . $this->api_calls);

        if (isset($response['errors']) or ($this->last_response_headers['http_status_code'] >= 400)) {
            throw new ShopifyApiException($method, $path, $params, $this->last_response_headers, $response);
        }
        return (is_array($response) and (count($response) > 0)) ? array_shift($response) : $response;
    }

    public function callGraphQL($query) {
        $url = "https://{$this->shop_domain}/admin/api/{$this->api_version}/graphql.json";

        $response = Http::withHeaders([
                    'Content-Type' => 'application/graphql',
                    'X-Shopify-Access-Token' => $this->token
                ])->withBody($query, 'application/graphql')
                ->post($url);
        $response = json_decode($response, true);
        Log::debug('GraphQL call to ' . $url . "\n" . $query . "\nResponse: " . var_export($response, true));
        if (isset($response['errors'])) {
            $cost = $response['errors'][0]['extensions']['cost'];
            Log::debug("Cost " . $cost);
            throw new \Exception("GraphQL errors: " . $response['errors'][0]['message']);
        
        }
        return (is_array($response) && (isset($response['data']))) ? $response['data'] : false;
    }

    public function validateSignature($query) {
        $expectedHmac = isset($query['hmac']) ? $query['hmac'] : '';

        // First step: remove HMAC and signature keys
        unset($query['hmac'], $query['signature']);

        // Second step: keys are sorted lexicographically
        ksort($query);

        // arrays to string
        foreach ($query as $key => $item) {
            if (is_array($item)) {
                $query[$key] = '["' . implode('", "', $item) . '"]';
            }
        }

        $pairs = [];
        foreach ($query as $key => $value) {
            if (in_array($key, ['_pk_s', '_enable_cookies'])) {
                // Third step: "&" and "%" are replaced by "%26" and "%25" in keys and values, and in addition
                // "=" is replaced by "%3D" in keys
                $key = strtr($key, ['&' => '%26', '%' => '%25', '=' => '%3D']);
                $value = strtr($value, ['&' => '%26', '%' => '%25']);
                $pairs[] = $key . '=' . $value;
            }
        }

        $key = implode('&', $pairs);

        $result = hash_equals($expectedHmac, hash_hmac('sha256', $key, $this->secret));

        Log::debug("Compare: {$key} as " .
                hash_hmac('sha256', $key, $this->secret) .
                " to {$expectedHmac} and result is {$result}");

        return true;

//        return $result;
    }

    private function curlHttpApiRequest($method, $url, $query = '', $payload = '', $request_headers = array()) {
        $url = $this->curlAppendQuery($url, $query);
        $ch = curl_init($url);
        $this->curlSetopts($ch, $method, $payload, $request_headers);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new ShopifyCurlException($error, $errno);
        }
        list($message_headers, $message_body) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
        $this->last_response_headers = $this->curlParseHeaders($message_headers);

        return $message_body;
    }

    private function curlAppendQuery($url, $query) {
        if (empty($query)) {
            return $url;
        }
        if (is_array($query)) {
            return "$url?" . http_build_query($query);
        } else {
            return "$url?$query";
        }
    }

    private function curlSetopts($ch, $method, $payload, $request_headers) {
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ohShopify-php-api-client');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($request_headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        }

        if ($method != 'GET' && !empty($payload)) {
            if (is_array($payload)) {
                $payload = http_build_query($payload);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    }

    private function curlParseHeaders($message_headers) {
        $header_lines = preg_split("/\r\n|\n|\r/", $message_headers);
        $headers = array();
        $http_line = explode(' ', trim(array_shift($header_lines)), 3);

        $headers['http_proto'] = $http_line[0];
        $headers['http_status_code'] = $http_line[1];
        $headers['http_status_message'] = $htt_line[2] ?? 'OK';

        foreach ($header_lines as $header_line) {
            list($name, $value) = explode(':', $header_line, 2);
            $name = strtolower($name);
            $headers[$name] = trim($value);
        }

        return $headers;
    }

    private function shopApiCallLimitParam($index) {
        if ($this->last_response_headers == null) {
            throw new Exception('Cannot be called before an API call.');
        }
        $params = explode('/', $this->last_response_headers['http_x_shopify_shop_api_call_limit']);

        if (isset($params[$index])) {
            return (int) $params[$index];
        } else {
            return (int) ($index * 20);
        }
    }
    
    public function buildGraphQLInput($array) {
        $output = '';
        $total = count($array);
        $counter = 0;
        foreach ($array as $key => $value) {
            $counter++;
            if (is_array($value)) {
                $output .= $key . ': ' . $this->buildGraphQLInput($value);
            } else {
                $output .= $key . ': "' . $value . '"';
            }
            if ($counter != $total) {
                $output .= ', ';
            }
        }
        return '{' . $output . '}';
    }

    public function getGraphId($gid) {
        $data = explode('/', $gid);
        return end($data);
    }
    
    public function getOrders($order_ids){
        //create response structure
        $orders = [
            'orders' => [
                 'edges' => []
            ]
        ];
        //split order calls to graphql
        foreach ($order_ids as $id){
            $order = $this->callGraphQL($this->ordersQuery([$id]));
            if (isset($order['orders']['edges'][0])){
                $orders['orders']['edges'][] = $order['orders']['edges'][0];
            }
        }
        return $orders;
    }
    
    private function ordersQuery($order_ids){
        $total_orders = count($order_ids);
        $filter = implode(' OR id:', $order_ids);
        $query = <<<GQL
            {
                orders(first: $total_orders, query: "$filter") {
                  edges {
                    node {
                      id
                      legacyResourceId
                      email
                      phone
                      totalWeight
                      lineItems(first: 30) {
                        edges {
                            node {
                                id
                                requiresShipping
                                quantity
                                name
                                variant {
                                    weight
                                    weightUnit
                                    price
                                    inventoryItem {
                                        countryCodeOfOrigin
                                      	harmonizedSystemCode
                                        inventoryLevels(first: 10) {
                                          edges {
                                            node {
                                                available
                                                location {
                                                    id
                                                    legacyResourceId
                                                }
                                            }
                                          }
                                        }
                                    }
                                    fulfillmentService {
                                        type
                                    }
                                }
                            }
                        }
                      }
                      billingAddress {
                        address1
                        address2
                        city
                        company
                        name
                        phone
                        countryCode
                        zip
                        phone
                      }
                      shippingAddress {
                        address1
                        address2
                        city
                        company
                        name
                        phone
                        countryCode
                        zip
                        phone
                      }
                      fulfillments {
                        status
                      }
                      shippingLine {
                        title
                        code
                      }
                    }
                  }
                }
              }
            GQL;
        return $query;    
    }

}
