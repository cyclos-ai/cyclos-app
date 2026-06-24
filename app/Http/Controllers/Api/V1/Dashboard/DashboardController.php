<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardResource;
use App\Models\Tenant\Container;
use App\Models\Tenant\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/stats
     * Real KPI tiles for the overview page, scoped to the current tenant.
     * Returns a FLAT object — the overview reads these keys directly.
     */
    public function stats(Request $request): JsonResponse
    {
        $total      = Container::count();
        $inTransit  = Container::whereIn('status', ['LOADED_ON_VESSEL', 'ON_WATER', 'AWAITING_DISCHARGE'])->count();
        $atTerminal = Container::whereIn('status', ['AT_OCEAN_TERMINAL', 'ARRIVED_AT_RAIL_TERMINAL'])->count();
        $alerts     = Container::whereNotNull('last_free_day_demurrage')
            ->whereDate('last_free_day_demurrage', '<=', now()->addDays(3))
            ->count();

        return response()->json([
            'total_containers' => $total,
            'in_transit'       => $inTransit,
            'at_terminal'      => $atTerminal,
            'alerts'           => $alerts,
            'containers_trend' => null,
            'transit_trend'    => null,
            'terminal_trend'   => null,
            'alerts_trend'     => null,
        ]);
    }

    /**
     * GET /api/v1/dashboards/default
     * The tenant's default dashboard + widgets (empty shape if none yet).
     * Registered before {uuid} so "default" is not treated as an id.
     */
    public function default(Request $request): JsonResponse
    {
        $dashboard = Dashboard::where('organization_id', tenancy()->tenant?->id)
            ->where('is_default', true)
            ->with('widgets')
            ->first();

        return response()->json([
            'uuid'    => $dashboard?->id,
            'name'    => $dashboard?->name ?? 'Overview',
            'widgets' => $dashboard?->widgets ?? [],
        ]);
    }

    /**
     * GET /api/v1/dashboards
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dashboard::where('organization_id', tenancy()->tenant?->id)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhereNull('user_id')
                  ->orWhere('is_shared', true)
                  ->orWhere('is_default', true);
            });

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, DashboardResource::class);
    }

    /**
     * GET /api/v1/dashboards/{uuid}
     */
    public function show(string $uuid, Request $request): JsonResponse
    {
        $dashboard = Dashboard::with('widgets')->find($uuid);

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        return $this->success(new DashboardResource($dashboard));
    }

    /**
     * POST /api/v1/dashboards
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'is_shared' => 'nullable|boolean',
            'layout'    => 'nullable|array',
        ]);

        $dashboard = Dashboard::create([
            'organization_id' => tenancy()->tenant?->id,
            'name'            => $request->input('name'),
            'user_id'         => $request->user()->id,
            'is_shared'       => $request->boolean('is_shared', false),
            'layout'          => $request->input('layout'),
        ]);

        return $this->created(new DashboardResource($dashboard), 'Dashboard created');
    }

    /**
     * PUT /api/v1/dashboards/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $dashboard = Dashboard::find($uuid);

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $request->validate([
            'name'      => 'sometimes|string|max:255',
            'is_shared' => 'nullable|boolean',
            'layout'    => 'nullable|array',
        ]);

        $dashboard->update($request->only(['name', 'is_shared', 'layout']));

        return $this->success(new DashboardResource($dashboard->fresh()), 'Dashboard updated');
    }

    /**
     * DELETE /api/v1/dashboards/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $dashboard = Dashboard::find($uuid);

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $dashboard->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/dashboards/{uuid}/widgets
     */
    public function addWidget(Request $request, string $uuid): JsonResponse
    {
        $dashboard = Dashboard::find($uuid);

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $request->validate([
            'widget_type' => 'required|string|max:100',
            'title'       => 'required|string|max:255',
            'config'      => 'nullable|array',
            'position'    => 'nullable|array',
        ]);

        $widget = $dashboard->widgets()->create([
            'widget_type' => $request->input('widget_type'),
            'title'       => $request->input('title'),
            'config'      => $request->input('config'),
            'position'    => $request->input('position'),
        ]);

        return $this->created($widget, 'Widget added');
    }

    /**
     * PUT /api/v1/dashboards/{uuid}/widgets/{widget_uuid}
     */
    public function updateWidget(Request $request, string $dashboardUuid, string $widgetUuid): JsonResponse
    {
        $dashboard = Dashboard::find($dashboardUuid);

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $widget = $dashboard->widgets()->find($widgetUuid);

        if (! $widget) {
            return $this->notFound('Widget not found');
        }

        $request->validate([
            'title'    => 'sometimes|string|max:255',
            'config'   => 'nullable|array',
            'position' => 'nullable|array',
        ]);

        $widget->update($request->only(['title', 'config', 'position']));

        return $this->success($widget->fresh(), 'Widget updated');
    }

    /**
     * DELETE /api/v1/dashboards/{uuid}/widgets/{widget_uuid}
     */
    public function removeWidget(string $dashboardUuid, string $widgetUuid): JsonResponse
    {
        $dashboard = Dashboard::find($dashboardUuid);

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $widget = $dashboard->widgets()->find($widgetUuid);

        if (! $widget) {
            return $this->notFound('Widget not found');
        }

        $widget->delete();

        return $this->noContent();
    }
}
