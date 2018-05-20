<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return $this->respondWithToken(auth()->getToken()->get());
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        $credentials = $this->credentials($request);

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401); // TODO
        }

        return $this->respondWithToken($token);
    }

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

    private function respondWithToken($token)
    {
        $user = auth()->user();

        return response()->json([
            'user' => [
                'email' => $user->email,
                'token' => $token,
                'username' => $user->username,
                'bio' => $user->bio,
                'image' => $user->image,
            ],
        ]);
    }
}
