<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates an optimized database view for translations.
     * The view is now simpler and faster since translations table
     * uses columns directly instead of key-value pairs.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        $translationsTable = config('translations.translations_table', 'translations');
        $viewName = config('translations.translations_view', 'translations_view');

        // Drop view if exists
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("DROP VIEW IF EXISTS `{$viewName}`");
            
            // Optimized view - now translations table has columns directly
            // No need for PIVOT, much faster queries!
            DB::statement("
                CREATE VIEW `{$viewName}` AS
                SELECT 
                    t.translatable_type,
                    t.translatable_id,
                    t.locale,
                    t.title,
                    t.slug,
                    t.description,
                    t.excerpt,
                    t.meta_title,
                    t.meta_description,
                    t.large_fields,
                    t.updated_at,
                    t.created_at
                FROM {$translationsTable} t
            ");
        } elseif ($driver === 'pgsql') {
            DB::statement("DROP VIEW IF EXISTS {$viewName} CASCADE");
            
            DB::statement("
                CREATE VIEW {$viewName} AS
                SELECT 
                    t.translatable_type,
                    t.translatable_id,
                    t.locale,
                    t.title,
                    t.slug,
                    t.description,
                    t.excerpt,
                    t.meta_title,
                    t.meta_description,
                    t.large_fields,
                    t.updated_at,
                    t.created_at
                FROM {$translationsTable} t
            ");
        } elseif ($driver === 'sqlite') {
            DB::statement("DROP VIEW IF EXISTS {$viewName}");
            
            DB::statement("
                CREATE VIEW {$viewName} AS
                SELECT 
                    t.translatable_type,
                    t.translatable_id,
                    t.locale,
                    t.title,
                    t.slug,
                    t.description,
                    t.excerpt,
                    t.meta_title,
                    t.meta_description,
                    t.large_fields,
                    t.updated_at,
                    t.created_at
                FROM {$translationsTable} t
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $viewName = config('translations.translations_view', 'translations_view');
        Schema::dropIfExists($viewName);
    }
};

