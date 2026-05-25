<?php

namespace App\Http\Controllers\Api\V1\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Container;
use App\Models\Tenant\DemurrageCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * GET /api/v1/calendar/events
     */
    public function events(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());

        $events = [];

        // ETAs
        $containers = Container::query()
            ->whereNotNull('eta')
            ->whereBetween('eta', [$from, $to])
            ->get(['uuid', 'container_number', 'eta', 'pod', 'carrier_scac']);

        foreach ($containers as $container) {
            $events[] = [
                'uuid'        => $container->uuid,
                'type'        => 'eta',
                'title'       => 'ETA: ' . $container->container_number,
                'date'        => $container->eta,
                'metadata'    => [
                    'container_number' => $container->container_number,
                    'pod'              => $container->pod,
                    'carrier_scac'     => $container->carrier_scac,
                ],
            ];
        }

        // Demurrage LFDs
        $demurrageCharges = DemurrageCharge::query()
            ->whereNotNull('last_free_day')
            ->whereBetween('last_free_day', [$from, $to])
            ->whereNull('outgate_date')
            ->get(['uuid', 'container_uuid', 'last_free_day']);

        foreach ($demurrageCharges as $charge) {
            $events[] = [
                'uuid'     => $charge->uuid,
                'type'     => 'demurrage_lfd',
                'title'    => 'LFD (Demurrage)',
                'date'     => $charge->last_free_day,
                'metadata' => ['container_uuid' => $charge->container_uuid],
            ];
        }

        // Sort by date
        usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $this->success([
            'events' => $events,
            'total'  => count($events),
            'period' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * GET /api/v1/calendar/export
     */
    public function export(Request $request): JsonResponse
    {
        $from   = $request->input('from', now()->startOfMonth()->toDateString());
        $to     = $request->input('to', now()->endOfMonth()->toDateString());
        $format = $request->input('format', 'ics');

        // In production this would generate and return a signed URL to a calendar file
        return $this->success([
            'format'     => $format,
            'period'     => ['from' => $from, 'to' => $to],
            'export_url' => null,
            'message'    => 'Calendar export job queued',
        ]);
    }
}
