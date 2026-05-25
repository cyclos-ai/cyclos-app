<?php

namespace App\Http\Controllers\Api\V1\CustomColumn;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CustomColumn;
use App\Models\Tenant\CustomColumnValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomColumnController extends Controller
{
    /**
     * GET /api/v1/custom-columns
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomColumn::query();

        if ($request->input('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'display_order'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/custom-columns/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $column = CustomColumn::where('uuid', $uuid)->first();

        if (! $column) {
            return $this->notFound('Custom column not found');
        }

        return $this->success($column);
    }

    /**
     * POST /api/v1/custom-columns
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'label'         => 'required|string|max:255',
            'entity_type'   => 'required|string|max:100',
            'field_type'    => 'required|in:text,number,date,boolean,select,multi_select',
            'options'       => 'nullable|array',
            'is_required'   => 'nullable|boolean',
            'is_filterable' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $column = CustomColumn::create($request->validated());

        return $this->created($column, 'Custom column created');
    }

    /**
     * PUT /api/v1/custom-columns/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $column = CustomColumn::where('uuid', $uuid)->first();

        if (! $column) {
            return $this->notFound('Custom column not found');
        }

        $request->validate([
            'label'         => 'sometimes|string|max:255',
            'field_type'    => 'sometimes|in:text,number,date,boolean,select,multi_select',
            'options'       => 'nullable|array',
            'is_required'   => 'nullable|boolean',
            'is_filterable' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $column->update($request->only(['label', 'field_type', 'options', 'is_required', 'is_filterable', 'display_order']));

        return $this->success($column->fresh(), 'Custom column updated');
    }

    /**
     * DELETE /api/v1/custom-columns/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $column = CustomColumn::where('uuid', $uuid)->first();

        if (! $column) {
            return $this->notFound('Custom column not found');
        }

        $column->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/custom-columns/{entity_type}/{entity_id}/values
     */
    public function values(string $entityType, string $entityId, Request $request): JsonResponse
    {
        $values = CustomColumnValue::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('column')
            ->get();

        return $this->success($values);
    }

    /**
     * POST /api/v1/custom-columns/{entity_type}/{entity_id}/values
     */
    public function storeValue(Request $request, string $entityType, string $entityId): JsonResponse
    {
        $request->validate([
            'column_uuid' => 'required|uuid',
            'value'       => 'nullable',
        ]);

        $value = CustomColumnValue::updateOrCreate(
            [
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'column_uuid'  => $request->input('column_uuid'),
            ],
            ['value' => $request->input('value')]
        );

        return $this->created($value, 'Custom column value saved');
    }
}
