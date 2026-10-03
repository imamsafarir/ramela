<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as RoleModel;

class UserController extends Controller
{
    private function ensureSuperAdmin(Request $request): void
    {
        if (! $request->user()->hasRole(Role::SuperAdmin->value)) {
            abort(403, 'Hanya Super Admin yang diizinkan melakukan tindakan ini.');
        }
    }

    public function index(Request $request): Response
    {
        $currentUser = $request->user();
        $isSuperAdmin = $currentUser->hasRole(Role::SuperAdmin->value);

        $search = $request->query('q');
        $roleFilter = $request->query('role');
        $statusFilter = $request->query('status'); // 'active' | 'never'
        $sort = $request->query('sort', 'latest');

        $query = User::query()
            ->with('roles')
            ->withCount('transactions');

        // Jika bukan Super Admin (misal role admin biasa), sembunyikan pengguna super_admin
        if (! $isSuperAdmin) {
            $query->whereDoesntHave('roles', fn ($r) => $r->where('name', Role::SuperAdmin->value));
            if ($roleFilter === Role::SuperAdmin->value) {
                $roleFilter = null;
            }
        }

        if ($search) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('username', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($roleFilter) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $roleFilter));
        }

        if ($statusFilter === 'active') {
            $query->whereNotNull('last_active_at');
        } elseif ($statusFilter === 'never') {
            $query->whereNull('last_active_at');
        }

        match ($sort) {
            'oldest' => $query->oldest('id'),
            'balance_desc' => $query->orderByDesc('saldo'),
            'balance_asc' => $query->orderBy('saldo'),
            'active_desc' => $query->orderByRaw('last_active_at IS NULL, last_active_at DESC'),
            'name_asc' => $query->orderBy('username'),
            'orders_desc' => $query->orderByDesc('transactions_count'),
            default => $query->latest('id'),
        };

        $users = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'saldo' => $u->saldo,
                'role' => $u->primaryRole(),
                'orders_count' => $u->transactions_count,
                'last_active_at' => $u->last_active_at?->format('d M Y H:i'),
                'last_active_iso' => $u->last_active_at?->toIso8601String(),
                'is_recently_active' => $u->last_active_at && $u->last_active_at->greaterThanOrEqualTo(now()->subDays(7)),
                'created_at' => $u->created_at->format('d M Y'),
                'created_at_full' => $u->created_at->format('d M Y H:i'),
            ]);

        $roleCounts = [
            'all' => $isSuperAdmin
                ? User::count()
                : User::whereDoesntHave('roles', fn ($q) => $q->where('name', Role::SuperAdmin->value))->count(),
            'pengguna' => User::whereHas('roles', fn ($q) => $q->where('name', Role::User->value))->count(),
            'kurir' => User::whereHas('roles', fn ($q) => $q->where('name', Role::Courier->value))->count(),
            'admin' => User::whereHas('roles', fn ($q) => $q->where('name', Role::Admin->value))->count(),
            'super_admin' => $isSuperAdmin
                ? User::whereHas('roles', fn ($q) => $q->where('name', Role::SuperAdmin->value))->count()
                : 0,
        ];

        $stats = [
            'total' => $roleCounts['all'],
            'customers' => $roleCounts['pengguna'],
            'couriers' => $roleCounts['kurir'],
            'admins' => $isSuperAdmin ? ($roleCounts['admin'] + $roleCounts['super_admin']) : $roleCounts['admin'],
            'total_balance' => (float) (
                $isSuperAdmin
                    ? User::sum('saldo')
                    : User::whereDoesntHave('roles', fn ($q) => $q->where('name', Role::SuperAdmin->value))->sum('saldo')
            ),
        ];

        $roles = array_map(fn ($r) => $r->value, Role::cases());
        if (! $isSuperAdmin) {
            $roles = array_values(array_filter($roles, fn ($r) => $r !== Role::SuperAdmin->value));
        }

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => $roles,
            'stats' => $stats,
            'roleCounts' => $roleCounts,
            'isSuperAdmin' => $isSuperAdmin,
            'filters' => [
                'q' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
                'sort' => $sort,
            ],
            'urls' => [
                'base' => url('/admin'),
            ],
        ]);
    }

    public function store(Request $request, WalletService $wallet): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash:ascii', 'unique:users,username'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(array_map(fn ($r) => $r->value, Role::cases()))],
            'initial_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $user = User::create([
            'username' => $data['username'],
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        RoleModel::findOrCreate($data['role'], 'web');
        $user->assignRole($data['role']);

        if (! empty($data['initial_balance']) && bccomp((string) $data['initial_balance'], '0', 2) > 0) {
            $wallet->credit(
                $user,
                (string) $data['initial_balance'],
                'adjustment',
                null,
                'Saldo awal pembuatan akun oleh admin',
                $request->user()
            );
        }

        return back()->with('success', "Pengguna baru @{$user->username} berhasil ditambahkan.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($user->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($data);

        return back()->with('success', "Profil pengguna @{$user->username} berhasil diperbarui.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn ($r) => $r->value, Role::cases()))],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== Role::SuperAdmin->value) {
            $otherSuper = User::whereHas('roles', fn ($q) => $q->where('name', Role::SuperAdmin->value))
                ->where('id', '!=', $user->id)
                ->exists();
            if (! $otherSuper) {
                return back()->withErrors(['role' => 'Tidak bisa mencabut role super_admin dari akun terakhir.']);
            }
        }

        RoleModel::findOrCreate($data['role'], 'web');
        $user->syncRoles([$data['role']]);

        return back()->with('success', "Role {$user->username} diubah menjadi {$data['role']}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update(['password' => $data['password']]);

        return back()->with('success', "Password untuk {$user->username} berhasil direset.");
    }

    public function adjustBalance(Request $request, User $user, WalletService $wallet): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($data['type'] === 'credit') {
                $wallet->credit($user, (string) $data['amount'], 'adjustment', null, $data['note'], $request->user());
            } else {
                $wallet->debit($user, (string) $data['amount'], 'adjustment', null, $data['note'], $request->user());
            }
        } catch (\InvalidArgumentException|InsufficientBalanceException $e) {
            return back()->withErrors(['saldo' => $e->getMessage()]);
        }

        return back()->with('success', "Saldo {$user->username} berhasil disesuaikan.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'Tidak bisa menghapus akun Anda sendiri.']);
        }

        $user->delete();

        return back()->with('success', "User {$user->username} berhasil dihapus.");
    }
}
