<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateSuperadmin extends Command
{
    protected $signature = 'posyandu:create-superadmin {email} {--name=Ketua}';

    protected $description = 'Membuat akun Ketua pertama tanpa kata sandi di argumen shell atau log';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Jalankan secara interaktif untuk memasukkan kata sandi privat.');

            return self::FAILURE;
        }
        $data = [
            'email' => $this->argument('email'),
            'name' => $this->option('name'),
            'password' => $this->secret('Kata sandi (minimal 12 karakter)'),
        ];
        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:users'],
            'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:12'],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $this->callSilent('db:seed', ['--class' => AccessControlSeeder::class, '--force' => true]);
            $user = User::create($data);
            $user->assignRole('superadmin');
        });
        $this->info('Akun Ketua dibuat. Registrasi publik tetap tidak tersedia.');

        return self::SUCCESS;
    }
}
