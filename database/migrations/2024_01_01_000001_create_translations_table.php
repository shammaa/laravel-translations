<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 255)->index();
            $table->unsignedBigInteger('translatable_id')->index();
            $table->string('locale', 10)->index();
            
            // Searchable fields (stored as columns for fast queries)
            $table->string('title')->nullable()->index();
            $table->string('slug')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            
            // Large fields stored as JSON (content, body, etc.)
            // This saves space and reduces table size significantly
            $table->json('large_fields')->nullable(); // For: content, body, full_text, etc.
            
            // Legacy key-value support for custom fields (optional)
            $table->string('key', 255)->nullable()->index();
            $table->text('value')->nullable();
            
            $table->timestamps();

            // Composite indexes for optimal performance
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'idx_translatable');
            $table->index(['locale', 'slug'], 'idx_locale_slug');
            $table->index(['locale', 'title'], 'idx_locale_title');
            $table->index(['translatable_type', 'locale'], 'idx_type_locale');
            
            // Full-text search indexes (MySQL/MariaDB)
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'])) {
                $table->fullText(['title', 'description'])->name('idx_fulltext_search');
            }

            // Unique constraint to prevent duplicates (using translatable + locale only)
            // One row per translatable per locale (much more efficient!)
            $table->unique([
                'translatable_type',
                'translatable_id',
                'locale'
            ], 'unique_translation_locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop view first if exists
        Schema::dropIfExists('translations_view');
        
        Schema::dropIfExists('translations');
    }
};

