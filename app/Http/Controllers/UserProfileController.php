<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserProfileRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        return success((new UserProfileResource($user)));
    }

    public function updateProfile(UserProfileRequest $request)
    {
        $user = request()->user();

        $user->update($request->validated());

        return success((new UserProfileResource($user)));
    }
}
