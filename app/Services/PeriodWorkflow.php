<?php

namespace App\Services;

use App\Models\AnalysisRun;
use App\Models\ReportingPeriod;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodWorkflow
{
    public function change(ReportingPeriod $period, int $revision, Closure $operation, array $allowedStatuses = ['open', 'returned']): mixed
    {
        return DB::transaction(function () use ($period, $revision, $operation, $allowedStatuses) {
            $current = ReportingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($current->revision !== $revision) {
                abort(409, 'Data telah berubah. Muat ulang halaman sebelum menyimpan.');
            }
            if (! in_array($current->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages(['period' => 'Periode diajukan atau terkunci. Buka kembali melalui review sebelum mengubah sumber.']);
            }
            $result = $operation($current);
            $current->revision++;
            $current->save();
            AnalysisRun::where(fn ($query) => $query->where('reporting_period_id', $current->id)->orWhere('comparison_period_id', $current->id))->update(['is_stale' => true]);

            return $result;
        }, 3);
    }
}
