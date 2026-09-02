<?php

namespace App\Providers;

use App\Services\WhatsApp\WaMeLinkSender;
use App\Services\WhatsApp\WhatsAppSender;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppSender::class, function () {
            return match (config('salon.whatsapp_driver', 'wame')) {
                'wame' => new WaMeLinkSender,
                'cloud' => throw new RuntimeException('Cloud API sender not implemented'),
                default => throw new RuntimeException('Unknown whatsapp_driver: '.config('salon.whatsapp_driver')),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
