<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalCarrier extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $guarded = [];

    protected $casts = [
        'supports_tracking' => 'boolean',
        'is_active'         => 'boolean',
        'aliases'           => 'array',
    ];
}
