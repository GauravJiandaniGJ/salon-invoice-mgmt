<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Setting;
use App\Services\WhatsApp\CloudApiSender;
use App\Services\WhatsApp\WaMeLinkSender;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppSender::class, function () {
            return match (self::whatsappDriver()) {
                'wame' => new WaMeLinkSender,
                'cloud' => CloudApiSender::fromSettings(),
                default => throw new RuntimeException('Unknown whatsapp_driver: '.self::whatsappDriver()),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(Login::class, fn (Login $e) => Activity::log('auth.login', 'Signed in', $e->user, null, $e->user->name));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && Activity::log('auth.logout', 'Signed out', $e->user, null, $e->user->name));
        Event::listen(Failed::class, fn (Failed $e) => Activity::log('auth.login_failed', 'Wrong password or unknown account', null, null, (string) ($e->credentials['email'] ?? 'unknown')));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        //
    }

    /** The owner's setting wins; config/env is the fallback (and the safe default when the DB is not ready). */
    public static function whatsappDriver(): string
    {
        try {
            $driver = (string) Setting::get('whatsapp_driver', config('salon.whatsapp_driver', 'wame'));
        } catch (Throwable) {
            $driver = (string) config('salon.whatsapp_driver', 'wame');
        }

        return $driver !== '' ? $driver : 'wame';
    }
}
