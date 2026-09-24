<?php

namespace App\Features\SystemSettings\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['setting_key', 'setting_value'];

    public const CACHE_KEY = 'app.settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function allKeyed(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::query()
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allKeyed()[$key] ?? $default;
    }
}
