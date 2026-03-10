<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function user(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->attributes->get('test_user');

        abort_unless((bool)$user, 401, 'Unauthorized');

        return $user;
    }
}
