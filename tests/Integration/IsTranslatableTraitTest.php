<?php

declare(strict_types=1);

namespace Shammaa\LaravelTranslations\Tests\Integration;

use Illuminate\Database\Eloquent\Model;
use Shammaa\LaravelTranslations\Exceptions\InvalidLocaleException;
use Shammaa\LaravelTranslations\Tests\TestCase;
use Shammaa\LaravelTranslations\Traits\IsTranslatable;

class IsTranslatableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test model table
        \Illuminate\Support\Facades\Schema::create('articles', function ($table) {
            $table->id();
            $table->integer('status')->default(0);
            $table->timestamps();
        });
    }

    public function test_model_can_use_trait(): void
    {
        $article = new TestArticle();
        $article->status = 1;
        $article->save();

        $this->assertInstanceOf(TestArticle::class, $article);
    }

    public function test_set_and_get_translation(): void
    {
        $article = new TestArticle();
        $article->status = 1;
        $article->save();

        $article->setTranslation('title', 'Test Title', 'en');
        $article->saveTranslations();

        $translation = $article->getTranslation('title', 'en');
        $this->assertEquals('Test Title', $translation);
    }

    public function test_magic_getter_returns_translation(): void
    {
        app()->setLocale('en');
        
        $article = new TestArticle();
        $article->status = 1;
        $article->save();

        $article->setTranslation('title', 'Test Title', 'en');
        $article->saveTranslations();

        $this->assertEquals('Test Title', $article->title);
    }

    public function test_set_locale_override(): void
    {
        app()->setLocale('en');
        
        $article = new TestArticle();
        $article->status = 1;
        $article->save();

        $article->setTranslation('title', 'English Title', 'en');
        $article->setTranslation('title', 'Arabic Title', 'ar');
        $article->saveTranslations();

        // Should return English by default
        $this->assertEquals('English Title', $article->title);

        // Override to Arabic
        $article->setLocale('ar');
        $this->assertEquals('Arabic Title', $article->title);
    }

    public function test_invalid_locale_throws_exception(): void
    {
        $this->expectException(InvalidLocaleException::class);
        
        $article = new TestArticle();
        $article->setTranslation('title', 'Test', 'invalid');
    }

    public function test_fallback_to_default_locale(): void
    {
        app()->setLocale('fr'); // Not supported
        
        $article = new TestArticle();
        $article->status = 1;
        $article->save();

        $article->setTranslation('title', 'Arabic Title', 'ar');
        $article->saveTranslations();

        // Should fallback to default locale (ar)
        $this->assertEquals('Arabic Title', $article->title);
    }
}

class TestArticle extends Model
{
    use IsTranslatable;

    protected $table = 'articles';

    protected $fillable = ['status'];

    protected $translatable = ['title', 'slug', 'content'];
}

