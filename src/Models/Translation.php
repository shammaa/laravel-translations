<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'translations';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        // Searchable fields (columns)
        'title',
        'slug',
        'description',
        'excerpt',
        'meta_title',
        'meta_description',
        // Large fields (JSON)
        'large_fields',
        // Legacy key-value support
        'key',
        'value',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'large_fields' => 'array',
    ];

    /**
     * Get the parent translatable model.
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include translations for a specific locale.
     */
    public function scopeForLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Scope a query to only include translations for a specific key.
     */
    public function scopeForKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Scope a query to only include translations for a specific translatable type.
     */
    public function scopeForType($query, string $type)
    {
        return $query->where('translatable_type', $type);
    }

    /**
     * Get or create translation for a locale.
     */
    public static function getOrCreateForLocale(
        string $translatableType,
        int $translatableId,
        string $locale,
        array $data = []
    ): self {
        return static::firstOrCreate([
            'translatable_type' => $translatableType,
            'translatable_id' => $translatableId,
            'locale' => $locale,
        ], $data);
    }

    /**
     * Update or create translation for a locale.
     */
    public static function updateOrCreateForLocale(
        string $translatableType,
        int $translatableId,
        string $locale,
        array $data = []
    ): self {
        return static::updateOrCreate([
            'translatable_type' => $translatableType,
            'translatable_id' => $translatableId,
            'locale' => $locale,
        ], $data);
    }

    /**
     * Get large field value (from JSON).
     */
    public function getLargeField(string $key): ?string
    {
        $largeFields = $this->large_fields ?? [];
        return $largeFields[$key] ?? null;
    }

    /**
     * Set large field value (to JSON).
     */
    public function setLargeField(string $key, ?string $value): void
    {
        $largeFields = $this->large_fields ?? [];
        if ($value === null) {
            unset($largeFields[$key]);
        } else {
            $largeFields[$key] = $value;
        }
        $this->large_fields = $largeFields;
    }
}

