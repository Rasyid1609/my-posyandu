<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'reporting_period_id',
    'created_by',
    'kind',
    'report_type',
    'status',
    'checksum',
    'source_name',
    'file_path',
    'mime_type',
    'draft_rows',
    'ocr_status',
    'failure_code',
    'revision',
    'committed_by',
    'committed_at',
])]
#[Hidden(['file_path'])]
class ImportBatch extends Model
{
    protected function casts(): array
    {
        return [
            'draft_rows' => 'encrypted:array',
            'revision' => 'integer',
            'committed_at' => 'datetime',
        ];
    }
}
