<?php

namespace App\Features\SystemSettings\Admin\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use BelongsToTenant;

    protected $table = 'settings';

    protected $fillable = ['setting_key', 'setting_value'];

    /** Settings are cached per tenant: "app.settings.{tenant_id}". */
    public const CACHE_PREFIX = 'app.settings.';

    protected static function booted(): void
    {
        static::saved(fn (self $setting) => Cache::forget(self::cacheKey($setting->tenant_id)));
        static::deleted(fn (self $setting) => Cache::forget(self::cacheKey($setting->tenant_id)));
    }

    public static function cacheKey(?int $tenantId = null): string
    {
        return self::CACHE_PREFIX.($tenantId ?? app(TenantContext::class)->idOrFail());
    }

    public static function allKeyed(): array
    {
        return Cache::rememberForever(self::cacheKey(), function () {
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
