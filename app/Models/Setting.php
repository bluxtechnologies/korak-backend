<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Setting extends Model
{
    public const CACHE_KEY = 'settings';

    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Get a setting value, falling back to the given default when missing.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = static::allCached();

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        return $settings[$key];
    }

    /**
     * Create or update a setting and refresh the settings cache.
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        static::flushCache();
    }

    /**
     * Remove a setting and refresh the settings cache.
     */
    public static function forgetValue(string $key): void
    {
        static::query()->where('key', $key)->delete();

        static::flushCache();
    }

    /**
     * Get every setting as a key/value map, cached across requests.
     *
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = Cache::rememberForever(self::CACHE_KEY, function (): array {
            return static::query()->pluck('value', 'key')->all();
        });

        return $settings;
    }

    /**
     * Forget the cached settings map.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
