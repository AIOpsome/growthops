<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@growthops.test'],
            [
                'name' => 'GrowthOps Demo',
                'password' => Hash::make(config('app.demo_user_password')),
            ],
        );

        Artisan::call('growthops:demo-seed');
        Artisan::call('growthops:analyze');
    }
}
