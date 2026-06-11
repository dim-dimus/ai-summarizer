<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SummaryResource;
use App\Http\Resources\UserResource;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /** All summaries across users (paginated, newest first). */
    public function summaries(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 50);

        $paginator = Summary::query()->latest()->paginate($perPage);

        return response()->json([
            'data' => SummaryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** All users (bare array per the OpenAPI contract). */
    public function users(): JsonResponse
    {
        return response()->json(
            UserResource::collection(User::orderBy('id')->get())
        );
    }

    /** Aggregate token + cost usage. */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_summaries' => Summary::count(),
            'total_input_tokens' => (int) Summary::sum('input_tokens'),
            'total_output_tokens' => (int) Summary::sum('output_tokens'),
            'total_cost_usd' => round((float) Summary::sum('cost_usd'), 6),
            'by_status' => Summary::query()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ]);
    }
}
