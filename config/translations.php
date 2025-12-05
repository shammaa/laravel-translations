<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale that will be used as a fallback when a translation
    | is not available in the current locale.
    |
    */
    'default_locale' => env('APP_LOCALE', 'ar'),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of all supported locales in your application.
    |
    */
    'supported_locales' => ['ar', 'en', 'fr'],

    /*
    |--------------------------------------------------------------------------
    | Translation Table
    |--------------------------------------------------------------------------
    |
    | The name of the translations table.
    |
    */
    'translations_table' => 'translations',

    /*
    |--------------------------------------------------------------------------
    | Translation View
    |--------------------------------------------------------------------------
    |
    | The name of the optimized database view for translations.
    | This view is automatically created and maintained by the package.
    |
    */
    'translations_view' => 'translations_view',

    /*
    |--------------------------------------------------------------------------
    | Searchable Fields
    |--------------------------------------------------------------------------
    |
    | Fields that are stored as columns for fast searching and indexing.
    | These fields can be indexed and used in WHERE clauses efficiently.
    |
    */
    'searchable_fields' => [
        'title',
        'slug',
        'description',
        'excerpt',
        'meta_title',
        'meta_description',
    ],

    /*
    |--------------------------------------------------------------------------
    | Large Fields
    |--------------------------------------------------------------------------
    |
    | Fields that are stored in JSON (large_fields column) to save space.
    | These fields are not searched frequently (like content, body, etc.)
    |
    */
    'large_fields' => [
        'content',
        'body',
        'full_text',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for translation caching to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('TRANSLATIONS_CACHE_ENABLED', true),
        'prefix' => 'translations',
        'ttl' => env('TRANSLATIONS_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-detect Translatable Fields
    |--------------------------------------------------------------------------
    |
    | If enabled, the package will automatically detect translatable fields
    | from common naming conventions.
    |
    */
    'auto_detect_fields' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Translatable Fields
    |--------------------------------------------------------------------------
    |
    | Default fields that are considered translatable if auto_detect is enabled.
    |
    */
    'default_translatable_fields' => [
        'title',
        'slug',
        'content',
        'description',
        'excerpt',
        'meta_title',
        'meta_description',
    ],

    /*
    |--------------------------------------------------------------------------
    | Use Database View
    |--------------------------------------------------------------------------
    |
    | Use the optimized database view for queries. This significantly improves
    | performance for large datasets.
    |
    */
    'use_view' => env('TRANSLATIONS_USE_VIEW', true),
];
