<?php

namespace App\Helpers;

use Shopify\Clients\Graphql;
use App\Models\Shopify\Shop;
use Log;

class ShopifyAPI
{
    private $client;

    public function __construct(Shop $shop) {
        $this->client = new Graphql($shop->shop_origin, $shop->token);

    }

    public function fullfillOrderNew($fulfillment_data) {
        $query_params = self::buildGraphQLInput($fulfillment_data);
        $queryString = <<<QUERY
            mutation CreateFulfillment {
                fulfillmentCreateV2( fulfillment: $query_params )
                {
                    userErrors {
                        field
                        message 
                    }
                }
            }
            QUERY;
        $data = $this->client->query($queryString);
        return json_decode($data->getBody()->getContents(), true);
    }

    public function getFulfillmentOrder($order_id) {
        if (is_numeric($order_id)) {
            $order_id = "gid://shopify/Order/" . $order_id;
        }
        $queryString = <<<QUERY
            {
                order(id: "$order_id") {
                      id
                      legacyResourceId
                      fulfillments {
                        status
                      }
                      fulfillmentOrders(first: 10) {
                        edges {
                            node {
                              id
                              status
                                lineItems(first: 10) {
                                    edges {
                                        node {
                                            id
                                            sku
                                            totalQuantity
                                            lineItem {
                                                id
                                            }
                                        }
                                    }
                                }
                            }
                        }
                      }
                    }
              }
            QUERY;
        Log::debug($queryString);
        $data = $this->client->query($queryString);
        $response = $data->getBody()->getContents();
        Log::debug($response);
        return json_decode($response, true);
    }

    private static function buildGraphQLInput(array $array) {
        $output_as_array = false;
        $output = '';
        $total = count($array);
        $counter = 0;
        foreach ($array as $key => $value) {
            $counter++;
            if (is_array($value)) {
                if (is_int($key) ){
                    $output_as_array = true;
                    $output .= self::buildGraphQLInput($value);
                } else {
                    $output .= $key . ': ' . self::buildGraphQLInput($value);
                }
            } else {
                if (gettype($value) == "integer"){
                    $output .= $key . ': ' . $value . '';
                } else if (gettype($value) == "boolean"){
                    $output .= $key . ': ' . ($value?'true':'false') . '';
                } else {
                    $output .= $key . ': "' . $value . '"';
                }
            }
            if ($counter != $total) {
                $output .= ', ';
            }
        }
        if ($output_as_array){
            return '[' . $output . ']';
        }
        return '{' . $output . '}';
    }
}
