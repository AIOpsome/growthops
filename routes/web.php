<?php

use App\Http\Controllers\DemoResetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Rehearsal-only, token-gated (see config/growthops.php demo_reset.token) — not linked from any UI nav.
Route::get('/internal/demo-reset/{token}', [DemoResetController::class, 'show'])->name('demo-reset.show');
Route::post('/internal/demo-reset/{token}', [DemoResetController::class, 'reset'])->name('demo-reset.reset');
