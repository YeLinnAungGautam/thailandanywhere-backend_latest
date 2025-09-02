<?php

namespace App\Http\Controllers\API\Partner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use function PHPUnit\Framework\isEmpty;

class AuthPartnerController extends Controller
{
    use HttpResponses;

    public function loginPartner(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $partner = Partner::where('email', $request->email)->first();

        if (!isEmpty($partner->parent_id) || !is_null($partner->parent_id)) {
            return response()->json(['message' => 'You are not allowed to login with this account.'], 401);
        }

        if (is_null($partner) || !Hash::check($request->password, $partner->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $partner->createToken('partner-token')->plainTextToken;

        $partner->update([
            'login_count' => $partner->login_count + 1
        ]);

        return success([
            'partner' => $partner,
            'token' => $token
        ], 'Successfully Login');
    }

    public function loginUser(Request $request)
    {
        $query = Partner::query()->with(['hotels', 'entranceTickets']);
        $query->where('id', Auth::id());
        $data = $query->first();

        return $this->success([
            'partner' => new PartnerResource($data),
        ], 'Partner Account Detail');
    }

    public function updateProfile(Request $request, $id){
        $partner = Partner::find($id);

        if (!$partner) {
            return $this->error(null, 'Data not found', 404);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',]);

        $partner->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return $this->success($partner, 'Successfully updated');
    }

    public function changePassword(Request $request, $id)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        $partner = Partner::find($id);

        if (!Hash::check($request->current_password, $partner->password)) {
            return $this->error(null, 'Password is incorrect');
        }

        $partner->update([
            'password' => Hash::make($request->password)
        ]);

        return $this->success($partner, 'Successfully changed password');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return successMessage('Successfully Logout');
    }
}
