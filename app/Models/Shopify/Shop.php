<?php

namespace App\Models\Shopify;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shopify\Shipment as ShopifyShipment;
use Pakettikauppa\Shipment;
use Pakettikauppa\Shipment\Info;
use Pakettikauppa\Shipment\Parcel;
use Pakettikauppa\Shipment\Receiver;
use Pakettikauppa\Shipment\Sender;
use Pakettikauppa\Shipment\AdditionalService;
use Psy\Exception\FatalErrorException;

class Shop extends Model
{
    protected $table = 'shopify_shops';
 
    /**
     * @var $pk_client \Pakettikauppa\Client
     */
    public function sendShipment($pk_client, $order, $senderInfo, $receiverInfo, $contents, $isReturn = false)
    {
        $sender = new Sender();
        if ($senderInfo['company'] != '') {
            $sender->setName1($senderInfo['company']);
            $sender->setName2($senderInfo['name']);
        } else {
            $sender->setName1($senderInfo['name']);
            $sender->setName2($senderInfo['company']);
        }
        $sender->setAddr1($senderInfo['address']);
        $sender->setPostcode($senderInfo['postcode']);
        $sender->setCity($senderInfo['city']);
        $sender->setCountry($senderInfo['country']);

        if (!empty($senderInfo['phone'])) {
            $sender->setPhone($senderInfo['phone']);
        }

        $receiver = new Receiver();
        if ($receiverInfo['company'] != '') {
            $receiver->setName1($receiverInfo['company']);
            $receiver->setName2($receiverInfo['name']);
        } else {
            $receiver->setName1($receiverInfo['name']);
            $receiver->setName2($receiverInfo['company']);
        }
        $receiver->setAddr1($receiverInfo['address']);
        if (!empty($receiverInfo['address2'])) {
            $receiver->setAddr2($receiverInfo['address2']);
        }
        $receiver->setPostcode($receiverInfo['postcode']);
        $receiver->setCity($receiverInfo['city']);
        $receiver->setCountry($receiverInfo['country']);
        $receiver->setEmail($receiverInfo['email']);
        $receiver->setPhone($receiverInfo['phone']);

        $info = new Info();
        $info->setReference($order['id']);

        $parcel = new Parcel();
        $parcel->setReference($order['id']);
        $parcel->setWeight(number_format($order['total_weight'] * 0.001, 3)); // kg
        $parcel->setVolume(number_format($order['total_weight'] * 0.000001, 6)); // m3
        $parcel->setContents('');

        foreach ($contents as $item) {
            $contentLine = new Shipment\ContentLine();
            $contentLine->currency = 'EUR';
            $contentLine->country_of_origin = 'FI';
            $contentLine->description = $item['name'];
            $contentLine->quantity = $item['quantity'];
            $contentLine->netweight = $item['grams'];
            $contentLine->tariff_code = '';
            $contentLine->value = $item['price'];
            $parcel->addContentLine($contentLine);
        }


        $pickupPointId = null;
        $method_code = null;

        if (isset($order['shipping_lines'][0]['title'])) {
            $shipping_settings = unserialize($this->shipping_settings);
            $service_name = $order['shipping_lines'][0]['title'];

            foreach ($shipping_settings as $item) {
                if ($item['shipping_rate_id'] == $service_name) {
                    $method_code = $item['product_code'];
                }
            }

            if (isset($order['shipping_lines'][0]['code']) && $order['shipping_lines'][0]['code'] != null) {
                $pickupPoint = $this->shippingCode2Method($order['shipping_lines'][0]['code']);
                $pickupPointId = $pickupPoint['pickup_point_id'];

                if (!empty($pickupPointId)) {
                    $method_code = $pickupPoint['method_code'];
                } else {
                    $pickupPointId = null;
                }
            }
        }

        // Don't generate shipping label if so desired
        if ($method_code == 'NO_SHIPPING') {
            return [
                'status' => '',
            ];
        }

        // use default shipping method
        if (empty($method_code)) {
            $method_code = $this->default_service_code;
        }

        $shipment = new Shipment();
        $shipment->setShippingMethod($method_code);
        $shipment->setSender($sender);
        $shipment->setReceiver($receiver);
        $shipment->setShipmentInfo($info);
        $shipment->addParcel($parcel);

        if ($pickupPointId != null and !$isReturn) {
            $additional_service = new AdditionalService();
            $additional_service->setServiceCode(2106);
            $additional_service->addSpecifier('pickup_point_id', $pickupPointId);
            $shipment->addAdditionalService($additional_service);
        }

        if ($this->always_create_return_label == true && !$isReturn) {
            $shipment->includeReturnLabel(true);
        }

        if ($isReturn) {
            $additional_service = new AdditionalService();
            $additional_service->setServiceCode(9902);
            $shipment->addAdditionalService($additional_service);
        }

        if ($this->create_activation_code == true) {
            $additional_service = new AdditionalService();
            $additional_service->setServiceCode(9902);
            $shipment->addAdditionalService($additional_service);
        }

        try {
            $pk_client->createTrackingCode($shipment);

            $tracking_code = (string)$shipment->getTrackingCode();
            $reference = (string)$shipment->getReference();

            $shopify_shipment = new ShopifyShipment();
            $shopify_shipment->shop_id = $this->id;
            $shopify_shipment->order_id = $order['id'];
            $shopify_shipment->tracking_code = $tracking_code;
            $shopify_shipment->reference = $reference;
            $shopify_shipment->test_mode = $this->test_mode;
            $shopify_shipment->return = $isReturn;
            $shopify_shipment->save();
        } catch (\Exception $ex) {
            $order['status'] = 'custom_error';
            $order['error_message'] = $ex->getMessage();
            return $order;
        }

        $order['status'] = 'created';
        $order['tracking_code'] = $tracking_code;

        return $order;
    }

