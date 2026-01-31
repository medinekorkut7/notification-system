<?php

namespace App\Providers;

use App\Models\Notification;
use App\Observers\NotificationObserver;
use App\Console\Commands\StressNotificationsCommand;
use App\Console\Commands\RequeueDeadLetterNotifications;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

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
        Notification::observe(NotificationObserver::class);

        $this->validateRuntimeConfig();

        if ($this->app->runningInConsole()) {
            $this->commands([
                StressNotificationsCommand::class,
                RequeueDeadLetterNotifications::class,
            ]);
        }
    }

    private function validateRuntimeConfig(): void
    {
        $providerUrl = config('notifications.provider.webhook_url');
        $apiKeys = array_filter(array_map('trim', explode(',', (string) env('API_KEYS', ''))));

        if (!$providerUrl) {
            Log::warning('Notification provider webhook URL is not configured.');
        }

        if (empty($apiKeys)) {
            Log::warning('API_KEYS is not configured; API requests will be unauthorized.');
        }
    }
}
