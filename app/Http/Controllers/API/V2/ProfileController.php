<?php

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\HttpResponses;
use App\Traits\ImageManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use HttpResponses;
    use ImageManager;

    public function show()
    {
        $user = User::find(Auth::id());

        return $this->success(new UserResource($user), 'User profile retrieved successfully');
    }

    public function updateProfile(UpdateUserRequest $request)
    {
        try {
            $user = User::find(Auth::id());
            $user->name = $request->name ?? $user->name;
            $user->email = $request->email ?? $user->email;
            $user->phone = $request->phone ?? $user->phone;
            $user->address = $request->address ?? $user->address;
            $user->gender = $request->gender ?? $user->gender;
            $user->dob = $request->dob ?? $user->dob;

            if ($file = $request->file('profile')) {
                $fileData = $this->uploads($file, 'images/');
                $user->profile = $fileData['fileName'];
            }

            $user->update();

            return $this->success(new UserResource($user), 'Successfully updated profile');
        } catch (Exception $e) {
            return $this->error(null, 'Failed to update profile');
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'old_password' => 'required',
                'password' => 'required|confirmed'
            ]);

            $user = User::find(Auth::id());

            if (!Hash::check($request->old_password, $user->password)) {
                return $this->error(null, 'Password is incorrect');
            }

            $user->password = Hash::make($request->password);
            $user->update();
            $user->tokens()->delete();

            return $this->success($user, 'Successfully changed password');
        } catch (Exception $e) {
            return $this->error(null, 'Failed to change password');
        }
    }
}
