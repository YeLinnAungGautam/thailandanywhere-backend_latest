<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HttpResponses;

    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'LIKE', "%{$request->search}%")
                    ->orWhere('email', 'LIKE', "%{$request->search}%");
            })
            ->when($request->unique_key, function ($query) use ($request) {
                $query->where('unique_key', $request->unique_key);
            })
            ->paginate($request->limit ?? 10);

        return $this->success(UserResource::collection($users)
            ->additional([
                'meta' => [
                    'total_page' => (int) ceil($users->total() / $users->perPage()),
                ],
            ])
            ->response()
            ->getData(), 'User List');
    }
}
