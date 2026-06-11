<?php

namespace App\Http\Controllers;

use App\Enums\SourceType;
use App\Enums\SummaryStatus;
use App\Http\Requests\StoreSummaryRequest;
use App\Http\Resources\SummaryResource;
use App\Jobs\ProcessSummaryJob;
use App\Models\Summary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    /**
     * List the authenticated user's summaries (paginated, newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:50'],
            'status' => ['sometimes', \Illuminate\Validation\Rule::enum(SummaryStatus::class)],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $paginator = $request->user()->summaries()
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);

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

    /**
     * Create a summary (async). Inserts a pending row and returns 202.
     * The job is dispatched in Phase 4.
     */
    public function store(StoreSummaryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $sourceType = SourceType::from($data['source_type']);

        $summary = $request->user()->summaries()->create([
            'source_type' => $sourceType,
            'source_url' => $sourceType === SourceType::Url ? $data['url'] : null,
            'original_text' => $sourceType === SourceType::Text ? $data['text'] : null,
            'style' => $data['style'],
            'status' => SummaryStatus::Pending,
        ]);

        ProcessSummaryJob::dispatch($summary->id);

        return (new SummaryResource($summary))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Get one summary owned by the user (poll this for status/result).
     */
    public function show(Request $request, int $id): SummaryResource
    {
        $summary = $request->user()->summaries()->findOrFail($id);

        return new SummaryResource($summary);
    }

    /**
     * Delete one summary owned by the user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $summary = $request->user()->summaries()->findOrFail($id);
        $summary->delete();

        return response()->json(null, 204);
    }
}
