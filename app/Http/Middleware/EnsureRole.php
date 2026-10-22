<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets a route through only for the listed roles, e.g. EnsureRole::class.':admin,cashier' */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->role, $roles, true), 403, 'You do not have access to this page.');

        return $next($request);
    }
}
