<?php

namespace App\Http\Controllers\Api\V1\Drayage;

use App\Domain\Drayage\Enums\DrayageStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\DrayageEvent;
use App\Models\Tenant\ImportDrayage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrayageStepController extends Controller
{
    // POST /api/v1/drayage/{uuid}/advance-step
    public function advanceStep(Request $request, string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::findOrFail($uuid);
        $currentStatus = $drayage->drayage_status instanceof DrayageStatus
            ? $drayage->drayage_status
            : DrayageStatus::tryFrom($drayage->drayage_status ?? '') ?? DrayageStatus::PENDING;

        $nextStatus = $this->getNextStatus($currentStatus);

        if (!$nextStatus) {
            return $this->error('No next step available from current status: ' . $currentStatus->label());
        }

        return $this->updateStep($request, $drayage, $nextStatus);
    }

    // PUT /api/v1/drayage/{uuid}/step
    public function setStep(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $drayage = ImportDrayage::findOrFail($uuid);
        $newStatus = DrayageStatus::from($request->input('status'));

        return $this->updateStep($request, $drayage, $newStatus);
    }

    // GET /api/v1/drayage/{uuid}/steps
    public function getSteps(string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::with('drayageEvents')->findOrFail($uuid);
        $currentStatus = $drayage->drayage_status instanceof DrayageStatus
            ? $drayage->drayage_status
            : DrayageStatus::tryFrom($drayage->drayage_status ?? '') ?? DrayageStatus::PENDING;

        $steps = [];
        foreach (DrayageStatus::cases() as $status) {
            if ($status->step() === 0) {
                continue;
            }

            $event = $drayage->drayageEvents
                ->first(fn($e) => ($e->event_type instanceof DrayageStatus ? $e->event_type->value : $e->event_type) === $status->value);

            $steps[] = [
                'step' => $status->step(),
                'status' => $status->value,
                'label' => $status->label(),
                'completed' => $event !== null,
                'is_current' => $currentStatus === $status,
                'event_date' => $event?->event_date,
                'location' => $event?->location,
                'notes' => $event?->notes,
                'recorded_by' => $event?->recorded_by,
            ];
        }

        usort($steps, fn($a, $b) => $a['step'] <=> $b['step']);

        return $this->success([
            'drayage_id' => $drayage->id,
            'container_id' => $drayage->container_id,
            'current_status' => $currentStatus->value,
            'current_step' => $currentStatus->step(),
            'total_steps' => 15,
            'is_complete' => $currentStatus->isTerminal(),
            'steps' => $steps,
        ]);
    }

    // POST /api/v1/drayage/{uuid}/pickup
    public function markPickedUp(Request $request, string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::findOrFail($uuid);
        return $this->updateStep($request, $drayage, DrayageStatus::PICKED_UP);
    }

    // POST /api/v1/drayage/{uuid}/delivered
    public function markDelivered(Request $request, string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::findOrFail($uuid);
        return $this->updateStep($request, $drayage, DrayageStatus::DELIVERED);
    }

    // POST /api/v1/drayage/{uuid}/empty-return
    public function markEmptyReturned(Request $request, string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::findOrFail($uuid);
        return $this->updateStep($request, $drayage, DrayageStatus::EMPTY_RETURNED);
    }

    private function updateStep(Request $request, ImportDrayage $drayage, DrayageStatus $newStatus): JsonResponse
    {
        $previousStatus = $drayage->drayage_status instanceof DrayageStatus
            ? $drayage->drayage_status->value
            : ($drayage->drayage_status ?? DrayageStatus::PENDING->value);

        $drayage->update([
            'drayage_status' => $newStatus->value,
            'status' => $this->mapToDrayageModelStatus($newStatus),
        ]);

        $this->updateTimestampField($drayage, $newStatus, $request);

        DrayageEvent::create([
            'import_drayage_id' => $drayage->id,
            'event_type' => $newStatus->value,
            'event_date' => $request->input('event_date', now()),
            'location' => $request->input('location'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'notes' => $request->input('notes'),
            'recorded_by' => $request->user()?->id,
            'source' => $request->input('source', 'manual'),
            'created_at' => now(),
        ]);

        $this->syncContainerStatus($drayage, $newStatus);

        return $this->success([
            'drayage_id' => $drayage->id,
            'previous_status' => $previousStatus,
            'current_status' => $newStatus->value,
            'step' => $newStatus->step(),
            'label' => $newStatus->label(),
        ], 'Step updated successfully');
    }

    private function updateTimestampField(ImportDrayage $drayage, DrayageStatus $status, Request $request): void
    {
        $timestamp = $request->input('event_date', now());
        $field = match($status) {
            DrayageStatus::AT_TERMINAL => 'terminal_appointment_dt',
            DrayageStatus::PICKED_UP => 'actual_pickup_dt',
            DrayageStatus::IN_TRANSIT_DELIVERY => 'outgate_dt',
            DrayageStatus::ARRIVED_AT_DELIVERY => 'actual_arrival_delivery_dt',
            DrayageStatus::DELIVERED => 'actual_delivery_dt',
            DrayageStatus::EMPTY_AT_DELIVERY => 'empty_at_delivery_dt',
            DrayageStatus::PICKED_UP_EMPTY => 'pickup_empty_dt',
            DrayageStatus::EMPTY_RETURNED => 'empty_return_dt',
            default => null,
        };

        if ($field) {
            $drayage->update([$field => $timestamp]);
        }
    }

    private function syncContainerStatus(ImportDrayage $drayage, DrayageStatus $status): void
    {
        if (!$drayage->container_id) {
            return;
        }

        $containerStatus = match($status) {
            DrayageStatus::PICKED_UP, DrayageStatus::IN_TRANSIT_DELIVERY => 'OUT_FOR_DELIVERY',
            DrayageStatus::EMPTY_RETURNED => 'EMPTY_RETURNED',
            default => null,
        };

        if ($containerStatus) {
            $drayage->container()->update(['status' => $containerStatus]);
        }
    }

    private function mapToDrayageModelStatus(DrayageStatus $status): string
    {
        return match(true) {
            in_array($status, [DrayageStatus::PENDING, DrayageStatus::TENDERED]) => 'pending',
            $status === DrayageStatus::CONFIRMED => 'confirmed',
            $status === DrayageStatus::DISPATCHED => 'dispatched',
            in_array($status, [DrayageStatus::AT_TERMINAL, DrayageStatus::PICKED_UP, DrayageStatus::IN_TRANSIT_DELIVERY]) => 'in_transit',
            in_array($status, [DrayageStatus::ARRIVED_AT_DELIVERY, DrayageStatus::DELIVERING, DrayageStatus::DELIVERED]) => 'delivered',
            in_array($status, [DrayageStatus::EMPTY_AT_DELIVERY, DrayageStatus::PICKED_UP_EMPTY, DrayageStatus::IN_TRANSIT_RETURN, DrayageStatus::EMPTY_RETURNED]) => 'returning',
            $status === DrayageStatus::COMPLETED => 'completed',
            $status === DrayageStatus::CANCELLED => 'cancelled',
            default => 'pending',
        };
    }

    private function getNextStatus(DrayageStatus $current): ?DrayageStatus
    {
        $currentStep = $current->step();
        if ($currentStep === 0 || $current->isTerminal()) {
            return null;
        }

        foreach (DrayageStatus::cases() as $status) {
            if ($status->step() === $currentStep + 1) {
                return $status;
            }
        }

        return null;
    }
}
