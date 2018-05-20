<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function show()
    {
        return $this->respondWithToken(auth()->getToken()->get());
    }

    public function store(Request $request)
    {
        $this->validateStore($request);

        $user = User::create([
            'email' => $request->input('user.email'),
            'password' => Hash::make($request->input('user.password')),
            'username' => $request->input('user.username'),
        ]);

        auth()->login($user);

        return $this->respondWithToken(auth()->getToken()->get());
    }

    private function validateStore(Request $request)
    {
        $this->validate($request, [
            'user.email' => 'required|string|email|max:255|unique:users,email',
            'user.password' => 'required|string|min:6|max:255',
            'user.username' => 'required|string|max:255|unique:users,username',
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $this->validateUpdate($request, $user->id);

        $user->fill([
            'email' => $request->input('user.email', $user->email),
            'password' => Hash::make($request->input('user.password', $user->password)),
            'username' => $request->input('user.username', $user->username),
            'bio' => $request->input('user.bio', $user->bio),
            'image' => $request->input('user.image', $user->image),
        ])->save();

        return $this->respondWithToken(auth()->getToken()->get());
    }

    private function validateUpdate(Request $request, $userId)
    {
        $this->validate($request, [
            'user.email' => 'string|email|max:255|unique:users,email,' . $userId,
            'user.password' => 'string|min:6|max:255',
            'user.username' => 'string|max:255|unique:users,username,' . $userId,
            'user.bio' => 'string|nullable|max:255',
            'user.image' => 'string|nullable|max:255|url',
        ]);
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        $credentials = $this->credentials($request);

        if (! $token = auth()->attempt($credentials)) {
            throw ValidationException::withMessages([
                'user.email' => [trans('auth.failed')],
            ]);
        }

        return $this->respondWithToken($token);
    }

    private function validateLogin(Request $request)
    {
        $this->validate($request, [
            'user.email' => 'required|string',
            'user.password' => 'required|string',
        ]);
    }

    private function credentials(Request $request)
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
