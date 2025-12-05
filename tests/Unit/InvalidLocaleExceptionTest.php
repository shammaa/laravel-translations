<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shammaa\LaravelTranslations\Exceptions\InvalidLocaleException;

class InvalidLocaleExceptionTest extends TestCase
{
    public function test_exception_message_without_supported_locales(): void
    {
        $exception = new InvalidLocaleException('invalid');
        $this->assertEquals("Invalid locale 'invalid'.", $exception->getMessage());
    }

    public function test_exception_message_with_supported_locales(): void
    {
        $exception = new InvalidLocaleException('invalid', ['ar', 'en']);
        $this->assertStringContainsString("Invalid locale 'invalid'", $exception->getMessage());
        $this->assertStringContainsString('ar, en', $exception->getMessage());
    }
}

