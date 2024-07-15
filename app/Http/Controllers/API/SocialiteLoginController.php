<?php

namespace App\Http\Controllers\API;

use App\Exceptions\EmailTakenException;
use App\Http\Controllers\Controller;
use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteLoginController extends Controller
{
    public function redirect(string $provider)
    {
        return success([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function callback(string $provider)
    {
        $user = Socialite::driver($provider)->stateless()->user();

        Log::info($user->getEmail());

        $user = $this->findOrCreateUser($provider, $user);

        return view('oauth/callback', [
            'token' => $user->createToken('UserToken')->plainTextToken,
        ]);
    }

    protected function findOrCreateUser(string $provider, $user)
    {
        $oauthProvider = OAuthProvider::where('provider', $provider)
            ->where('provider_user_id', $user->getId())
            ->first();

        if ($oauthProvider) {
            return $oauthProvider->user;
        }

        if (User::where('email', $user->getEmail())->exists()) {
            throw new EmailTakenException;
        }

        return $this->createUser($provider, $user);
    }

    /**
     * Create a new user.
     */
    protected function createUser(string $provider, $sUser) : User
    {
        $user = User::create([
            'name' => $sUser->getName(),
            'first_name' => $sUser->getName(),
            'email' => $sUser->getEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt(Str::random(10)),
        ]);

        $user->oauthProviders()->create([
            'provider' => $provider,
            'provider_user_id' => $sUser->getId(),
        ]);

        return $user;
    }
}
