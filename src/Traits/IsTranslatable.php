<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shammaa\LaravelTranslations\Exceptions\InvalidLocaleException;
use Shammaa\LaravelTranslations\Exceptions\InvalidTranslationFieldException;
use Shammaa\LaravelTranslations\Models\Translation;
use Shammaa\LaravelTranslations\Services\TranslationManager;
use Shammaa\LaravelTranslations\Services\TranslationValidator;

trait IsTranslatable
{
    /**
     * Pending translation attributes to be saved.
     */
    protected array $pendingTranslations = [];

    /**
     * Current locale override for this model instance.
     * If set, magic methods will use this locale instead of app locale.
     */
    protected ?string $localeOverride = null;

    /**
     * Default locale for this model (can be set in model).
     * 
     * ⚠️ DEPRECATED: This property is ignored - the model ALWAYS follows app()->getLocale() (site locale)!
     * Setting this property has no effect - the model will always use the current site locale.
     * 
     * This property is kept for backward compatibility only.
     * 
     * @var string|null
     * @deprecated The model always follows app()->getLocale() - this property is ignored
     */
    protected ?string $translationLocale = null;

    /**
     * Boot the trait.
     */
    protected static function bootIsTranslatable(): void
    {
        // Save translations when model is saved
        static::saved(function ($model) {
            $model->saveTranslations();
        });
    }

    /**
     * Get the translations relationship.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Scope: Filter by translation field value.
     *
     * @param Builder $query The query builder
     * @param string $field The translation field name
     * @param string $operator The comparison operator (e.g., '=', 'like', '>')
     * @param mixed $value The value to compare against
     * @param string|null $locale The locale (defaults to current app locale)
     * @return Builder
     * @example Article::whereTranslation('title', 'like', '%Laravel%')->get();
     */
    public function scopeWhereTranslation(Builder $query, string $field, string $operator, $value = null, ?string $locale = null): Builder
    {
        $locale = $locale ?? app()->getLocale();
        $viewName = config('translations.translations_view', 'translations_view');
        $model = $query->getModel();
        $table = $model->getTable();
        
        if (config('translations.use_view', true)) {
            return $query->join($viewName . ' as t', function ($join) use ($table, $model, $locale) {
                $join->on("{$table}.id", '=', 't.translatable_id')
                     ->where('t.translatable_type', '=', $model->getMorphClass())
                     ->where('t.locale', '=', $locale);
            })->where('t.' . $field, $operator, $value);
        }

        return $query->whereHas('translations', function ($q) use ($field, $operator, $value, $locale) {
            $q->where('locale', $locale)->where($field, $operator, $value);
        });
    }

    /**
     * Scope: Filter by translation field using LIKE.
     *
     * @param Builder $query The query builder
     * @param string $field The translation field name
     * @param string $value The value to search for (will be wrapped with %)
     * @param string|null $locale The locale (defaults to current app locale)
     * @return Builder
     * @example Article::whereTranslationLike('title', 'Laravel')->get();
     */
    public function scopeWhereTranslationLike(Builder $query, string $field, string $value, ?string $locale = null): Builder
    {
        return $this->scopeWhereTranslation($query, $field, 'like', "%{$value}%", $locale);
    }

    /**
     * Scope: Filter models that have translation for locale.
     * 
     * @example Article::hasTranslation('en')->get();
     * @example Article::hasTranslation('en', 'title')->get();
     */
    public function scopeHasTranslation(Builder $query, ?string $locale = null, ?string $field = null): Builder
    {
        $locale = $locale ?? app()->getLocale();
        $viewName = config('translations.translations_view', 'translations_view');
        $model = $query->getModel();
        $table = $model->getTable();

        if (config('translations.use_view', true)) {
            return $query->join($viewName . ' as t', function ($join) use ($table, $model, $locale, $field) {
                $join->on("{$table}.id", '=', 't.translatable_id')
                     ->where('t.translatable_type', '=', $model->getMorphClass())
                     ->where('t.locale', '=', $locale);

                if ($field) {
                    $join->whereNotNull('t.' . $field)
                         ->where('t.' . $field, '!=', '');
                }
            });
        }

        return $query->whereHas('translations', function ($q) use ($locale, $field) {
            $q->where('locale', $locale);
            if ($field) {
                $q->whereNotNull($field)->where($field, '!=', '');
            }
        });
    }

