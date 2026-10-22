<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** A deactivated account cannot log in. */
class BlockInactiveUsers
{
    public function handle(Login $event): void
    {
        if (! (bool) ($event->user->is_active ?? true)) {
            Auth::guard($event->guard)->logout();

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }
    }
}
