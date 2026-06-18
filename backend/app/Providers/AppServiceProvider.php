<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Contracts\PushSender;
use App\Notifications\FcmPushSender;
use App\Notifications\LogPushSender;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the JWT-backed "api" guard so services can constructor-inject it.
        $this->app->bind(JWTGuard::class, static function (): JWTGuard {
            /** @var JWTGuard $guard */
            $guard = Auth::guard('api');

            return $guard;
        });

        // Select the push transport by config (task 020): `log` (default — dev/CI,
        // no FCM needed) or `fcm`. NotificationService depends only on the
        // PushSender contract, so swapping transports is a one-line config flip.
        $this->app->singleton(PushSender::class, static function ($app): PushSender {
            /** @var LoggerInterface $logger */
            $logger = $app->make(LoggerInterface::class);

            return match ((string) config('services.fcm.driver')) {
                'fcm' => new FcmPushSender(
                    $logger,
                    config('services.fcm.project_id'),
                    config('services.fcm.credentials'),
                ),
                default => new LogPushSender($logger),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
