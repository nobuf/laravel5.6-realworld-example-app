<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Presenters\UserPresenter;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use AuthenticatesUsers;

    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
            'user.email' => 'required|string',
            'user.password' => 'required|string',
        ]);
    }

    protected function credentials(Request $request)
    {
        return [
            'email' => $request->input('user.email'),
            'password' => $request->input('user.password'),
        ];
    }

    protected function sendLoginResponse(Request $request)
    {
        $this->clearLoginAttempts($request);

        return response()->json([
            'user' => $this->guard()->user(),
        ]);
    }

    public function username()
    {
        return 'user.email';
    }
}
