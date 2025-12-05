<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shammaa\LaravelTranslations\Exceptions\InvalidLocaleException;
use Shammaa\LaravelTranslations\Exceptions\InvalidTranslationFieldException;
use Shammaa\LaravelTranslations\Services\TranslationValidator;

class TranslationValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set config for testing
        config([
            'translations.supported_locales' => ['ar', 'en', 'fr'],
        ]);
    }

    public function test_validate_locale_success(): void
    {
        $this->assertTrue(TranslationValidator::validateLocale('ar'));
        $this->assertTrue(TranslationValidator::validateLocale('en'));
        $this->assertTrue(TranslationValidator::validateLocale('fr'));
    }

    public function test_validate_locale_throws_exception(): void
    {
        $this->expectException(InvalidLocaleException::class);
        TranslationValidator::validateLocale('invalid');
    }

    public function test_validate_locale_returns_false_without_exception(): void
    {
        $this->assertFalse(TranslationValidator::validateLocale('invalid', false));
    }

    public function test_validate_field_success(): void
    {
        $fields = ['title', 'slug', 'content'];
        $this->assertTrue(TranslationValidator::validateField('title', $fields));
        $this->assertTrue(TranslationValidator::validateField('slug', $fields));
    }

    public function test_validate_field_throws_exception(): void
    {
        $this->expectException(InvalidTranslationFieldException::class);
        TranslationValidator::validateField('invalid', ['title', 'slug']);
    }

    public function test_validate_locales_success(): void
    {
        $this->assertTrue(TranslationValidator::validateLocales(['ar', 'en']));
    }

    public function test_validate_locales_throws_exception(): void
    {
        $this->expectException(InvalidLocaleException::class);
        TranslationValidator::validateLocales(['ar', 'invalid']);
    }

    public function test_validate_fields_success(): void
    {
        $fields = ['title', 'slug', 'content'];
        $this->assertTrue(TranslationValidator::validateFields(['title', 'slug'], $fields));
    }

    public function test_validate_fields_throws_exception(): void
    {
        $this->expectException(InvalidTranslationFieldException::class);
        TranslationValidator::validateFields(['title', 'invalid'], ['title', 'slug']);
    }
}

