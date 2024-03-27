<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Pakettikauppa\Client;

class PakettikauppaAPI extends Client
{
    public function searchPickupPoints($postcode = null, $street_address = null, $country = null, $service_provider = null, $limit = 5, $type = null, $attempts = 1): array|null
    {
        for ($i=1; $i<=$attempts; $i++) {
            Log::debug("Search pickup point attempt: {$i}");
            $result = parent::searchPickupPoints($postcode, $street_address, $country, $service_provider, $limit, $type);

            if (!is_null($this->http_response_code)) {
                return $result;
            }
        }

        return null;
    }

}