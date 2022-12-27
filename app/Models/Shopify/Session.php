<?php

namespace App\Models\Shopify;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $table = "shopify_sessions";

    protected $fillable = ['session_id', 'session_data'];
}
