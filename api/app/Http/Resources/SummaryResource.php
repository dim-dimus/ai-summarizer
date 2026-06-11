<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Summary */
class SummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type->value,
            'source_url' => $this->source_url,
            'title' => $this->title,
            'style' => $this->style->value,
            'status' => $this->status->value,
            'result_text' => $this->result_text,
            'error_message' => $this->error_message,
            'model' => $this->model,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'cost_usd' => $this->cost_usd !== null ? (float) $this->cost_usd : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
