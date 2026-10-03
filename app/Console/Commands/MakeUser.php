<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;

class MakeUser extends Command
{
    protected $signature = 'ramela:make-user {username} {role : pengguna|admin|kurir|super_admin} {--password= : Kosongkan untuk password acak}';

    protected $description = 'Buat akun dengan role tertentu (password acak ditampilkan sekali)';

    public function handle(): int
    {
        $role = Role::tryFrom($this->argument('role'));

        if (! $role) {
            $this->error('Role tidak valid. Pilihan: '.implode(', ', array_map(fn ($r) => $r->value, Role::cases())));

            return self::FAILURE;
        }

        $username = $this->argument('username');

        if (User::withTrashed()->where('username', $username)->exists()) {
            $this->error("Username \"{$username}\" sudah dipakai.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);

        RoleModel::findOrCreate($role->value, 'web');
        User::create(['username' => $username, 'password' => $password])->assignRole($role->value);

        $this->info("Akun dibuat. username: {$username} | role: {$role->value} | password: {$password}");

        return self::SUCCESS;
    }
}
