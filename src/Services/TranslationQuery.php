<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TranslationQuery
{
    /**
     * Apply translation where clause using optimized view.
     */
    public function whereTranslation(Builder $query, string $key, string $operator = '=', $value = null, ?string $locale = null): Builder
    {
        $locale = $locale ?? app()->getLocale();
        $viewName = config('translations.translations_view', 'translations_view');
        $model = $query->getModel();
        $table = $model->getTable();

        // Use view for optimized query
        if (config('translations.use_view', true)) {
            return $query->join($viewName, function ($join) use ($table, $model, $locale) {
                $join->on("{$table}.id", '=', "{$viewName}.translatable_id")
                     ->where("{$viewName}.translatable_type", '=', $model->getMorphClass())
                     ->where("{$viewName}.locale", '=', $locale);
            })->where("{$viewName}.{$key}", $operator, $value);
        }

        // Fallback to regular join
        return $query->join('translations as t', function ($join) use ($table, $model, $locale, $key) {
            $join->on("{$table}.id", '=', 't.translatable_id')
                 ->where('t.translatable_type', '=', $model->getMorphClass())
                 ->where('t.locale', '=', $locale)
                 ->where('t.key', '=', $key);
        })->where('t.value', $operator, $value);
    }

    /**
     * Apply translation where like clause.
     */
    public function whereTranslationLike(Builder $query, string $key, string $value, ?string $locale = null): Builder
    {
        return $this->whereTranslation($query, $key, 'like', "%{$value}%", $locale);
    }

    /**
     * Filter by locale availability.
     */
    public function hasTranslation(Builder $query, ?string $locale = null, ?string $key = null): Builder
    {
        $locale = $locale ?? app()->getLocale();
        $viewName = config('translations.translations_view', 'translations_view');
        $model = $query->getModel();
        $table = $model->getTable();

        if (config('translations.use_view', true)) {
            $query->join($viewName, function ($join) use ($table, $model, $locale, $key) {
                $join->on("{$table}.id", '=', "{$viewName}.translatable_id")
                     ->where("{$viewName}.translatable_type", '=', $model->getMorphClass())
                     ->where("{$viewName}.locale", '=', $locale);

                if ($key) {
                    $join->whereNotNull("{$viewName}.{$key}")
                         ->where("{$viewName}.{$key}", '!=', '');
                }
            });
        } else {
            $query->whereExists(function ($subQuery) use ($model, $locale, $key) {
                $subQuery->select(DB::raw(1))
                    ->from('translations')
                    ->whereColumn('translations.translatable_id', $model->getTable() . '.id')
                    ->where('translations.translatable_type', $model->getMorphClass())
                    ->where('translations.locale', $locale);

                if ($key) {
                    $subQuery->where('translations.key', $key)
                             ->whereNotNull('translations.value')
                             ->where('translations.value', '!=', '');
                }
            });
        }

        return $query;
    }

    /**
     * Filter by missing translation.
     */
    public function missingTranslation(Builder $query, ?string $locale = null, ?string $key = null): Builder
    {
        $locale = $locale ?? app()->getLocale();
        $model = $query->getModel();

        return $query->whereDoesntExist(function ($subQuery) use ($model, $locale, $key) {
            $subQuery->select(DB::raw(1))
                ->from('translations')
                ->whereColumn('translations.translatable_id', $model->getTable() . '.id')
                ->where('translations.translatable_type', $model->getMorphClass())
                ->where('translations.locale', $locale);

            if ($key) {
                $subQuery->where('translations.key', $key)
                         ->whereNotNull('translations.value')
                         ->where('translations.value', '!=', '');
            }
        });
    }

    /**
     * Eager load translations for multiple models.
     */
    public function withTranslation(Builder $query, array $locales = [], array $keys = []): Builder
    {
        if (empty($locales)) {
            $locales = [app()->getLocale()];
        }

        return $query->with(['translations' => function ($q) use ($locales, $keys) {
            $q->whereIn('locale', $locales);
            
            if (!empty($keys)) {
                $q->whereIn('key', $keys);
            }
        }]);
    }

    /**
     * Get translation statistics.
     */
    public function getTranslationStats(Builder $query, array $locales = []): array
    {
        if (empty($locales)) {
            $locales = config('translations.supported_locales', ['ar', 'en']);
        }

        $model = $query->getModel();
        $results = DB::table('translations')
            ->where('translatable_type', $model->getMorphClass())
            ->whereIn('locale', $locales)
            ->select('locale', DB::raw('COUNT(DISTINCT translatable_id) as count'))
            ->groupBy('locale')
            ->pluck('count', 'locale')
            ->toArray();

        $stats = [];
        foreach ($locales as $locale) {
            $stats[$locale] = $results[$locale] ?? 0;
        }

        return $stats;
    }
}

