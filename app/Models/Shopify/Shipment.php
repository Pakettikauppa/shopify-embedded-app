<?php

namespace App\Models\Shopify;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $table = 'shopify_shipments';
    
    protected $casts = [
        'products' => 'array'
    ];
}
