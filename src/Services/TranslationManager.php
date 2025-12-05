<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shammaa\LaravelTranslations\Exceptions\InvalidLocaleException;
use Shammaa\LaravelTranslations\Models\Translation;
use Shammaa\LaravelTranslations\Services\TranslationValidator;

class TranslationManager
{
    /**
     * Get translation value from view (optimized).
     *
     * @param string $translatableType The translatable model type
     * @param int $translatableId The translatable model ID
     * @param string $locale The locale
     * @param string $key The translation key
     * @return string|null The translation value or null if not found
     * @throws InvalidLocaleException
     */
    public function getFromView(string $translatableType, int $translatableId, string $locale, string $key): ?string
    {
        try {
            // Validate locale
            TranslationValidator::validateLocale($locale);
            
            $viewName = config('translations.translations_view', 'translations_view');
            $cacheKey = $this->getCacheKey($translatableType, $translatableId, $locale, $key);

            if (config('translations.cache.enabled', true)) {
                return Cache::remember($cacheKey, config('translations.cache.ttl', 3600), function () use ($viewName, $translatableType, $translatableId, $locale, $key) {
                    return $this->loadFromView($viewName, $translatableType, $translatableId, $locale, $key);
                });
            }

            return $this->loadFromView($viewName, $translatableType, $translatableId, $locale, $key);
        } catch (\Exception $e) {
            Log::error('TranslationManager getFromView failed', [
                'type' => $translatableType,
                'id' => $translatableId,
                'locale' => $locale,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw validation exceptions
            if ($e instanceof InvalidLocaleException) {
                throw $e;
            }
            
            return null;
        }
    }

    /**
     * Load translation from database view.
     *
     * @param string $viewName The view name
     * @param string $translatableType The translatable model type
     * @param int $translatableId The translatable model ID
     * @param string $locale The locale
     * @param string $key The translation key
     * @return string|null The translation value or null if not found
     */
    protected function loadFromView(string $viewName, string $translatableType, int $translatableId, string $locale, string $key): ?string
    {
        try {
            $searchableFields = config('translations.searchable_fields', ['title', 'slug', 'description']);
            $largeFields = config('translations.large_fields', ['content']);

            // Check if it's a searchable field (column)
            if (in_array($key, $searchableFields, true)) {
                $result = DB::table($viewName)
                    ->where('translatable_type', $translatableType)
                    ->where('translatable_id', $translatableId)
                    ->where('locale', $locale)
                    ->value($key);

                return $result;
            }

            // Check if it's a large field (JSON)
            if (in_array($key, $largeFields, true)) {
                $result = DB::table($viewName)
                    ->where('translatable_type', $translatableType)
                    ->where('translatable_id', $translatableId)
                    ->where('locale', $locale)
                    ->value('large_fields');

                if ($result) {
                    $largeFieldsData = json_decode($result, true);
                    return is_array($largeFieldsData) ? ($largeFieldsData[$key] ?? null) : null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('TranslationManager loadFromView failed', [
                'view' => $viewName,
                'type' => $translatableType,
                'id' => $translatableId,
                'locale' => $locale,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Get all translations from view (optimized).
     */
    public function getAllFromView(string $translatableType, int $translatableId, string $locale): array
    {
        $viewName = config('translations.translations_view', 'translations_view');
        $cacheKey = $this->getCacheKey($translatableType, $translatableId, $locale, 'all');

        if (config('translations.cache.enabled', true)) {
            return Cache::remember($cacheKey, config('translations.cache.ttl', 3600), function () use ($viewName, $translatableType, $translatableId, $locale) {
                return $this->loadAllFromView($viewName, $translatableType, $translatableId, $locale);
            });
        }

        return $this->loadAllFromView($viewName, $translatableType, $translatableId, $locale);
    }

    /**
     * Load all translations from database view.
     */
    protected function loadAllFromView(string $viewName, string $translatableType, int $translatableId, string $locale): array
    {
        $result = DB::table($viewName)
            ->where('translatable_type', $translatableType)
            ->where('translatable_id', $translatableId)
            ->where('locale', $locale)
            ->first();

        if (!$result) {
            return [];
        }

        $translations = [];
        $searchableFields = config('translations.searchable_fields', ['title', 'slug', 'description', 'excerpt', 'meta_title', 'meta_description']);

        // Get searchable fields (columns)
        foreach ($searchableFields as $field) {
            if (isset($result->$field) && $result->$field !== null) {
                $translations[$field] = $result->$field;
            }
        }

        // Get large fields (JSON)
        if (isset($result->large_fields) && $result->large_fields) {
            $largeFieldsData = json_decode($result->large_fields, true);
            if (is_array($largeFieldsData)) {
                $translations = array_merge($translations, $largeFieldsData);
            }
        }

        return $translations;
    }

    /**
     * Set translation value.
     *
     * @param string $translatableType The translatable model type
     * @param int $translatableId The translatable model ID
     * @param string $locale The locale
     * @param string $key The translation key
     * @param string|null $value The translation value
     * @return Translation The translation model
     * @throws InvalidLocaleException
     */
    public function set(string $translatableType, int $translatableId, string $locale, string $key, ?string $value): Translation
    {
        try {
            // Validate locale
            TranslationValidator::validateLocale($locale);
            
            $searchableFields = config('translations.searchable_fields', ['title', 'slug', 'description']);
            $largeFields = config('translations.large_fields', ['content']);

            // Get or create translation record for this locale
            $translation = Translation::firstOrNew([
                'translatable_type' => $translatableType,
                'translatable_id' => $translatableId,
                'locale' => $locale,
            ]);

            // Set searchable field (column)
            if (in_array($key, $searchableFields, true)) {
                $translation->$key = $value;
            }
            // Set large field (JSON)
            elseif (in_array($key, $largeFields, true)) {
                $largeFieldsData = $translation->large_fields ?? [];
                if ($value === null) {
                    unset($largeFieldsData[$key]);
                } else {
                    $largeFieldsData[$key] = $value;
                }
                $translation->large_fields = $largeFieldsData;
            }
            // Unknown field, skip
            else {
                Log::warning('Unknown translation field', [
                    'key' => $key,
                    'type' => $translatableType,
                    'locale' => $locale,
                ]);
                return $translation;
            }

            $translation->save();

            // Clear cache
            $this->clearCache($translatableType, $translatableId, $locale, $key);

            return $translation;
        } catch (\Exception $e) {
            Log::error('TranslationManager set failed', [
                'type' => $translatableType,
                'id' => $translatableId,
                'locale' => $locale,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw validation exceptions
            if ($e instanceof InvalidLocaleException) {
                throw $e;
            }
            
            // For other exceptions, try to return existing translation or create new one
            return Translation::firstOrNew([
                'translatable_type' => $translatableType,
                'translatable_id' => $translatableId,
                'locale' => $locale,
            ]);
        }
    }

    /**
     * Bulk set translations.
     */
    public function bulkSet(string $translatableType, int $translatableId, string $locale, array $translations): void
    {
        $searchableFields = config('translations.searchable_fields', ['title', 'slug', 'description']);
        $largeFields = config('translations.large_fields', ['content']);

        // Get or create translation record
        $translation = Translation::firstOrNew([
            'translatable_type' => $translatableType,
            'translatable_id' => $translatableId,
            'locale' => $locale,
        ]);

        $largeFieldsData = $translation->large_fields ?? [];

        // Set fields
        foreach ($translations as $key => $value) {
            if (in_array($key, $searchableFields)) {
                $translation->$key = $value;
            } elseif (in_array($key, $largeFields)) {
                if ($value === null) {
                    unset($largeFieldsData[$key]);
                } else {
                    $largeFieldsData[$key] = $value;
                }
            }
        }

        $translation->large_fields = $largeFieldsData;
        $translation->save();

        // Clear cache for all keys
        foreach ($translations as $key => $value) {
            $this->clearCache($translatableType, $translatableId, $locale, $key);
        }
    }

    /**
     * Bulk get translations for multiple items (optimized).
     */
    public function bulkGet(array $items, string $locale, array $keys = []): Collection
    {
        $viewName = config('translations.translations_view', 'translations_view');
        $cacheKey = $this->getBulkCacheKey($items, $locale, $keys);

        if (config('translations.cache.enabled', true)) {
            return Cache::remember($cacheKey, config('translations.cache.ttl', 3600), function () use ($viewName, $items, $locale, $keys) {
                return $this->loadBulkFromView($viewName, $items, $locale, $keys);
            });
        }

        return $this->loadBulkFromView($viewName, $items, $locale, $keys);
    }

    /**
     * Load bulk translations from view.
     */
    protected function loadBulkFromView(string $viewName, array $items, string $locale, array $keys): Collection
    {
        $query = DB::table($viewName)
            ->where('locale', $locale);

        $conditions = [];
        foreach ($items as $item) {
            $conditions[] = [
                'translatable_type' => $item['type'],
                'translatable_id' => $item['id'],
            ];
        }

        // Build OR conditions
        $query->where(function ($q) use ($conditions) {
            foreach ($conditions as $condition) {
                $q->orWhere(function ($subQ) use ($condition) {
                    $subQ->where('translatable_type', $condition['type'])
                         ->where('translatable_id', $condition['id']);
                });
            }
        });

        $results = $query->get();

        return collect($results)->map(function ($result) use ($keys) {
            $translations = [];
            $commonFields = ['title', 'slug', 'content', 'description', 'excerpt', 'meta_title', 'meta_description'];

            $fieldsToGet = !empty($keys) ? $keys : $commonFields;

            foreach ($fieldsToGet as $field) {
                if (isset($result->$field) && $result->$field !== null) {
                    $translations[$field] = $result->$field;
                }
            }

            // Parse all_fields for custom fields
            if (isset($result->all_fields) && $result->all_fields) {
                $fields = explode('|||', $result->all_fields);
                foreach ($fields as $field) {
                    if (str_contains($field, ':')) {
                        [$key, $value] = explode(':', $field, 2);
                        if (empty($keys) || in_array($key, $keys)) {
                            $translations[$key] = $value;
                        }
                    }
                }
            }

            return [
                'translatable_type' => $result->translatable_type,
                'translatable_id' => $result->translatable_id,
                'locale' => $result->locale,
                'translations' => $translations,
            ];
        });
    }

    /**
     * Clear cache.
     */
    public function clearCache(string $translatableType, int $translatableId, string $locale, ?string $key = null): void
    {
        if ($key) {
            Cache::forget($this->getCacheKey($translatableType, $translatableId, $locale, $key));
        } else {
            // Clear all caches for this item
            $prefix = config('translations.cache.prefix', 'translations');
            $pattern = "{$prefix}.{$translatableType}.{$translatableId}.{$locale}.*";
            
            // Note: This requires cache tags or manual cache clearing
            // For now, we'll clear the common keys
            Cache::forget($this->getCacheKey($translatableType, $translatableId, $locale, 'all'));
        }
    }

    /**
     * Get cache key.
     */
    protected function getCacheKey(string $translatableType, int $translatableId, string $locale, string $key): string
    {
        $prefix = config('translations.cache.prefix', 'translations');
        return "{$prefix}.{$translatableType}.{$translatableId}.{$locale}.{$key}";
    }

    /**
     * Get bulk cache key.
     */
    protected function getBulkCacheKey(array $items, string $locale, array $keys): string
    {
        $prefix = config('translations.cache.prefix', 'translations');
        $itemsHash = md5(json_encode($items));
        $keysHash = md5(json_encode($keys));
        return "{$prefix}.bulk.{$itemsHash}.{$locale}.{$keysHash}";
    }
}

