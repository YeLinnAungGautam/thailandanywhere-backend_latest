<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
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

        $partner = Partner::where('email', $request->email)->first();

        if (is_null($partner) || !Hash::check($request->password, $partner->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $partner->createToken('partner-token')->plainTextToken;

        return success([
            'partner' => $partner,
            'token' => $token
        ], 'Successfully Login');
    }

    public function loginUser(Request $request)
    {
        $query = Partner::query();
        $query->where('id', Auth::id());
        $data = $query->first();

        return $this->success([
            'partner' => new PartnerResource($data),
        ], 'Partner Account Detail');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return successMessage('Successfully Logout');
    }
}
