<?php

namespace App\Http\Controllers\Api\V1\Rail;

use App\Http\Controllers\Controller;
use App\Models\Tenant\RailMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RailController extends Controller
{
    /**
     * GET /api/v1/rail/carriers
     */
    public function carrierLookup(Request $request): JsonResponse
    {
        $carriers = [
            ['scac' => 'BNSF', 'name' => 'BNSF Railway',                  'network' => 'Class I'],
            ['scac' => 'UP',   'name' => 'Union Pacific Railroad',         'network' => 'Class I'],
            ['scac' => 'CSX',  'name' => 'CSX Transportation',             'network' => 'Class I'],
            ['scac' => 'NS',   'name' => 'Norfolk Southern Railway',       'network' => 'Class I'],
            ['scac' => 'CN',   'name' => 'Canadian National Railway',      'network' => 'Class I'],
            ['scac' => 'CP',   'name' => 'Canadian Pacific Kansas City',   'network' => 'Class I'],
            ['scac' => 'KCS',  'name' => 'Kansas City Southern',           'network' => 'Class I'],
        ];

        return $this->success($carriers);
    }

    /**
     * GET /api/v1/rail/milestones/container/{container_number}
     */
    public function milestonesByContainer(string $containerNumber, Request $request): JsonResponse
    {
        $milestones = RailMilestone::where('container_number', strtoupper($containerNumber))
            ->orderBy('event_date', 'desc')
            ->get();

        if ($milestones->isEmpty()) {
            return $this->notFound('No rail milestones found for container');
        }

        return $this->success($milestones);
    }

    /**
     * GET /api/v1/rail/milestones/{uuid}
     */
    public function milestonesByUuid(string $uuid): JsonResponse
    {
        $milestone = RailMilestone::where('uuid', $uuid)->first();

        if (! $milestone) {
            return $this->notFound('Rail milestone not found');
        }

        return $this->success($milestone);
    }
}
