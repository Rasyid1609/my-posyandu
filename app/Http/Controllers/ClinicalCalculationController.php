<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClinicalCalculationRequest;
use App\Models\ReportingPeriod;
use App\Models\TargetMembership;
use App\Services\ClinicalCalculations;
use Illuminate\Http\JsonResponse;

class ClinicalCalculationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ClinicalCalculationRequest $request, ReportingPeriod $period, TargetMembership $target, ClinicalCalculations $calculations): JsonResponse
    {
        abort_unless($target->reporting_period_id === $period->id, 404);
        $data = $request->validated();
        $visitId = $data['visit_id'] ?? null;
        if ($visitId !== null) {
            abort_unless($target->visits()->whereKey($visitId)->where('record_status', 'draft')->exists(), 404);
        }

        return response()->json($calculations->calculate($target, $data, $visitId))->header(
            'Cache-Control',
            'private, no-store'
        );
    }
}
