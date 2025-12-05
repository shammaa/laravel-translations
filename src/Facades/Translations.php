<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed getFromView(string $translatableType, int $translatableId, string $locale, string $key)
 * @method static array getAllFromView(string $translatableType, int $translatableId, string $locale)
 * @method static \Shammaa\LaravelTranslations\Models\Translation set(string $translatableType, int $translatableId, string $locale, string $key, ?string $value)
 * @method static void bulkSet(string $translatableType, int $translatableId, string $locale, array $translations)
 * @method static \Illuminate\Support\Collection bulkGet(array $items, string $locale, array $keys = [])
 * @method static void clearCache(string $translatableType, int $translatableId, string $locale, ?string $key = null)
 *
 * @see \Shammaa\LaravelTranslations\Services\TranslationManager
 */
class Translations extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'translations';
    }
}

