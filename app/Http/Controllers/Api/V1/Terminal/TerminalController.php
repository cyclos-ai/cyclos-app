<?php

namespace App\Http\Controllers\Api\V1\Terminal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Terminal\TerminalResource;
use App\Models\Tenant\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    /**
     * GET /api/v1/terminals/locodes
     */
    public function locodes(Request $request): JsonResponse
    {
        $query = Terminal::query();

        if ($request->input('country')) {
            $query->where('country_code', strtoupper($request->input('country')));
        }

        if ($request->input('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('locode', 'LIKE', '%' . $search . '%')
                  ->orWhere('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('city', 'LIKE', '%' . $search . '%');
            });
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'locode'),
            (int) $request->input('direction', 1)
        );

        return $this->paginateResource($query, $request, TerminalResource::class);
    }

    /**
     * GET /api/v1/terminals/firms/{firms_code}
     */
    public function byFirmsCode(string $firmsCode): JsonResponse
    {
        $terminal = Terminal::where('firms_code', strtoupper($firmsCode))->first();

        if (! $terminal) {
            return $this->notFound('Terminal not found for FIRMS code: ' . $firmsCode);
        }

        return $this->success(new TerminalResource($terminal));
    }
}
