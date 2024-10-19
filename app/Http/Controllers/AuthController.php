<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\OAuthProvider;
use App\Models\User;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use HttpResponses;

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::guard('user')->attempt($request->only('email', 'password'))) {
            return $this->error(null, 'Credentials do not match', 401);
        }

        $user = Auth::guard('user')->user();

        $user->tokens()->delete();

        return $this->success([
            'user' => new UserResource($user),
            'token' => $user->createToken('API Token of admin id ' . $user->id, ['user'])->plainTextToken
        ], 'Successfully Login');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|confirmed',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        return $this->success([
            'user' => new UserResource($user),
            'token' => $user->createToken('API Token of user id ' . $user->id, ['user'])->plainTextToken
        ], 'Successfully Registered');

    }

    public function me(Request $request)
    {
        $query = User::query();
        $query->where('id', Auth::id());
        $data = $query->first();

        return $this->success([
            'user' => new UserResource($data),
        ], 'User Account Detail');
    }

    public function logout()
    {
        $user = Auth::user();
        $user->currentAccessToken()->delete();

        return $this->success(null, 'Successfully Logout');
    }

    public function deleteAccountPermanent()
    {
        $user = Auth::user();

        if ($user) {
            OAuthProvider::where('user_id', $user->id)->delete();

            $user->tokens()->delete();
            $user->delete();

            return $this->success(null, 'Account deleted permanently');
        }

        return $this->error(null, 'User not found', 404);
    }
}