    /**
     * Scope: Filter models that are missing translation.
     * 
     * @example Article::missingTranslation('fr')->get();
     */
    public function scopeMissingTranslation(Builder $query, ?string $locale = null, ?string $field = null): Builder
    {
        $locale = $locale ?? app()->getLocale();

        return $query->whereDoesntHave('translations', function ($q) use ($locale, $field) {
            $q->where('locale', $locale);
            if ($field) {
                $q->whereNotNull($field)->where($field, '!=', '');
            }
        });
    }

    /**
     * Scope: Eager load translations.
     * 
     * @example Article::withTranslations()->get();
     * @example Article::withTranslations(['ar', 'en'])->get();
     */
    public function scopeWithTranslations(Builder $query, array $locales = []): Builder
    {
        if (empty($locales)) {
            $locales = [app()->getLocale()];
        }

        return $query->with(['translations' => function ($q) use ($locales) {
            $q->whereIn('locale', $locales);
        }]);
    }

    /**
     * Get translation statistics for query.
     * 
     * @example Article::translationStats();
     * @example Article::translationStats(['ar', 'en']);
     */
    public static function translationStats(array $locales = []): array
    {
        if (empty($locales)) {
            $locales = config('translations.supported_locales', ['ar', 'en']);
        }

        $model = new static;
        $results = \DB::table('translations')
            ->where('translatable_type', static::class)
            ->whereIn('locale', $locales)
            ->select('locale', \DB::raw('COUNT(DISTINCT translatable_id) as count'))
            ->groupBy('locale')
            ->pluck('count', 'locale')
            ->toArray();

        $stats = [];
        foreach ($locales as $locale) {
            $stats[$locale] = $results[$locale] ?? 0;
        }

        return $stats;
    }

    /**
     * Get translatable fields for this model.
     */
    public function getTranslatableFields(): array
    {
        if (property_exists($this, 'translatable')) {
            return $this->translatable;
        }

        // Auto-detect from config
        if (config('translations.auto_detect_fields', true)) {
            $defaultFields = config('translations.default_translatable_fields', []);
            return array_intersect($defaultFields, $this->getFillable() ?? []);
        }

        return [];
    }

