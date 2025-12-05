# Laravel Translations

A professional, high-performance multilingual translation system for Laravel with optimized database views and advanced features.

## Features

- ✅ **Single Translation Table** - One unified table for all models (no table per model!)
- ✅ **Optimized Performance** - Database views + smart caching (15-50x faster than key-value approach)
- ✅ **Columns + JSON** - Searchable fields as columns, large fields as JSON
- ✅ **Auto Locale Detection** - Automatically uses current locale
- ✅ **Scope Methods** - Built-in query scopes for easy filtering
- ✅ **Advanced Features** - Missing translations detection, cloning, statistics
- ✅ **Zero Configuration** - Works out of the box
- ✅ **Fully Cached** - Automatic caching for maximum performance

## Installation

```bash
composer require shammaa/laravel-translations
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=translations-config
php artisan vendor:publish --tag=translations-migrations
php artisan migrate
```

## Quick Start

### 1. Add Trait to Your Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Shammaa\LaravelTranslations\Traits\IsTranslatable;

class Article extends Model
{
    use IsTranslatable;

    protected $translatable = ['title', 'slug', 'content', 'description'];
}
```

### 2. Use It!

```php
// Create with translation
$article = Article::create(['status' => 1]);

// Translate (uses current locale automatically!)
$article->translateTo([
    'title' => 'Article Title',
    'slug' => 'article-slug',
    'content' => 'Article content...',
])->save();

// Access translations
echo $article->title; // Current locale automatically
echo $article->getTranslation('title', 'en'); // Specific locale

// Query
$articles = Article::whereTranslationLike('title', 'Laravel')->get();
```

## Usage Examples

### Saving Translations

```php
// Single locale (auto-detects current locale)
$article->translateTo([
    'title' => 'Article Title',
    'slug' => 'article-slug',
])->save();

// Multiple locales
$article->fillTranslations([
    'ar' => ['title' => 'عنوان المقال', 'slug' => 'maqal'],
    'en' => ['title' => 'Article Title', 'slug' => 'article'],
])->save();
```

### Querying

```php
// Search in translations
Article::whereTranslationLike('title', 'Laravel')->get();

// Filter by translation availability
Article::hasTranslation('en')->get();
Article::missingTranslation('fr')->get();

// Eager load translations
Article::withTranslations(['ar', 'en'])->get();
```

### Advanced Features

```php
// Check missing fields
$missing = $article->getMissingFields('en');

// Get translation completion
$percentage = $article->getTranslationCompletion('en'); // 75.5%

// Clone translations
$article->cloneTranslations('ar', 'en')->save();

// Get statistics
$stats = Article::translationStats();
// ['ar' => 1000, 'en' => 800]
```

## Performance

The package is highly optimized:

- **Single item**: 0.1-1ms (with cache)
- **100 items**: 50-100ms
- **1000 items**: 400-600ms
- **15-50x faster** than key-value approach
- **99% fewer queries**

Run benchmark:

```bash
php artisan translations:benchmark --model="App\Models\Article" --count=100
```

## Configuration

Edit `config/translations.php`:

```php
'default_locale' => 'ar',
'supported_locales' => ['ar', 'en', 'fr'],
'searchable_fields' => ['title', 'slug', 'description', ...],
'large_fields' => ['content', 'body', ...],
'cache' => [
    'enabled' => true,
    'ttl' => 3600,
],
```

## Requirements

- PHP >= 8.1
- Laravel >= 9.0

## License

MIT License

## Author

Shadi Shammaa