    public function shippingCode2Method($shippingCode)
    {
        $pickupPoint = explode(":", $shippingCode);
        $pickupPointId = null;
        $method_code = null;

        if (count($pickupPoint) == 2) {
            $method_code = $pickupPoint[0];
            $pickupPointId = $pickupPoint[1];
        }

        if (!is_numeric($method_code)) {
            switch ($pickupPoint[0]) {
                case 'Posti':
                    $method_code = '2103';
                    break;
                case 'Matkahuolto':
                    $method_code = '90080';
                    break;
                case 'DB Schenker':
                    $method_code = '80010';
                    break;
                default:
                    // reset to defaults.
                    $method_code = null;
                    $pickupPointId = null;
                    break;
            }
        }
        return [
            'method_code' => $method_code,
            'pickup_point_id' => $pickupPointId
        ];
    }

    /**
     * Saves shop test mode parameter
     * 
     * @param bool $test_mode - true to enable test mode, false to go production
     * 
     * @return bool true on success
     */
    public function saveTestMode($test_mode = true)
    {
        $this->test_mode = $test_mode;

        return $this->save();
    }

    /**
     * Saves shop api credentials
     * 
     * @param string $api_key
     * @param string $api_secret
     * 
     * @return bool true on success
     */
    public function saveApiCredentials($api_key = '', $api_secret = '')
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;

        return $this->save();
    }

    /**
     * Updates locale setting
     * 
     * @param string $locale ISO code for prefered localization
     * 
     * @return bool true on success
     */
    public function saveLocale($locale = 'en')
    {
        $this->locale = $locale;

        return $this->save();
    }

    /**
     * Builds shipping settings array available providers
     * 
     * @param array|null $shipping_methods shiping methods array 
     * @param array $productProviderByCode service providers array
     * 
     * @return array build shipping settings array
     */
    public function buildShippingSettings($shipping_methods, $productProviderByCode = [])
    {
        $shipping_settings = array();

        if (!$shipping_methods) {
            return $shipping_settings;
        }

        foreach ($shipping_methods as $key => $code) {
            $shipping_settings[] = [
                'shipping_rate_id' => $key,
                'product_code' => $code,
                'service_provider' => ($code == null ? '' : $productProviderByCode[(string)$code])
            ];
        }

        return $shipping_settings;
    }

    /**
     * Updates shiping settings
     * 
     * @param array $settings array of shipping settings settings [shipping_settings, default_service_code, always_create_return_label, create_activation_code]
     * @param array $productProviderByCode product providers array
     * 
     * @return bool true on success
     */
    public function saveShippingSettings($settings)
    {
        // if its array serialize it, otherwise assume we got serialized array
        $this->shipping_settings = is_array($settings['shipping_settings']) ? serialize($settings['shipping_settings']) : $settings['shipping_settings'];
        $this->default_service_code = $settings['default_service_code'] ? $settings['default_service_code'] : 0;
        $this->always_create_return_label = $settings['always_create_return_label'];
        $this->create_activation_code = $settings['create_activation_code'];

        return $this->save();
    }

    /**
     * Saves sender information
     * 
     * @param array $sender_data sender settings array
     * 
     * @return bool true on success
     */
    public function saveSender($sender_data)
    {
        foreach ($sender_data as $key => $value) {
            $this->$key = $value;
        }

        return $this->save();
    }

    /**
     * Saves pickup points settings
     * 
     * @param array $data pickup points settings array
     * 
     * @return bool true on success
     */
    public function savePickupPointsSettings($data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }

        return $this->save();
    }

    /**
     * Decodes and returns settings as array (if no settings it will be empty array)
     * 
     * @return array settings array
     */
    public function getSettings()
    {
        return $this->settings == null ? array() : json_decode($this->settings, true);
    }

    /**
     * Creates pickup points settings array
     * 
     * @param array $products shipping methods array
     * 
     * @return array pickup points settings array
     */
    public function getPickupPointSettings($products)
    {
        $pickupPointSettings = $this->getSettings();

        foreach ($products as $product) {
            $shippingMethodCode = (string) $product['shipping_method_code'];

            if ($product['has_pickup_points'] && empty($pickupPointSettings[$shippingMethodCode])) {
                $pickupPointSettings[$shippingMethodCode]['active']          = 'false';
                $pickupPointSettings[$shippingMethodCode]['base_price']      = '0';
                $pickupPointSettings[$shippingMethodCode]['trigger_price']   = '';
                $pickupPointSettings[$shippingMethodCode]['triggered_price'] = '';
            }

            if (!isset($pickupPointSettings[$shippingMethodCode]['active'])) {
                $pickupPointSettings[$shippingMethodCode]['active'] = 'false';
            }
        }

        return $pickupPointSettings;
    }

    /**
     * @param int|null $carrierServiceId carrier service id from Shopify
     * @param int $pickupPointsCount maximum available pickup points
     * 
     * @return bool true on success
     */
    public function saveCarrierServiceId($carrierServiceId = null, $pickupPointsCount = 10)
    {
        $this->carrier_service_id = $carrierServiceId;
        $this->pickuppoints_count = $pickupPointsCount;

        return $this->save();
    }
    
    public function getApiTokenAttribute($value)
    {
        return @json_decode($value);
    }
}
