<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTestUser
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-Test-User-Id');

        abort_unless($userId !== null, 401, 'X-Test-User-Id header is required');

        $user = User::query()->find((int) $userId);

        abort_unless($user instanceof User, 401, 'User not found');

        $request->attributes->set('test_user', $user);

        return $next($request);
    }
}
