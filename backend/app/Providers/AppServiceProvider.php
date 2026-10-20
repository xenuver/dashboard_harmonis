<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perSecond(3)->by('login-ip:'.$request->ip()),

                Limit::perSecond(3)->by(
                    'login-username:'.strtolower($request->input('username', ''))
                ),
            ];
        });

        Gate::define('upload-reports', function (User $user) {
            return $user->isItStaff()
                ? Response::allow()
                : Response::denyWithStatus(403, 'Anda tidak diperbolehkan untuk mengunggah laporan');
        });

        Gate::define('manage-users', function (User $user) {
            return $user->isItStaff()
                ? Response::allow()
                : Response::denyWithStatus(403, 'Anda tidak diperbolehkan untuk mengatur pengguna');
        });
    }
}
