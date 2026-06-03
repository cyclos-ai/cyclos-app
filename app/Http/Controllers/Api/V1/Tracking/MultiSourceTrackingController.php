<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Controller;
use App\Services\Tracking\MultiSourceTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MultiSourceTrackingController extends Controller
{
    public function __construct(
        private readonly MultiSourceTrackingService $trackingService,
    ) {}

    /**
     * GET /api/v1/tracking/container/{number}?carrier=ZIMU&bol=ZIMUVSS1234567
     *
     * Tries JSONCargo first; falls back to the carrier's own DCSA endpoint.
     * Always returns HTTP 200 with a structured body:
     *   found:true  + the normalized container data
     *   found:false + attempted[] explaining each failure
     */
    public function track(Request $request, string $number): JsonResponse
    {
        $request->validate([
            'carrier' => 'nullable|string|max:10',
            'bol'     => 'nullable|string|max:50',
        ]);

        $result = $this->trackingService->track(
            containerNumber: $number,
            carrierScac:     $request->query('carrier'),
            bol:             $request->query('bol'),
        );

        return $this->success($result);
    }

    /**
     * GET /api/v1/tracking/sources
     *
     * Lists every provider in the chain and whether it is currently configured.
     * Useful for the UI to display coverage status.
     */
    public function sources(): JsonResponse
    {
        return $this->success($this->trackingService->providerSources());
    }
}
