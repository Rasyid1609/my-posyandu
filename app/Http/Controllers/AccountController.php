<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountRequest;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private AuditTrail $audit) {}

    public function index(): Response
    {
        abort_unless(auth()->user()->hasRole('superadmin') && auth()->user()->can('accounts.manage'), 403);

        return Inertia::render('Settings/Accounts', [
            'users' => User::query()
                ->with(['roles:id,name', 'permissions:id,name'])
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'is_active']),
            'roles' => config('posyandu.roles'),
            'availablePermissions' => config('posyandu.permissions'),
            'rolePermissions' => config('posyandu.role_permissions'),
            'matrixVersion' => config('posyandu.permission_matrix_version'),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            $user->is_active = $data['is_active'];
            $user->save();
            $user->assignRole($data['role']);
            $user->syncPermissions($data['role'] === 'superadmin' ? [] : $data['permissions']);
            $this->audit->record('account.created', $user);
        });

        return back()->with('success', 'Akun dibuat. Sampaikan kredensial melalui jalur privat.');
    }

    public function update(AccountRequest $request, User $user): RedirectResponse
    {
        $passwordChanged = DB::transaction(function () use ($request, $user): bool {
            $superadmins = User::role('superadmin')->orderBy('id')->lockForUpdate()->get();
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            $data = $request->validated();
            if ($current->hasRole('superadmin') && $current->is_active && ($data['role'] !== 'superadmin' || ! $data['is_active']) && $superadmins->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Superadmin aktif terakhir tidak boleh dinonaktifkan atau diturunkan jabatannya.']);
            }
            $current->fill($request->safe()->only(['name', 'email']));
            if (! empty($data['password'])) {
                $current->password = $data['password'];
            }
            $passwordChanged = $current->isDirty('password');
            if ($passwordChanged) {
                $current->setRememberToken(Str::random(60));
            }
            $current->is_active = $data['is_active'];
            $current->save();
            $current->syncRoles([$data['role']]);
            $current->syncPermissions($data['role'] === 'superadmin' ? [] : $data['permissions']);
            $this->audit->record('account.updated', $current);

            if ($passwordChanged && config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table'))
                    ->where('user_id', $current->getKey())
                    ->delete();
            }

            return $passwordChanged;
        });

        if ($passwordChanged && $request->user()->is($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Inertia::clearHistory();

            return redirect()->route('login')->with('success', 'Kata sandi diperbarui. Silakan masuk kembali.');
        }

        return back()->with('success', 'Akun dan permission diperbarui.');
    }
}
