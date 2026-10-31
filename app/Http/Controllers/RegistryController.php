<?php

namespace App\Http\Controllers;

use App\Models\HomeVisit;
use App\Models\Household;
use App\Models\MaternalEpisode;
use App\Models\Resident;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\IdentityDetails;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegistryController extends Controller
{
    public function index(Request $request, IdentityDetails $identities, AuditTrail $audit): Response
    {
        Gate::authorize('residents.view');
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $reveal = $request->user()->can('identity.reveal');
        $households = Household::query()->when(
            $data['q'] ?? null,
            fn ($q, $text) => $q->whereRaw('LOWER(head_of_household_name) LIKE ?', ['%'.mb_strtolower($text).'%'])
        )
            ->withCount('residents')->orderBy('head_of_household_name')->orderBy('id')->paginate(20)->withQueryString();
        $households->through(function (Household $household) use ($identities, $reveal, $audit) {
            if ($reveal && $household->family_card_ciphertext) {
                $audit->record('household.identity_revealed', $household);
            }

            return [
                ...$household->toArray(),
                'family_card_display' => $identities->display($household->family_card_ciphertext, $reveal),
                'detail_url' => route('households.show', $household),
            ];
        });

        return Inertia::render('Residents/Households', [
            'households' => $households,
            'search' => $data['q'] ?? '',
        ]);
    }

    public function show(Request $request, Household $household, IdentityDetails $identities, AuditTrail $audit): Response
    {
        Gate::authorize('residents.view');
        $reveal = $request->user()->can('identity.reveal');
        $household->loadCount('residents');
        $audit->record('household.viewed', $household);
        if ($reveal && $household->family_card_ciphertext) {
            $audit->record('household.identity_revealed', $household);
        }

        $members = $household->residents()->orderBy('full_name')->orderBy('id')
            ->paginate(15, [
                'id',
                'registration_number',
                'full_name',
                'gender',
                'birth_date',
                'is_birth_date_estimated',
                'family_relationship',
                'residency_status',
            ], 'members_page')
            ->withQueryString()->fragment('anggota-keluarga')
            ->through(fn (Resident $resident): array => [...$resident->toArray(), 'detail_url' => route('residents.edit', $resident)]);
        $homeVisits = null;
        if ($request->user()->can('visits.view')) {
            $audit->record('household.home_visits_viewed', $household);
            $homeVisits = $household->homeVisits()
                ->with([
                    'reportingPeriod:id,month,year,status',
                    'residents:id,full_name,deleted_at',
                    'staff:id,name',
                ])
                ->orderByDesc('visited_on')->orderByDesc('id')->paginate(15, ['*'], 'visits_page')
                ->withQueryString()->fragment('riwayat-kunjungan')
                ->through(fn (HomeVisit $visit): array => [
                    ...$visit->only(['id', 'visited_on', 'reason', 'follow_up', 'record_status']),
                    'period' => $visit->reportingPeriod->only(['id', 'month', 'year', 'status']),
                    'staff' => $visit->staff->map(fn (User $staff): array => $staff->only(['id', 'name']))->all(),
                    'residents' => $visit->residents->map(fn (Resident $resident): array => [
                        ...$resident->only(['id', 'full_name']),
                        'detail_url' => $resident->trashed() ? null : route('residents.edit', $resident),
                    ])->all(),
                    'period_url' => route(
                        'home-visits.index',
                        ['period' => $visit->reporting_period_id, 'household_id' => $household->id]
                    ),
                ]);
        }

        return Inertia::render('Residents/HouseholdDetail', [
            'household' => [...$household->only([
                'id',
                'head_of_household_name',
                'address',
                'rt',
                'rw',
                'is_active',
                'residents_count',
            ]),
                'family_card_display' => $identities->display($household->family_card_ciphertext, $reveal)],
            'members' => $members,
            'homeVisits' => $homeVisits,
            'links' => [
                'households' => route('households.index'),
                'residents' => route('residents.index'),
            ],
        ]);
    }

    public function saveHousehold(Request $request, AuditTrail $audit, IdentityDetails $identities, ?Household $household = null): RedirectResponse
    {
        Gate::authorize('residents.manage');
        Gate::authorize('residents.view');
        $data = $request->validate([
            'head_of_household_name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],

            'family_card_number' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{16}$/D'],

            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],

            'is_active' => ['required', 'boolean'],
            'revision' => [$household ? 'required' : 'nullable', 'integer', 'min:1'],
        ], ['family_card_number.regex' => 'Nomor KK harus terdiri dari 16 digit, atau dikosongkan.']);
        DB::transaction(function () use ($household, $data, $audit, $identities) {
            if ($household) {
                $household = Household::query()->lockForUpdate()->findOrFail($household->id);
                abort_unless($household->revision === $data['revision'], 409, 'Data keluarga telah berubah.');
            }
            $household ??= new Household;
            $household->fill(Arr::except($data, ['family_card_number']));
            $identities->household($household, $data);
            $household->revision = ($data['revision'] ?? 0) + 1;
            try {
                $household->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages(['family_card_number' => 'Nomor KK sudah tercatat. Periksa data keluarga.']);
            }
            $audit->record('household.saved', $household);
        });

        return back()->with('success', 'Keluarga disimpan. Pengarsipan tidak menghapus riwayat.');
    }

    public function episode(Request $request, Resident $resident, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('residents.manage');
        Gate::authorize('residents.view');
        $data = $request->validate([
            'episode_type' => ['required', Rule::in(['pregnant', 'postpartum', 'breastfeeding'])],

            'start_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            ],

            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],

            'source' => ['required', 'string', 'min:5', 'max:500'],
            'revision' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($resident, $data, $audit) {
            $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
            abort_unless($resident->revision === $data['revision'], 409, 'Data warga telah berubah.');
            if ($resident->gender !== 'P') {
                throw ValidationException::withMessages(['episode_type' => 'Episode maternal hanya untuk warga perempuan.']);
            }
            $overlap = MaternalEpisode::where('resident_id', $resident->id)->where('episode_type', $data['episode_type'])
                ->where('start_date', '<=', $data['end_date'] ?? '9999-12-31')
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $data['start_date']))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'Episode jenis yang sama tumpang tindih dengan riwayat.']);
            }
            $episode = MaternalEpisode::create([
                ...$data,
                'resident_id' => $resident->id,
                'created_by' => auth()->id(),
                'is_active' => true,
            ]);
            $resident->increment('revision');
            $audit->record('maternal_episode.created', $episode);
        });

        return back()->with(
            'success',
            'Episode ditambahkan. Snapshot sasaran yang sudah dibuat tetap dipertahankan.'
        );
    }

    public function closeEpisode(Request $request, Resident $resident, MaternalEpisode $episode, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('residents.manage');
        Gate::authorize('residents.view');
        abort_unless($episode->resident_id === $resident->id, 404);
        $data = $request->validate([
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$episode->start_date->toDateString(),
                'before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            ],
            'revision' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($resident, $episode, $data, $audit) {
            $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
            abort_unless($resident->revision === $data['revision'], 409);
            if ($episode->fresh()->end_date) {
                throw ValidationException::withMessages(['end_date' => 'Episode sudah ditutup.']);
            }
            $episode->update(['end_date' => $data['end_date'], 'is_active' => false]);
            $resident->increment('revision');
            $audit->record('maternal_episode.closed', $episode);
        });

        return back()->with('success', 'Episode ditutup, riwayat tetap tersedia.');
    }
}
