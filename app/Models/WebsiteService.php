<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $category
 * @property string|null $description
 * @property string $icon
 * @property bool $is_active
 * @property string $unavailable_headline
 * @property string|null $maintenance_message
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebsiteService extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'website_services_all';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'website_services';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'icon',
        'is_active',
        'unavailable_headline',
        'maintenance_message',
        'sort_order',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Bootstrap the model and register clear-cache hooks.
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            static::flushCache();
        });

        static::deleted(function (): void {
            static::flushCache();
        });
    }

    /**
     * Clear the cached website services collection.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Retrieve all website services ordered by sort_order with caching.
     *
     * @return Collection<int, static>
     */
    public static function allCached(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): Collection {
            return static::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Check if a specific service is currently active and available.
     */
    public static function isServiceActive(string $code): bool
    {
        $code = strtolower(trim($code));

        $service = static::allCached()->first(function (self $item) use ($code): bool {
            return strtolower($item->code) === $code;
        });

        // If service is not registered in the database, default to true so unmanaged services aren't blocked
        if (! $service) {
            return true;
        }

        return (bool) $service->is_active;
    }

    /**
     * Find a service by its code from the cached collection.
     */
    public static function findByCode(string $code): ?self
    {
        $code = strtolower(trim($code));

        return static::allCached()->first(function (self $item) use ($code): bool {
            return strtolower($item->code) === $code;
        });
    }
}
