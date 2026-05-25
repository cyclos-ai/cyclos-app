<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    protected function success($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $response = ['message' => $message];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    protected function created($data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function error(string $message, int $status = 400, $errors = null): JsonResponse
    {
        $response = ['message' => $message];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function paginate($query, Request $request): JsonResponse
    {
        $pageNum  = max(0, (int) $request->input('page_num', 0));
        $pageSize = min(50, max(1, (int) $request->input('page_size', 20)));

        $total = $query->count();
        $items = $query->skip($pageNum * $pageSize)->take($pageSize)->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total'     => $total,
                'page_num'  => $pageNum,
                'page_size' => $pageSize,
                'pages'     => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ]);
    }

    protected function paginateResource($query, Request $request, string $resourceClass): JsonResponse
    {
        $pageNum  = max(0, (int) $request->input('page_num', 0));
        $pageSize = min(50, max(1, (int) $request->input('page_size', 20)));

        $total = $query->count();
        $items = $query->skip($pageNum * $pageSize)->take($pageSize)->get();

        return response()->json([
            'data' => $resourceClass::collection($items),
            'meta' => [
                'total'     => $total,
                'page_num'  => $pageNum,
                'page_size' => $pageSize,
                'pages'     => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ]);
    }

    protected function applyFilters($query, array $filters)
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

    protected function applySorting($query, ?string $orderBy, int $direction = 1)
    {
        if ($orderBy) {
            $dir = $direction === -1 ? 'desc' : 'asc';
            $query->orderBy($orderBy, $dir);
        }

        return $query;
    }
}
