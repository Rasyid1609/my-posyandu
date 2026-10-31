<?php

namespace App\Services;

use App\Jobs\GenerateAnalysis;
use App\Models\AnalysisRun;
use App\Models\ReportingPeriod;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AggregateAnalysis
{
    public const PROMPT = 'grouped-narrative-v2';

    public function __construct(private ReportData $reports, private AuditTrail $audit, private AnalysisEvidence $evidenceSource) {}

    public function evidence(array $metrics): array
    {
        return $this->evidenceSource->rows($metrics, 'operational');
    }

    public function request(ReportingPeriod $period, User $user, bool $useAi, string $scope = 'operational', ?ReportingPeriod $compare = null): AnalysisRun
    {
        if (! array_key_exists($scope, AnalysisEvidence::SCOPES) || $compare?->id === $period->id) {
            throw ValidationException::withMessages(['analysis' => 'Lingkup/periode pembanding tidak valid.']);
        }

        return DB::transaction(function () use ($period, $user, $useAi, $scope, $compare) {
            $locks = ReportingPeriod::whereIn('id', array_filter([$period->id, $compare?->id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $period = $locks[$period->id];
            $compare = $compare ? $locks[$compare->id] : null;
            $dataset = $this->reports->read($period);
            $comparison = $compare ? $this->reports->read($compare) : null;
            if ($comparison && ! $this->evidenceSource->compatible($dataset['metrics'], $comparison['metrics'], $scope)) {
                throw ValidationException::withMessages(['compare_id' => 'Definisi usia, mapping atau sumber tidak sebanding.']);
            }
            $primaryEvidence = $this->evidenceSource->rows($dataset['metrics'], $scope);
            $comparisonEvidence = $comparison ? array_map(fn ($metric) => [
                ...$metric,
                'id' => 'comparison.'.$metric['id'],
                'label' => 'Pembanding — '.$metric['label'],
            ], $this->evidenceSource->rows($comparison['metrics'], $scope)) : [];
            $evidence = [...$primaryEvidence, ...$comparisonEvidence];
            if ($useAi) {
                if (! config('analysis.enabled') || ! config('analysis.processing_approved') || ! config('analysis.api_key') || ! config('analysis.model')) {
                    throw ValidationException::withMessages(['analysis' => 'Provider AI belum diaktifkan atau dikonfigurasi. Ringkasan lokal tetap dapat digunakan.']);
                }
                $minimum = config('analysis.minimum_cell_size');
                if ($this->evidenceSource->population($dataset['metrics'], $scope) < $minimum
                    || ($comparison && $this->evidenceSource->population($comparison['metrics'], $scope) < $minimum)
                    || collect($evidence)->contains(fn ($metric) => $metric['value'] > 0 && $metric['value'] < $minimum)) {
                    throw ValidationException::withMessages(['analysis' => 'Agregat terlalu kecil untuk dikirim ke provider. Gunakan ringkasan lokal.']);
                }
                if (count($evidence) > 250 || strlen(json_encode($evidence, JSON_THROW_ON_ERROR)) > 60000) {
                    throw ValidationException::withMessages(['analysis' => 'Dataset terlalu besar; pilih satu kelompok tanpa pembanding.']);
                }
            }
            $provider = $useAi ? 'openai' : 'local_template';
            $model = $useAi ? config('analysis.model') : 'deterministic-v2';
            $key = hash('sha256', json_encode([
                $period->id,
                $dataset['source_hash'],
                $scope,
                $compare?->id,
                $comparison['source_hash'] ?? null,
                $provider,
                $model,
                self::PROMPT,
            ], JSON_THROW_ON_ERROR));
            $existing = AnalysisRun::where('deduplication_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            if ($useAi && AnalysisRun::where('requested_by', $user->id)
                ->where('provider', 'openai')
                ->where('created_at', '>=', now()->startOfDay())
                ->count() >= config('analysis.daily_limit')) {
                throw ValidationException::withMessages(['analysis' => 'Batas permintaan AI harian tercapai.']);
            }
            $result = $useAi ? null : $this->template($dataset['metrics']);
            if ($result) {
                $result['findings'] = $primaryEvidence ? [[
                    'title' => 'Bukti periode terpilih',
                    'description' => 'Angka berasal dari dataset backend sesuai lingkup yang dipilih.',
                    'evidence_ids' => array_slice(array_column($primaryEvidence, 'id'), 0, 3),
                    'kind' => 'fact',
                ]] : [];
                if ($comparisonEvidence) {
                    $result['findings'][] = [
                        'title' => 'Periode pembanding',
                        'description' => 'Bandingkan bukti angka; perubahan tidak membuktikan penyebab atau diagnosis.',
                        'evidence_ids' => array_slice(array_column($comparisonEvidence, 'id'), 0, 3),
                        'kind' => 'fact',
                    ];
                }
                $result['limitations'][] = 'Indikator kosong tidak diganti nol; sumber historis dan transaksi tetap terpisah.';
            }
            $run = AnalysisRun::create([
                'reporting_period_id' => $period->id,
                'requested_by' => $user->id,
                'deduplication_key' => $key,

                'source_revision' => $period->revision,
                'source_hash' => $dataset['source_hash'],
                'provider' => $provider,
                'model' => $model,

                'report_scope' => $scope,
                'comparison_period_id' => $compare?->id,
                'comparison_revision' => $compare?->revision,
                'comparison_hash' => $comparison['source_hash'] ?? null,

                'prompt_version' => self::PROMPT,
                'schema_version' => 'narrative-v1',
                'status' => $useAi ? 'queued' : 'succeeded',
                'evidence' => $evidence,
                'result' => $result,
            ]);
            $this->audit->record('analysis.requested', $run);
            if ($useAi) {
                GenerateAnalysis::dispatch($run->id)->afterCommit();
            }

            return $run;
        }, 3);
    }

    public function comparisonCurrent(AnalysisRun $run): bool
    {
        if ($run->relationLoaded('comparisonPeriod')) {
            return ! $run->comparison_period_id || $run->comparisonPeriod?->revision === $run->comparison_revision;
        }

        return ! $run->comparison_period_id || ReportingPeriod::whereKey($run->comparison_period_id)->value('revision') === $run->comparison_revision;
    }

    public function template(array $metrics): array
    {
        return [
            'summary' => 'Ringkasan operasional lokal dari sumber periode terpilih.',
            'findings' => [[
                'title' => 'Kehadiran dan kegiatan',
                'description' => 'Periksa angka sasaran, kehadiran, dan kunjungan pada bukti terlampir.',
                'evidence_ids' => ['targets', 'present', 'finalized_visits'],
                'kind' => 'fact',
            ]],
            'data_quality_issues' => ($metrics['unconfirmed'] || $metrics['draft_visits']) ? ['Masih ada kehadiran belum dikonfirmasi atau kunjungan draft.'] : [],
            'limitations' => [
                'Ringkasan lokal berbasis template, bukan hasil AI.',
                'Tidak menyimpulkan kondisi kesehatan atau sebab ketidakhadiran.',
            ],
            'suggested_actions' => [[
                'action' => 'Tinjau kelengkapan data bersama pengurus.',
                'reason' => 'Pastikan sumber rekap dapat ditelusuri.',
                'priority' => 'normal',
                'reviewer' => 'Pengurus',
            ]],
            'conclusion' => 'Gunakan angka sumber untuk review administratif; interpretasi lebih lanjut memerlukan peninjauan petugas.',
        ];
    }

    public function validateResult(array $result, array $evidence): array
    {
        $validated = Validator::make($result, [
            'summary' => ['required', 'string', 'max:2000'],
            'findings' => ['present', 'array', 'max:12'],

            'findings.*' => ['array:title,description,evidence_ids,kind'],
            'findings.*.title' => ['required', 'string', 'max:200'],

            'findings.*.description' => ['required', 'string', 'max:2000'],
            'findings.*.evidence_ids' => ['required', 'array', 'min:1', 'max:8'],

            'findings.*.evidence_ids.*' => ['required', Rule::in(array_column($evidence, 'id'))],

            'findings.*.kind' => ['required', Rule::in(['fact', 'interpretation', 'proposal'])],

            'data_quality_issues' => ['present', 'array', 'max:12'],
            'data_quality_issues.*' => ['string', 'max:1000'],

            'limitations' => ['required', 'array', 'max:12'],
            'limitations.*' => ['string', 'max:1000'],

            'suggested_actions' => ['present', 'array', 'max:12'],
            'suggested_actions.*' => ['array:action,reason,priority,reviewer'],

            'suggested_actions.*.action' => ['required', 'string', 'max:1000'],
            'suggested_actions.*.reason' => ['required', 'string', 'max:1000'],

            'suggested_actions.*.priority' => ['required', Rule::in(['normal', 'high'])],
            'suggested_actions.*.reviewer' => ['required', 'string', 'max:150'],

            'conclusion' => ['required', 'string', 'max:2000'],
        ])->validate();
        foreach (Arr::dot($validated) as $key => $value) {
            if (is_string($value) && ! str_contains($key, 'evidence_ids') && preg_match('/\\p{N}/u', $value)) {
                throw ValidationException::withMessages(['analysis' => 'Angka narasi harus dirender dari bukti backend, bukan teks provider.']);
            }
        }

        return Arr::only($validated, [
            'summary',
            'findings',
            'data_quality_issues',
            'limitations',
            'suggested_actions',
            'conclusion',
        ]);
    }

    public function schema(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];
        $object = fn (array $props) => [
            'type' => 'object',
            'properties' => $props,
            'required' => array_keys($props),
            'additionalProperties' => false,
        ];

        return $object([
            'summary' => $string,
            'findings' => [
                'type' => 'array',
                'items' => $object([
                    'title' => $string,
                    'description' => $string,
                    'evidence_ids' => $strings,
                    'kind' => ['type' => 'string', 'enum' => ['fact', 'interpretation', 'proposal']],
                ]),
            ],

            'data_quality_issues' => $strings,
            'limitations' => $strings,

            'suggested_actions' => [
                'type' => 'array',
                'items' => $object([
                    'action' => $string,
                    'reason' => $string,
                    'priority' => ['type' => 'string', 'enum' => ['normal', 'high']],
                    'reviewer' => $string,
                ]),
            ],
            'conclusion' => $string,
        ]);
    }
}
