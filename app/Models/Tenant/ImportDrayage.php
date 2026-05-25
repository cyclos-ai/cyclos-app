<?php

namespace App\Models\Tenant;

use App\Domain\Drayage\Enums\DrayageStatus;
use App\Domain\Drayage\Enums\DrayageType;
use App\Domain\Drayage\Enums\LoadType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportDrayage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'import_drayage';

    protected $guarded = [];

    protected $casts = [
        'appointment_date' => 'datetime',
        'pickup_date' => 'datetime',
        'delivery_date' => 'datetime',
        'metadata' => 'array',
        'drayage_type' => DrayageType::class,
        'load_type' => LoadType::class,
        'drayage_status' => DrayageStatus::class,
        'drayage_leg' => 'integer',
        'terminal_appointment_dt' => 'datetime',
        'pickup_appointment_dt' => 'datetime',
        'actual_pickup_dt' => 'datetime',
        'outgate_dt' => 'datetime',
        'delivery_appointment_dt' => 'datetime',
        'actual_arrival_delivery_dt' => 'datetime',
        'actual_delivery_dt' => 'datetime',
        'empty_at_delivery_dt' => 'datetime',
        'pickup_empty_dt' => 'datetime',
        'empty_return_dt' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function drayageEvents(): HasMany
    {
        return $this->hasMany(DrayageEvent::class)->orderBy('event_date', 'asc');
    }

    public function getCurrentStep(): int
    {
        $status = $this->drayage_status instanceof DrayageStatus
            ? $this->drayage_status
            : DrayageStatus::tryFrom($this->drayage_status ?? '') ?? DrayageStatus::PENDING;

        return $status->step();
    }

    public function getNextStep(): ?DrayageStatus
    {
        $status = $this->drayage_status instanceof DrayageStatus
            ? $this->drayage_status
            : DrayageStatus::tryFrom($this->drayage_status ?? '') ?? DrayageStatus::PENDING;

        $currentStep = $status->step();

        if ($currentStep === 0 || $status->isTerminal()) {
            return null;
        }

        foreach (DrayageStatus::cases() as $candidate) {
            if ($candidate->step() === $currentStep + 1) {
                return $candidate;
            }
        }

        return null;
    }

    public function canTransitionTo(DrayageStatus $status): bool
    {
        $current = $this->drayage_status instanceof DrayageStatus
            ? $this->drayage_status
            : DrayageStatus::tryFrom($this->drayage_status ?? '') ?? DrayageStatus::PENDING;

        if ($current->isTerminal()) {
            return false;
        }

        // Allow moving to any non-terminal step ahead of current, or to terminal states
        return $status->step() > $current->step() || $status->isTerminal();
    }

    public function isComplete(): bool
    {
        $status = $this->drayage_status instanceof DrayageStatus
            ? $this->drayage_status
            : DrayageStatus::tryFrom($this->drayage_status ?? '') ?? DrayageStatus::PENDING;

        return $status->isTerminal();
    }
}
