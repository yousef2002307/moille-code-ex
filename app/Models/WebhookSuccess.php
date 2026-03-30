<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookSuccess extends Model
{
    protected $table = 'webhook_success';

    protected $fillable = [
        'payment_id',
        'status',
        'webhook_data',
        'ip_address',
    ];

    protected $casts = [
        'webhook_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
