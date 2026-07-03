<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('loads the admin panel login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('authenticates the seeded demo user into the panel', function () {
    $this->seed(DatabaseSeeder::class);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'demo@growthops.test',
            'password' => config('app.demo_user_password'),
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs(User::where('email', 'demo@growthops.test')->firstOrFail());

    $this->get('/admin')->assertOk();
});
