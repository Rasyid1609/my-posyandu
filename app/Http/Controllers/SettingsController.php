<?php

namespace App\Http\Controllers;

use App\Models\AgeRuleVersion;
use App\Services\AuditTrail;
use App\Services\ClinicalFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('accounts.manage');

        return Inertia::render('Settings/Rules', [
            'versions' => AgeRuleVersion::query()->latest('id')->get(),
            'labels' => ClinicalFields::AGE_LABELS,
        ]);
    }

    public function store(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('accounts.manage');
        $data = $request->validate([
            'version' => ['required', 'string', 'max:60', 'unique:age_rule_versions,version'],

            'source' => ['required', 'string', 'min:10', 'max:1000'],

            'is_provisional' => ['required', 'boolean'],
            'boundaries' => ['required', 'array', 'size:6'],

            'boundaries.*' => ['required', 'integer', 'min:1', 'max:2400'],
        ]);
        $previous = 0;
        foreach ($data['boundaries'] as $boundary) {
            if ($boundary <= $previous) {
                return back()->withErrors(['boundaries' => 'Batas bulan harus berurutan naik, tanpa rentang kosong atau tumpang tindih.']);
            }
            $previous = $boundary;
        }
        DB::transaction(function () use ($data, $audit) {
            $rule = AgeRuleVersion::create([...$data, 'created_by' => auth()->id()]);
            $audit->record('age_rule.created', $rule);
        });

        return back()->with(
            'success',
            'Versi aturan disimpan untuk periode baru. Periode lama tetap memakai versinya.'
        );
    }
}
