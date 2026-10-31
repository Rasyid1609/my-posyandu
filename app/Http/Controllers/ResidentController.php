<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentRequest;
use App\Models\AgeRuleVersion;
use App\Models\Household;
use App\Models\MaternalEpisode;
use App\Models\Resident;
use App\Models\Visit;
use App\Services\AuditTrail;
use App\Services\ClinicalFields;
use App\Services\IdentityDetails;
use App\Services\ResidentRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResidentController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()->can('aggregates.view') && ! $request->user()->can('viewAny', Resident::class)) {
            return Inertia::render('AccessPending');
        }
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'moved', 'deceased', 'inactive'])],
            'category' => [
                'nullable',
                Rule::in([...ClinicalFields::AGE_CODES, 'pregnant', 'postpartum', 'breastfeeding']),
            ],
        ]);
        if (! $request->user()->can('residents.view') && ! empty($filters['q'])) {
            abort(403);
        }
        $query = Resident::query()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->whereRaw('LOWER(full_name) LIKE ?', ['%'.Str::lower($q).'%']))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('residency_status', $status));
        $rule = AgeRuleVersion::latest('id')->firstOrFail();
        if (! empty($filters['category'])) {
            $this->filterCategory($query, $filters['category'], $rule->boundaries);
        }
        $distribution = [];
        foreach (ClinicalFields::AGE_LABELS as $code => $label) {
            $ageQuery = clone $query;
            $this->filterCategory($ageQuery, $code, $rule->boundaries);
            $distribution[] = ['name' => $label, 'value' => $ageQuery->count()];
        }
        $stats = $request->user()->can('aggregates.view') ? [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('residency_status', 'active')->count(),
            'missing_birth_date' => (clone $query)->whereNull('birth_date')->count(),
            'estimated_birth_date' => (clone $query)->where('is_birth_date_estimated', true)->count(),
            'distribution' => $distribution,
        ] : null;

        return Inertia::render('Residents/Index', [
            'residents' => $request->user()->can('viewAny', Resident::class) ? $query
                ->select([
                    'id',
                    'registration_number',
                    'full_name',
                    'gender',
                    'birth_date',
                    'is_birth_date_estimated',
                    'residency_status',
                    'revision',
                ])
                ->orderBy('full_name')->orderBy('id')->paginate(15)->withQueryString() : null,
            'stats' => $stats,
            'filters' => $filters,
            'categoryLabels' => ClinicalFields::AGE_LABELS + [
                'pregnant' => 'Ibu hamil',
                'postpartum' => 'Ibu nifas',
                'breastfeeding' => 'Ibu menyusui',
            ],
            'ageRule' => $rule->only(['version', 'is_provisional', 'boundaries']),
        ]);
    }

    private function filterCategory(Builder $query, string $code, array $boundaries): void
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        if (in_array($code, ['pregnant', 'postpartum', 'breastfeeding'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('maternal_episodes')
                ->whereColumn('maternal_episodes.resident_id', 'residents.id')
                ->where('episode_type', $code)->whereDate('start_date', '<=', $today)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today)));
        } elseif ($code === 'general') {
            $query->where(fn ($q) => $q->whereNull('birth_date')
                ->orWhere('is_birth_date_estimated', true)
                ->orWhereDate('birth_date', '>', $today));
        } else {
            $index = array_search($code, ClinicalFields::AGE_CODES, true);
            $query->where('is_birth_date_estimated', false)
                ->whereNotNull('birth_date')
                ->whereDate('birth_date', '<=', $today->subMonthsNoOverflow($index ? $boundaries[$index - 1] : 0));
            if (isset($boundaries[$index])) {
                $query->whereDate('birth_date', '>', $today->subMonthsNoOverflow($boundaries[$index]));
            }
        }
    }

    public function create(): Response
    {
        Gate::authorize('create', Resident::class);

        return Inertia::render('Residents/Form', [
            'resident' => null,
            'identityDetails' => null,
            'history' => [],
            'visits' => [],
            'episodes' => [],
            ...$this->lookups(),
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function store(ResidentRequest $request, ResidentRegistry $registry): RedirectResponse
    {
        $registry->save($request->validated());

        return redirect()->route('residents.index')->with('success', 'Warga berhasil ditambahkan.');
    }

    public function edit(Resident $resident, Request $request, AuditTrail $audit, IdentityDetails $identities): Response
    {
        Gate::authorize('view', $resident);
        $audit->record('resident.viewed', $resident);
        $reveal = $request->user()->can('identity.reveal');
        if ($reveal) {
            $audit->record('resident.identity_revealed', $resident);
        }
        $family = $resident->household_id ? Household::find($resident->household_id) : null;

        return Inertia::render('Residents/Form', [
            'resident' => $resident->only([
                'id',
                'registration_number',
                'full_name',
                'gender',
                'birth_date',
                'is_birth_date_estimated',
                'address',
                'rt',
                'rw',
                'residency_status',
                'revision',
                'household_id',
                'guardian_id',
                'family_relationship',
            ]),
            'identityDetails' => [
                'national_id_number' => $identities->display($resident->national_id_ciphertext, $reveal),
                'phone_number' => $identities->display($resident->phone_ciphertext, $reveal),
                'family_card_number' => $identities->display($family?->family_card_ciphertext, $reveal),
            ],
            ...$this->lookups($resident),
            'episodes' => MaternalEpisode::where('resident_id', $resident->id)->latest('id')->get(),
            'history' => DB::table('resident_status_histories')
                ->where('resident_id', $resident->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get(['status', 'effective_date', 'reason']),
            'visits' => $request->user()->can('visits.view') ? Visit::query()
                ->where('resident_id', $resident->id)
                ->with('examination')
                ->orderByDesc('visited_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get() : [],
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    private function lookups(?Resident $resident = null): array
    {
        return [
            'households' => Household::query()->orderBy('head_of_household_name')->get(['id', 'head_of_household_name']),
            'guardians' => Resident::query()
                ->whereNotNull('household_id')
                ->when($resident, fn ($q) => $q->whereKeyNot($resident->id))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'household_id']),
        ];
    }

    public function update(ResidentRequest $request, Resident $resident, ResidentRegistry $registry): RedirectResponse
    {
        $registry->save($request->validated(), $resident);

        return redirect()->route('residents.index')->with('success', 'Data warga dan riwayat tersimpan.');
    }
}
