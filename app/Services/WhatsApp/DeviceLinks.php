<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;

/**
 * Click-to-chat URLs. Desktop goes straight to WhatsApp Web (the wa.me
 * interstitial mangles 4-byte emoji on some browsers); phones use wa.me,
 * which hands off to the installed app.
 */
final class DeviceLinks
{
    public static function web(string $phone, string $message): string
    {
        return 'https://web.whatsapp.com/send?phone='.$phone.'&text='.rawurlencode($message);
    }

    public static function mobile(string $phone, string $message): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public static function isMobile(?Request $request): bool
    {
        $ua = (string) $request?->userAgent();

        return (bool) preg_match('/Android|iPhone|iPad|iPod/i', $ua);
    }

    public static function forRequest(?Request $request, string $phone, string $message): string
    {
        return self::isMobile($request) ? self::mobile($phone, $message) : self::web($phone, $message);
    }
}
