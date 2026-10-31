<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_scope',
    'comparison_period_id',
    'comparison_revision',
    'comparison_hash',
    'reporting_period_id',
    'requested_by',
    'deduplication_key',
    'source_revision',
    'source_hash',
    'provider',
    'model',
    'prompt_version',
    'schema_version',
    'status',
    'is_stale',
    'evidence',
    'result',
    'usage',
    'failure_code',
    'review_status',
    'reviewed_by',
    'review_note',
    'reviewed_result',
])]
class AnalysisRun extends Model
{
    public function comparisonPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class, 'comparison_period_id');
    }

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'result' => 'array',
            'usage' => 'array',
            'reviewed_result' => 'array',
            'is_stale' => 'boolean',
            'source_revision' => 'integer',
            'comparison_revision' => 'integer',
        ];
    }
}
