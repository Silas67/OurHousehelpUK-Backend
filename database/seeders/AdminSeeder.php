<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Upserts the admin account from ADMIN_EMAIL/ADMIN_PASSWORD env vars.
     * No-op if either is unset, so this is safe to run on every boot —
     * set those two variables in Render's environment, redeploy, then log
     * in at /admin/login with them.
     */
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$email || !$password) {
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'         => 'Admin',
                'password'     => bcrypt($password),
                'account_type' => 'admin',
            ]
        );
    }
}
