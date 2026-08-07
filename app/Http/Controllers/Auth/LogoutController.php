<?php

namespace App\Http\Controllers\Auth;

use App\Livewire\Actions\Logout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController
{
    public function __invoke(Request $request, Logout $logout): RedirectResponse
    {
        $logout();

        return redirect('/');
    }
}
