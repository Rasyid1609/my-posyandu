<?php

namespace App\Services;

use App\Models\ReportingPeriod;

class ReportData
{
    public function __construct(private OperationalReport $report, private GroupedReport $groups) {}

    public function read(ReportingPeriod $period, ?int $version = null, bool $sensitive = false): array
    {
        $snapshot = $version ? $period->snapshots()->where('version', $version)->firstOrFail()
            : ($period->status === 'locked' ? $period->snapshots()->orderByDesc('version')->first() : null);
        $metrics = $snapshot ? $snapshot->metrics : $this->report->calculate($period);
        $metrics['_reports'] = $this->groups->visible($metrics['_reports'] ?? [], $sensitive);

        return [
            'metrics' => $metrics,
            'version' => $snapshot?->version,
            'status' => $snapshot ? 'locked' : $period->status,

            'source_revision' => $snapshot?->source_revision ?? $period->revision,

            'mapping_version' => $snapshot?->mapping_version ?? OperationalReport::VERSION,

            'source_hash' => $snapshot?->source_hash ?? hash_hmac(
                'sha256',
                json_encode([$period->id, $period->revision, $metrics], JSON_THROW_ON_ERROR),
                config('app.key')
            ),
        ];
    }
}
