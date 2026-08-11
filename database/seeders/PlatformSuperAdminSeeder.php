<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'jupazago11@gmail.com']);

        $admin->fill([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'password' => Hash::make('123456'),
            'status' => 'active',
            'is_platform_admin' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ]);

        if (blank($admin->remember_token)) {
            $admin->remember_token = Str::random(10);
        }

        $admin->save();
    }
}
