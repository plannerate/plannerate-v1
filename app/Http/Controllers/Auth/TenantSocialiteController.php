<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSocialiteProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class TenantSocialiteController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        $tenantProvider = $this->resolveProvider($provider);

        $this->configureSocialite($tenantProvider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $tenantProvider = $this->resolveProvider($provider);

        $this->configureSocialite($tenantProvider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            Log::warning('Socialite callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('app.auth.socialite_callback_failed'),
            ]);
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user instanceof User) {
            return redirect()->route('login')->withErrors([
                'email' => __('app.auth.socialite_user_not_found'),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }

    private function resolveProvider(string $provider): TenantSocialiteProvider
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            abort(404);
        }

        $tenantProvider = $tenant->socialiteProvider;

        if (! $tenantProvider instanceof TenantSocialiteProvider || $tenantProvider->provider !== $provider) {
            abort(404);
        }

        if (! $tenantProvider->is_active) {
            abort(404);
        }

        // reassign to satisfy type narrowing below
        /** @var TenantSocialiteProvider $tenantProvider */

        return $tenantProvider;
    }

    private function configureSocialite(TenantSocialiteProvider $tenantProvider): void
    {
        $redirectUrl = route('tenant.auth.socialite.callback', ['provider' => $tenantProvider->provider]);

        $config = [
            'services.'.$tenantProvider->provider.'.client_id' => $tenantProvider->client_id,
            'services.'.$tenantProvider->provider.'.client_secret' => $tenantProvider->client_secret,
            'services.'.$tenantProvider->provider.'.redirect' => $redirectUrl,
        ];

        if ($tenantProvider->provider === 'azure' && $tenantProvider->azure_tenant) {
            $config['services.azure.tenant'] = $tenantProvider->azure_tenant;
        }

        config($config);
    }
}
