<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Enums\SummaryStatus;
use App\Enums\SummaryStyle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Summary extends Model
{
    /** @use HasFactory<\Database\Factories\SummaryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_url',
        'original_text',
        'title',
        'style',
        'status',
        'result_text',
        'error_message',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'style' => SummaryStyle::class,
            'status' => SummaryStatus::class,
            'metadata' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:6',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
