<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class DemoResetController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $this->authorize($token);

        return view('demo-reset');
    }

    public function reset(Request $request, string $token): RedirectResponse
    {
        $this->authorize($token);

        Artisan::call('growthops:demo-reset');

        return redirect()->route('demo-reset.show', ['token' => $token])->with('status', trim(Artisan::output()));
    }

    private function authorize(string $token): void
    {
        $expected = config('growthops.demo_reset.token');

        if (blank($expected) || ! hash_equals($expected, $token)) {
            abort(404);
        }
    }
}
