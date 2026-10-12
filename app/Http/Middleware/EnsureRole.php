<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian: ->middleware('role:admin,bendahara')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user && in_array($user->role, array_map(Role::from(...), $roles), true),
            403,
            'Anda tidak memiliki akses ke fitur ini.',
        );

        return $next($request);
    }
}
