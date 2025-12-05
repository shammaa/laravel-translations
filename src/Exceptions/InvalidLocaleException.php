<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Exceptions;

use InvalidArgumentException;

class InvalidLocaleException extends InvalidArgumentException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $locale, array $supportedLocales = [])
    {
        $message = "Invalid locale '{$locale}'.";
        
        if (!empty($supportedLocales)) {
            $message .= " Supported locales: " . implode(', ', $supportedLocales);
        }
        
        parent::__construct($message);
    }
}

