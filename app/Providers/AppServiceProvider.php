<?php

namespace App\Providers;

use App\Services\PhotoStorage\GoogleDriveStorage;
use App\Services\PhotoStorage\LocalPhotoStorage;
use App\Services\PhotoStorage\PhotoStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PhotoStorage::class, function () {
            $json = config('services.google_drive.service_account_json', '');
            if ($json && $json !== '') {
                return new GoogleDriveStorage();
            }
            return new LocalPhotoStorage();
        });
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(($request->input('email') ?? '').'|'.$request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.',
                ], 429));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
