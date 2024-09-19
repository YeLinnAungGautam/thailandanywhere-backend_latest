<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $supplier = Supplier::where('email', $request->email)->first();

        if (is_null($supplier) || !Hash::check($request->password, $supplier->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $supplier->createToken('supplier-token')->plainTextToken;

        return success([
            'supplier' => $supplier,
            'token' => $token
        ], 'Successfully Login');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return successMessage('Successfully Logout');
    }
}
