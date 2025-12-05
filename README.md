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
- ✅ **Validation & Error Handling** - Comprehensive validation and error handling
- ✅ **Fully Tested** - Unit and integration tests included
- ✅ **Type Safe** - Full type hints and strict types
- ✅ **Logging** - Automatic logging for errors and warnings

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
    
    // ✅ The model automatically follows app()->getLocale() (site locale)!
    // ❌ Don't use $translationLocale - it's deprecated and ignored!
}
```

### 2. Set Locale in Middleware (Auto-follow site locale)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // Detect locale from URL, session, etc.
        $locale = $request->segment(1) ?? session('locale', 'ar');
        
        if (in_array($locale, ['ar', 'en'])) {
            app()->setLocale($locale);
        }
        
        return $next($request);
    }
}
```

**Now the model automatically follows the site locale!** ✅

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

// Access translations (auto-detects current locale)
app()->setLocale('en');
echo $article->title; // "Article Title" ✅

app()->setLocale('ar');
echo $article->title; // "عنوان المقال" ✅

// Set locale for this instance (overrides app locale)
$article->setLocale('ar');
echo $article->title; // "عنوان المقال" ✅


// Query
$articles = Article::whereTranslationLike('title', 'Laravel')->get();
```

## Usage Examples

### Multiple Languages Support

**Option 1: Dynamic (Recommended - follows app locale)**
```php
// In Model - don't set translationLocale
class Article extends Model
{
    use IsTranslatable;
    protected $translatable = ['title', 'slug', 'content'];
    // No translationLocale - automatically follows app()->getLocale()
}

// Usage
app()->setLocale('en');
echo $article->title; // "Article Title" ✅

app()->setLocale('ar');
echo $article->title; // "عنوان المقال" ✅
```

**Option 2: translationLocale property (Deprecated - DON'T USE!)**
```php
// ⚠️ DON'T USE THIS! This property is deprecated and ignored.
// The model ALWAYS follows app()->getLocale() automatically.
// ❌ Don't use: protected $translationLocale = '...'; 

// Just remove it - the model will work perfectly without it:
class Article extends Model
{
    use IsTranslatable;
    protected $translatable = ['title', 'slug', 'content'];
    // No translationLocale needed! ✅ The model follows app()->getLocale() automatically!
}

// Usage - ALWAYS follows app locale automatically!
app()->setLocale('en');
echo $article->title; // "Article Title" ✅ (follows site locale!)

app()->setLocale('ar');
echo $article->title; // "عنوان المقال" ✅ (follows site locale!)
```

**Option 3: Dynamic per instance**
```php
$article->setLocale('ar');
echo $article->title; // "عنوان المقال" ✅

$article->setLocale('en');
echo $article->title; // "Article Title" ✅
```

**Priority order:** `setLocale()` > `app()->getLocale()` (site locale) **ALWAYS!**

**Important:** 
- ✅ The model **ALWAYS follows the site locale** (`app()->getLocale()`) automatically!
- ❌ **Don't use `$translationLocale` property** - it's deprecated and completely ignored!
- ✅ Just set the locale in your middleware/controller: `app()->setLocale('ar')` or `app()->setLocale('en')`
- ✅ The model will automatically use the current site locale - no configuration needed!

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

## Testing

Run the test suite:

```bash
composer test
# or
vendor/bin/phpunit
```

The package includes:
- ✅ Unit tests for validation and exceptions
- ✅ Integration tests for trait functionality
- ✅ Full test coverage

## Requirements

- PHP >= 8.1
- Laravel >= 9.0

## License

MIT License

## Author

Shadi Shammaa

