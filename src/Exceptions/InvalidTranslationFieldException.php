<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Exceptions;

use InvalidArgumentException;

class InvalidTranslationFieldException extends InvalidArgumentException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $field, array $translatableFields = [])
    {
        $message = "Field '{$field}' is not translatable.";
        
        if (!empty($translatableFields)) {
            $message .= " Translatable fields: " . implode(', ', $translatableFields);
        }
        
        parent::__construct($message);
    }
}

