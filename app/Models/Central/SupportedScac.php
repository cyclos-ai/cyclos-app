<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportedScac extends Model
{
    use HasFactory;

    protected $table = 'supported_scacs';

    protected $connection = 'central';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
