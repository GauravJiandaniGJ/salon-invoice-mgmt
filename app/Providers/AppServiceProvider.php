<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\WhatsApp\CloudApiSender;
use App\Services\WhatsApp\WaMeLinkSender;
use App\Services\WhatsApp\WhatsAppSender;
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
