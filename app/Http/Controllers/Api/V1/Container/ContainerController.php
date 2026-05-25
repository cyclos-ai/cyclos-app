<?php

namespace App\Http\Controllers\Api\V1\Container;

use App\Http\Controllers\Controller;
use App\Http\Requests\Container\FilterContainerRequest;
use App\Http\Requests\Container\StoreContainerRequest;
use App\Http\Requests\Container\UpdateContainerRequest;
use App\Http\Resources\Container\ContainerResource;
use App\Models\Tenant\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContainerController extends Controller
{
    /**
     * GET /api/v1/containers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Container::query();

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        return $this->success(new ContainerResource($container));
    }

    /**
     * POST /api/v1/containers
     */
    public function store(StoreContainerRequest $request): JsonResponse
    {
        $container = Container::create($request->validated());

        return $this->created(new ContainerResource($container), 'Container created successfully');
    }

    /**
     * PUT /api/v1/containers/{uuid}
     */
    public function update(UpdateContainerRequest $request, string $uuid): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $container->update($request->validated());

        return $this->success(new ContainerResource($container->fresh()), 'Container updated successfully');
    }

    /**
     * DELETE /api/v1/containers/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $container->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/containers/filter
     */
    public function filter(FilterContainerRequest $request): JsonResponse
    {
        $query = Container::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by'),
            (int) $request->input('direction', 1)
        );

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/active
     */
    public function active(Request $request): JsonResponse
    {
        $query = Container::query()
            ->whereNotNull('vessel_uuid')
            ->where('status', '!=', 'delivered');

        $this->applySorting($query, $request->input('order_by', 'eta'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/not-tracking
     */
    public function notTracking(Request $request): JsonResponse
    {
        $query = Container::query()->where('is_tracking', false);

        $this->applySorting($query, $request->input('order_by', 'created_at'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/dropped-mbl
     */
    public function droppedMbl(Request $request): JsonResponse
    {
        $query = Container::query()->whereNull('mbl_uuid');

        $this->applySorting($query, $request->input('order_by', 'created_at'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/mbl/{mbl_number}
     */
    public function byMbl(string $mblNumber, Request $request): JsonResponse
    {
        $query = Container::query()->whereHas('mbl', function ($q) use ($mblNumber) {
            $q->where('mbl_number', $mblNumber);
        });

        $this->applySorting($query, $request->input('order_by', 'container_number'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/number/{container_number}
     */
    public function byContainerNumber(string $containerNumber): JsonResponse
    {
        $container = Container::where('container_number', strtoupper($containerNumber))->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        return $this->success(new ContainerResource($container));
    }

    /**
     * PATCH /api/v1/containers/{uuid}/priority
     */
    public function updatePriority(Request $request, string $uuid): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate(['priority' => 'required|in:low,normal,high,critical']);

        $container->update(['priority' => $request->input('priority')]);

        return $this->success(new ContainerResource($container->fresh()), 'Priority updated');
    }

    /**
     * PATCH /api/v1/containers/{uuid}/outgate
     */
    public function outgate(Request $request, string $uuid): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate([
            'outgate_date'     => 'required|date',
            'trucker_name'     => 'nullable|string|max:255',
            'license_plate'    => 'nullable|string|max:50',
        ]);

        $container->update([
            'outgate_date'  => $request->input('outgate_date'),
            'trucker_name'  => $request->input('trucker_name'),
            'license_plate' => $request->input('license_plate'),
            'status'        => 'outgated',
        ]);

        return $this->success(new ContainerResource($container->fresh()), 'Container outgated');
    }

    /**
     * PATCH /api/v1/containers/{uuid}/empty-return
     */
    public function emptyReturn(Request $request, string $uuid): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate([
            'empty_return_date'     => 'required|date',
            'empty_return_location' => 'nullable|string|max:255',
        ]);

        $container->update([
            'empty_return_date'     => $request->input('empty_return_date'),
            'empty_return_location' => $request->input('empty_return_location'),
            'status'                => 'empty_returned',
        ]);

        return $this->success(new ContainerResource($container->fresh()), 'Empty return recorded');
    }

    /**
     * GET /api/v1/containers/{uuid}/location-history
     */
    public function locationHistory(string $uuid, Request $request): JsonResponse
    {
        $container = Container::where('uuid', $uuid)->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $history = $container->locationHistory()
            ->orderBy('recorded_at', 'desc')
            ->paginate(
                min(50, max(1, (int) $request->input('page_size', 20))),
                ['*'],
                'page',
                max(1, (int) $request->input('page_num', 0) + 1)
            );

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'total'     => $history->total(),
                'page_num'  => $history->currentPage() - 1,
                'page_size' => $history->perPage(),
                'pages'     => $history->lastPage(),
            ],
        ]);
    }
}
