<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait FilterableTrait
{
    /**
     * Apply Cyclos.ai-style filter operators to a query builder instance.
     */
    protected function applyFiltersTrait($query, array $filters)
    {
        foreach ($filters as $filter) {
            $field    = $filter['field']    ?? null;
            $operator = $filter['operator'] ?? 'eq';
            $value    = $filter['value']    ?? null;

            if (! $field) {
                continue;
            }

            switch ($operator) {
                case 'eq':
                    $query->where($field, '=', $value);
                    break;
                case 'neq':
                    $query->where($field, '!=', $value);
                    break;
                case 'gt':
                    $query->where($field, '>', $value);
                    break;
                case 'gte':
                    $query->where($field, '>=', $value);
                    break;
                case 'lt':
                    $query->where($field, '<', $value);
                    break;
                case 'lte':
                    $query->where($field, '<=', $value);
                    break;
                case 'contains':
                    $query->where($field, 'LIKE', '%' . $value . '%');
                    break;
                case 'not_contains':
                    $query->where($field, 'NOT LIKE', '%' . $value . '%');
                    break;
                case 'starts_with':
                    $query->where($field, 'LIKE', $value . '%');
                    break;
                case 'ends_with':
                    $query->where($field, 'LIKE', '%' . $value);
                    break;
                case 'is_null':
                    $query->whereNull($field);
                    break;
                case 'is_not_null':
                    $query->whereNotNull($field);
                    break;
                case 'in':
                    $query->whereIn($field, (array) $value);
                    break;
                case 'not_in':
                    $query->whereNotIn($field, (array) $value);
                    break;
            }
        }

        return $query;
    }

    /**
     * Apply sorting from request parameters (order_by, direction).
     */
    protected function applySortingTrait($query, Request $request)
    {
        $orderBy   = $request->input('order_by');
        $direction = (int) $request->input('direction', 1);

        if ($orderBy) {
            $dir = $direction === -1 ? 'desc' : 'asc';
            $query->orderBy($orderBy, $dir);
        }

        return $query;
    }

    /**
     * Build a paginated response array.
     */
    protected function buildPaginatedResponse($query, Request $request, string $resourceClass = null): array
    {
        $pageNum  = max(0, (int) $request->input('page_num', 0));
        $pageSize = min(50, max(1, (int) $request->input('page_size', 20)));

        $total = $query->count();
        $items = $query->skip($pageNum * $pageSize)->take($pageSize)->get();

        $data = $resourceClass ? $resourceClass::collection($items) : $items;

        return [
            'data' => $data,
            'meta' => [
                'total'     => $total,
                'page_num'  => $pageNum,
                'page_size' => $pageSize,
                'pages'     => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ];
    }
}
