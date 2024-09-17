<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function profile(Request $request)
    {
        $supplier = $request->user()->load('drivers');

        return success(new SupplierResource($supplier));
    }

    public function updateProfile(SupplierRequest $request)
    {
        $supplier = $request->user();
        $input = $request->validated();

        if ($request->file('logo')) {
            $input['logo'] = uploadFile($request->file('logo'), 'images/supplier/');
        }

        $supplier->update($input);

        return success(new SupplierResource($supplier), 'Successfully updated');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'password' => 'required|confirmed',
        ]);

        $supplier = $request->user();

        if (!Hash::check($request->old_password, $supplier->password)) {
            return failedMessage('Old password is incorrect');
        }

        $supplier->update([
            'password' => Hash::make($request->password),
        ]);

        return success('Password has been changed');
    }
}
