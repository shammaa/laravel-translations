<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Services;

use Shammaa\LaravelTranslations\Exceptions\InvalidLocaleException;
use Shammaa\LaravelTranslations\Exceptions\InvalidTranslationFieldException;

class TranslationValidator
{
    /**
     * Validate locale.
     *
     * @param string $locale The locale to validate
     * @param bool $throwException Whether to throw exception on invalid locale
     * @return bool
     * @throws InvalidLocaleException
     */
    public static function validateLocale(string $locale, bool $throwException = true): bool
    {
        if (empty($locale)) {
            if ($throwException) {
                throw new InvalidLocaleException($locale);
            }
            return false;
        }

        $supportedLocales = config('translations.supported_locales', ['ar', 'en', 'fr']);
        
        if (!in_array($locale, $supportedLocales, true)) {
            if ($throwException) {
                throw new InvalidLocaleException($locale, $supportedLocales);
            }
            return false;
        }

        return true;
    }

    /**
     * Validate translation field.
     *
     * @param string $field The field to validate
     * @param array $translatableFields List of translatable fields
     * @param bool $throwException Whether to throw exception on invalid field
     * @return bool
     * @throws InvalidTranslationFieldException
     */
    public static function validateField(string $field, array $translatableFields, bool $throwException = true): bool
    {
        if (empty($field)) {
            if ($throwException) {
                throw new InvalidTranslationFieldException($field, $translatableFields);
            }
            return false;
        }

        if (!in_array($field, $translatableFields, true)) {
            if ($throwException) {
                throw new InvalidTranslationFieldException($field, $translatableFields);
            }
            return false;
        }

        return true;
    }

    /**
     * Validate multiple locales.
     *
     * @param array $locales The locales to validate
     * @param bool $throwException Whether to throw exception on invalid locale
     * @return bool
     * @throws InvalidLocaleException
     */
    public static function validateLocales(array $locales, bool $throwException = true): bool
    {
        foreach ($locales as $locale) {
            if (!self::validateLocale($locale, $throwException)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate multiple fields.
     *
     * @param array $fields The fields to validate
     * @param array $translatableFields List of translatable fields
     * @param bool $throwException Whether to throw exception on invalid field
     * @return bool
     * @throws InvalidTranslationFieldException
     */
    public static function validateFields(array $fields, array $translatableFields, bool $throwException = true): bool
    {
        foreach ($fields as $field) {
            if (!self::validateField($field, $translatableFields, $throwException)) {
                return false;
            }
        }

        return true;
    }
}

