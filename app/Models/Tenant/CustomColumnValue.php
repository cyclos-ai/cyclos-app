<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomColumnValue extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public function customColumn(): BelongsTo
    {
        return $this->belongsTo(CustomColumn::class);
    }
}
