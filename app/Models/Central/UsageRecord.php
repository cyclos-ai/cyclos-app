<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use HasFactory;
    use HasUuids;

    protected $connection = 'central';

    protected $table = 'tenant_usage_records';

    protected $guarded = [];

    protected $casts = [
        'period_date' => 'date',
        'reported_at' => 'datetime',
        'quantity'    => 'int',
    ];
}
