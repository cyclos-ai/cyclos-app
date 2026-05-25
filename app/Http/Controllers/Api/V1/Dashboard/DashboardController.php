<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\DashboardResource;
use App\Models\Tenant\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboards
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dashboard::where('user_id', $request->user()->id)
            ->orWhere('is_shared', true);

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
        $dashboard = Dashboard::where('uuid', $uuid)
            ->with('widgets')
            ->first();

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
            'name'      => $request->input('name'),
            'user_id'   => $request->user()->id,
            'is_shared' => $request->boolean('is_shared', false),
            'layout'    => $request->input('layout'),
        ]);

        return $this->created(new DashboardResource($dashboard), 'Dashboard created');
    }

    /**
     * PUT /api/v1/dashboards/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $dashboard = Dashboard::where('uuid', $uuid)->first();

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
        $dashboard = Dashboard::where('uuid', $uuid)->first();

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
        $dashboard = Dashboard::where('uuid', $uuid)->first();

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $request->validate([
            'widget_type'   => 'required|string|max:100',
            'title'         => 'required|string|max:255',
            'config'        => 'nullable|array',
            'position'      => 'nullable|array',
            'position.x'    => 'nullable|integer|min:0',
            'position.y'    => 'nullable|integer|min:0',
            'position.w'    => 'nullable|integer|min:1',
            'position.h'    => 'nullable|integer|min:1',
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
        $dashboard = Dashboard::where('uuid', $dashboardUuid)->first();

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $widget = $dashboard->widgets()->where('uuid', $widgetUuid)->first();

        if (! $widget) {
            return $this->notFound('Widget not found');
        }

        $request->validate([
            'title'      => 'sometimes|string|max:255',
            'config'     => 'nullable|array',
            'position'   => 'nullable|array',
        ]);

        $widget->update($request->only(['title', 'config', 'position']));

        return $this->success($widget->fresh(), 'Widget updated');
    }

    /**
     * DELETE /api/v1/dashboards/{uuid}/widgets/{widget_uuid}
     */
    public function removeWidget(string $dashboardUuid, string $widgetUuid): JsonResponse
    {
        $dashboard = Dashboard::where('uuid', $dashboardUuid)->first();

        if (! $dashboard) {
            return $this->notFound('Dashboard not found');
        }

        $widget = $dashboard->widgets()->where('uuid', $widgetUuid)->first();

        if (! $widget) {
            return $this->notFound('Widget not found');
        }

        $widget->delete();

        return $this->noContent();
    }
}