    /**
     * Get a translation value.
     *
     * @param string $key The translation key
     * @param string|null $locale The locale (defaults to current app locale)
     * @return string|null The translation value or null if not found
     * @throws InvalidLocaleException
     * @throws InvalidTranslationFieldException
     */
    public function getTranslation(string $key, ?string $locale = null): ?string
    {
        try {
            $locale = $locale ?? app()->getLocale();
            
            // Validate locale
            TranslationValidator::validateLocale($locale);
            
            // Validate field
            $translatableFields = $this->getTranslatableFields();
            TranslationValidator::validateField($key, $translatableFields);
            
            $cacheKey = $this->getTranslationCacheKey($key, $locale);

            if (config('translations.cache.enabled', true)) {
                return Cache::remember($cacheKey, config('translations.cache.ttl', 3600), function () use ($key, $locale) {
                    return $this->loadTranslation($key, $locale);
                });
            }

            return $this->loadTranslation($key, $locale);
        } catch (\Exception $e) {
            Log::error('Translation get failed', [
                'key' => $key,
                'locale' => $locale ?? 'null',
                'model' => static::class,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw validation exceptions
            if ($e instanceof InvalidLocaleException || $e instanceof InvalidTranslationFieldException) {
                throw $e;
            }
            
            // Return null for other exceptions (database errors, etc.)
            return null;
        }
    }

    /**
     * Load translation from database or view.
     *
     * @param string $key The translation key
     * @param string $locale The locale
     * @return string|null The translation value or null if not found
     */
    protected function loadTranslation(string $key, string $locale): ?string
    {
        try {
            $manager = app(TranslationManager::class);

            if (config('translations.use_view', true)) {
                return $manager->getFromView(
                    static::class,
                    $this->getKey(),
                    $locale,
                    $key
                );
            }

            return $this->translations()
                ->where('locale', $locale)
                ->where('key', $key)
                ->value('value');
        } catch (\Exception $e) {
            Log::error('Translation load failed', [
                'key' => $key,
                'locale' => $locale,
                'model' => static::class,
                'model_id' => $this->getKey(),
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Get all translations for a specific locale.
     */
    public function getTranslations(?string $locale = null): Collection
    {
        $locale = $locale ?? app()->getLocale();
        $cacheKey = $this->getTranslationsCacheKey($locale);

        if (config('translations.cache.enabled', true)) {
            return Cache::remember($cacheKey, config('translations.cache.ttl', 3600), function () use ($locale) {
                return $this->loadTranslations($locale);
            });
        }

        return $this->loadTranslations($locale);
    }

    /**
     * Load all translations from database or view.
     */
    protected function loadTranslations(string $locale): Collection
    {
        $manager = app(TranslationManager::class);

        if (config('translations.use_view', true)) {
            $translations = $manager->getAllFromView(
                static::class,
                $this->getKey(),
                $locale
            );

            return collect($translations);
        }

        return $this->translations()
            ->where('locale', $locale)
            ->pluck('value', 'key');
    }

    /**
     * Set translation for a key (pending save).
     *
     * @param string $key The translation key
     * @param string|null $value The translation value
     * @param string|null $locale The locale (defaults to current app locale)
     * @return $this
     * @throws InvalidLocaleException
     * @throws InvalidTranslationFieldException
     */
    public function setTranslation(string $key, ?string $value, ?string $locale = null): self
    {
        try {
            $locale = $locale ?? app()->getLocale();
            
            // Validate locale
            TranslationValidator::validateLocale($locale);
            
            // Validate field
            $translatableFields = $this->getTranslatableFields();
            TranslationValidator::validateField($key, $translatableFields);

            if (!isset($this->pendingTranslations[$locale])) {
                $this->pendingTranslations[$locale] = [];
            }

            $this->pendingTranslations[$locale][$key] = $value;

            return $this;
        } catch (\Exception $e) {
            Log::error('Translation set failed', [
                'key' => $key,
                'locale' => $locale ?? 'null',
                'model' => static::class,
                'error' => $e->getMessage(),
            ]);
            
            // Re-throw validation exceptions
            if ($e instanceof InvalidLocaleException || $e instanceof InvalidTranslationFieldException) {
                throw $e;
            }
            
            // For other exceptions, return $this but log the error
            return $this;
        }
    }
    /**
     * Get pending translation (for compatibility with other packages).
     */
    public function getPendingTranslation(string $key, string $locale): ?string
    {
        return $this->pendingTranslations[$locale][$key] ?? null;
    }


    /**
     * Set multiple translations at once (pending save).
     * 
     * @example $article->setTranslations(['title' => 'Article', 'slug' => 'article'], 'en');
     * @example $article->setTranslations(['title' => 'Article']); // Uses current locale
     */
    public function setTranslations(array $translations, ?string $locale = null): self
    {
        $locale = $locale ?? app()->getLocale();

        foreach ($translations as $key => $value) {
            $this->setTranslation($key, $value, $locale);
        }

        return $this;
    }

    /**
     * Set translations - automatically uses current locale if not specified.
     * 
     * @example $article->translateTo(['title' => 'Article', 'slug' => 'article']); // Uses current locale automatically!
     * @example $article->translateTo('en', ['title' => 'Article', 'slug' => 'article']); // Specific locale
     */
    public function translateTo($localeOrTranslations, ?array $translations = null): self
    {
        // If first parameter is array, it means no locale provided - use current locale automatically
        if (is_array($localeOrTranslations) && $translations === null) {
            $data = $localeOrTranslations;

            // Auto-decode JSON strings if they are passed as values
            foreach ($data as $key => $value) {
                if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data[$key] = $decoded;
                    }
                }
            }

            if (count($data) > 0) {
                $firstKey = (string) key($data);
                $firstValue = reset($data);

                if (is_array($firstValue)) {
                    // Determine if this is attribute-first: ['name' => ['ar' => '...', 'en' => '...']]
                    if (in_array($firstKey, $this->getTranslatableFields())) {
                        foreach ($data as $attribute => $locales) {
                            if (is_array($locales)) {
                                foreach ($locales as $locale => $value) {
                                    $this->setTranslation($attribute, $value, $locale);
                                }
                            } else {
                                $this->setTranslation($attribute, $locales, app()->getLocale());
                            }
                        }
                        return $this;
                    }
                }
            }
            return $this->setTranslations($data, app()->getLocale());
        }

        // Otherwise, first parameter is locale (backward compatibility)
        $locale = is_string($localeOrTranslations) ? $localeOrTranslations : app()->getLocale();
        return $this->setTranslations($translations ?? $localeOrTranslations, $locale);
    }

    /**
     * Set translation for current locale (convenient method).
     * 
     * @example $article->translate(['title' => 'Article', 'slug' => 'article']);
     */
    public function translate(array $translations): self
    {
        return $this->setTranslations($translations, app()->getLocale());
    }

    /**
     * Save pending translations to database.
     */
    public function saveTranslations(): void
    {
        if (empty($this->pendingTranslations)) {
            return;
        }

        // Must have an ID
        if (!$this->getKey()) {
            return;
        }

        $manager = app(TranslationManager::class);

        foreach ($this->pendingTranslations as $locale => $translations) {
            // Validate translatable fields
            $validTranslations = [];
            foreach ($translations as $key => $value) {
                if (in_array($key, $this->getTranslatableFields())) {
                    $validTranslations[$key] = $value;
                }
            }

            // Skip locales where all translatable fields are empty
            $hasContent = false;
            foreach ($validTranslations as $value) {
                if (!empty($value)) {
                    $hasContent = true;
                    break;
                }
            }

            if (!$hasContent) {
                continue; // Skip this locale entirely
            }

            if (!empty($validTranslations)) {
                $manager->bulkSet(
                    static::class,
                    $this->getKey(),
                    $locale,
                    $validTranslations
                );
            }
        }

        // Clear all caches for this model
        $this->clearTranslationsCache();
        
        // Reset pending translations
        $this->pendingTranslations = [];
    }

    /**
     * Save translations immediately (without waiting for model save).
     */
    public function saveTranslationsNow(): self
    {
        if (!$this->exists) {
            throw new \RuntimeException('Model must be saved before saving translations.');
        }

        $this->saveTranslations();
        
        return $this;
    }

    /**
     * Clear translation cache.
     */
    public function clearTranslationCache(?string $key = null, ?string $locale = null): void
    {
        if ($key && $locale) {
            Cache::forget($this->getTranslationCacheKey($key, $locale));
        } else {
            // Clear all caches for this model
            $locales = config('translations.supported_locales', ['ar', 'en']);
            $fields = $this->getTranslatableFields();

            foreach ($locales as $loc) {
                Cache::forget($this->getTranslationsCacheKey($loc));
                foreach ($fields as $field) {
                    Cache::forget($this->getTranslationCacheKey($field, $loc));
                }
            }
        }
    }

    /**
     * Clear all translations cache.
     */
    protected function clearTranslationsCache(): void
    {
        $locales = config('translations.supported_locales', ['ar', 'en']);

        foreach ($locales as $locale) {
            Cache::forget($this->getTranslationsCacheKey($locale));
        }
    }

    /**
     * Get translation cache key.
     */
    protected function getTranslationCacheKey(string $key, string $locale): string
    {
        $prefix = config('translations.cache.prefix', 'translations');
        return "{$prefix}.{$this->getMorphClass()}.{$this->getKey()}.{$locale}.{$key}";
    }

    /**
     * Get translations cache key.
     */
    protected function getTranslationsCacheKey(string $locale): string
    {
        $prefix = config('translations.cache.prefix', 'translations');
        return "{$prefix}.{$this->getMorphClass()}.{$this->getKey()}.{$locale}.all";
    }

    /**
     * Magic getter for translation attributes.
     */
    public function __get($key)
    {
        // Check if it's a translatable field
        if (in_array($key, $this->getTranslatableFields())) {
            // Determine which locale to use
            $locale = $this->getEffectiveLocale();
            
            $translation = $this->getTranslation($key, $locale);
            
            // Fallback to default locale if translation is missing
            if ($translation === null) {
                $defaultLocale = config('translations.default_locale', 'ar');
                if ($defaultLocale !== $locale) {
                    $translation = $this->getTranslation($key, $defaultLocale);
                }
            }

            return $translation ?? parent::__get($key) ?? null;
        }

        return parent::__get($key);
    }

    /**
     * Get the effective locale to use for translations.
     * Priority: localeOverride > app locale (site locale) ALWAYS!
     * 
     * The model ALWAYS follows app()->getLocale() (site locale) - translationLocale is IGNORED!
     * This ensures the model responds to locale changes in the site automatically.
     */
    protected function getEffectiveLocale(): string
    {
        // Priority 1: localeOverride (set via setLocale() method)
        // This allows manual override per instance
        if ($this->localeOverride !== null) {
            return $this->localeOverride;
        }

        // Priority 2: app locale (site locale) - ALWAYS use this!
        // The model always follows the site locale, regardless of translationLocale property
        $currentAppLocale = app()->getLocale();
        
        // Always use app locale - this ensures the model follows site locale changes
        return $currentAppLocale;
    }

    /**
     * Magic setter for translation attributes.
     */
    public function __set($key, $value)
    {
        if (in_array($key, $this->getTranslatableFields())) {
            if (is_array($value)) {
                // Multiple locales: ['ar' => '...', 'en' => '...']
                foreach ($value as $locale => $val) {
                    $this->setTranslation($key, $val, $locale);
                }
            } else {
                // Single value for current locale
                $this->setTranslation($key, $value);
            }
            return;
        }

        parent::__set($key, $value);
    }

    /**
     * Fill translations from array.
     * 
     * @example $article->fillTranslations(['en' => ['title' => 'Article', 'slug' => 'article']]);
     */
    public function fillTranslations(array $translationsByLocale): self
    {
        foreach ($translationsByLocale as $locale => $translations) {
            $this->setTranslations($translations, $locale);
        }

        return $this;
    }

    /**
     * Check if translation exists for a locale.
     */
    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        return $this->getTranslation($key, $locale) !== null;
    }

    /**
     * Get translation with fallback (alias for getTranslation with fallback).
     */
    public function translateField(string $key, ?string $locale = null, ?string $fallbackLocale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $fallbackLocale = $fallbackLocale ?? config('translations.default_locale', 'ar');

        $translation = $this->getTranslation($key, $locale);

        if ($translation === null && $locale !== $fallbackLocale) {
            return $this->getTranslation($key, $fallbackLocale);
        }

        return $translation;
    }

    /**
     * Get all available locales for this model.
     */
    public function getAvailableLocales(): array
    {
        return $this->translations()
            ->distinct()
            ->pluck('locale')
            ->toArray();
    }

    /**
     * Get translation record for specific locale.
     */
    public function translation(?string $locale = null): ?Translation
    {
        $locale = $locale ?? app()->getLocale();

        return $this->translations()
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Check if model has translation for locale.
     */
    public function hasTranslationFor(?string $locale = null, ?string $field = null): bool
    {
        $locale = $locale ?? app()->getLocale();
        $query = $this->translations()->where('locale', $locale);

        if ($field) {
            $query->whereNotNull($field)->where($field, '!=', '');
        }

        return $query->exists();
    }

    /**
     * Set locale override for this model instance.
     * When set, magic methods (like $model->title) will use this locale.
     *
     * @param string|null $locale The locale to set (null to reset)
     * @return $this
     * @throws InvalidLocaleException
     * @example $article->setLocale('ar'); echo $article->title; // Returns Arabic translation
     */
    public function setLocale(?string $locale): self
    {
        // If locale is null, allow it (reset override)
        if ($locale !== null) {
            TranslationValidator::validateLocale($locale);
        }
        
        $this->localeOverride = $locale;
        return $this;
    }

    /**
     * Get current locale override or app locale.
     */
    public function getLocale(): string
    {
        return $this->localeOverride ?? app()->getLocale();
    }

    /**
     * Reset locale override to use app locale.
     */
    public function resetLocale(): self
    {
        $this->localeOverride = null;
        return $this;
    }
}

