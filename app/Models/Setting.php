<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** @var array<string, string|null>|null request-level cache */
    protected static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all_cached();

        if (! array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') {
            return $default ?? config("salon.defaults.$key");
        }

        return $all[$key];
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        static::$cache = null;
    }

    /** @return array<string, string|null> */
    public static function all_cached(): array
    {
        if (static::$cache === null) {
            static::$cache = static::query()->pluck('value', 'key')->all();
        }

        return static::$cache;
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }
}
