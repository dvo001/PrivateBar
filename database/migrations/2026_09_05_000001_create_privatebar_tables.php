<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('ingredient_categories', function (Blueprint $t) {
            $t->string('id', 40)->primary();
            $t->string('name');
            $t->decimal('typical_abv', 5, 2)->nullable();
        });
        $this->create('ingredients', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->string('category_id', 40);
            $t->foreign('category_id')->references('id')->on('ingredient_categories');
            $t->boolean('automatic')->default(false);
            $t->timestamps();
        });
        $this->create('ingredient_synonyms', function (Blueprint $t) {
            $t->string('name')->primary();
            $t->foreignUuid('ingredient_id')->constrained()->cascadeOnDelete();
        });
        $this->create('ingredient_substitutions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('required_id')->constrained('ingredients');
            $t->foreignUuid('replacement_id')->constrained('ingredients');
            $t->boolean('enabled')->default(true);
            $t->unique(['required_id', 'replacement_id']);
            $t->timestamps();
        });
        $this->create('products', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('barcode', 32)->nullable()->unique();
            $t->string('name');
            $t->string('brand')->nullable();
            $t->string('image_path')->nullable();
            $t->decimal('abv', 5, 2)->nullable();
            $t->boolean('generic')->default(false);
            $t->boolean('manually_corrected')->default(false);
            $t->string('source')->nullable();
            $t->text('license')->nullable();
            $t->timestamps();
        });
        $this->create('product_ingredient_mappings', function (Blueprint $t) {
            $t->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('ingredient_id')->constrained();
            $t->boolean('manual')->default(true);
            $t->primary(['product_id', 'ingredient_id']);
            $t->index('ingredient_id');
        });
        $this->create('bar_inventory', function (Blueprint $t) {
            $t->foreignUuid('product_id')->primary()->constrained()->cascadeOnDelete();
            $t->dateTime('created_at');
        });
        $this->create('recipes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->index();
            $t->string('fingerprint', 64)->nullable()->index();
            $t->text('instructions');
            $t->text('original_text')->nullable();
            $t->string('original_language', 10)->default('de');
            $t->boolean('translation_manual')->default(false);
            $t->boolean('translation_pending')->default(false);
            $t->boolean('household')->default(true);
            $t->uuid('parent_id')->nullable();
            $t->boolean('hidden')->default(false)->index();
            $t->boolean('alcoholic')->default(true)->index();
            $t->string('base_spirit')->nullable()->index();
            $t->string('taste')->nullable()->index();
            $t->string('method')->nullable()->index();
            $t->string('glass')->nullable();
            $t->string('image_path')->nullable();
            $t->dateTime('imported_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        $this->create('recipe_ingredients', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('ingredient_id')->constrained();
            $t->decimal('amount', 10, 3)->nullable();
            $t->string('unit', 30)->nullable();
            $t->string('original_measure')->nullable();
            $t->string('role', 20)->default('required');
            $t->unsignedInteger('position')->default(0);
            $t->index(['recipe_id', 'position']);
        });
        $this->create('recipe_sources', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 40);
            $t->string('external_id');
            $t->string('url');
            $t->text('license');
            $t->longText('original_json');
            $t->string('source_hash', 64);
            $t->dateTime('imported_at');
            $t->unique(['provider', 'external_id']);
        });
        $this->create('recipe_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->longText('snapshot');
            $t->string('actor');
            $t->dateTime('created_at');
            $t->index(['recipe_id', 'version']);
        });
        $this->create('recipe_ratings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $t->uuid('user_uuid');
            $t->unsignedTinyInteger('stars');
            $t->timestamps();
            $t->unique(['recipe_id', 'user_uuid']);
        });
        $this->create('favorites', function (Blueprint $t) {
            $t->foreignUuid('recipe_id')->primary()->constrained()->cascadeOnDelete();
            $t->dateTime('created_at');
        });
        $this->create('shopping_list_items', function (Blueprint $t) {
            $t->foreignUuid('ingredient_id')->primary()->constrained()->cascadeOnDelete();
            $t->dateTime('created_at');
        });
        foreach (['local_settings', 'shared_settings'] as $name) {
            $this->create($name, function (Blueprint $t) use ($name) {
                if ($name === 'shared_settings') {
                    $t->uuid('id')->unique();
                }
                $t->string('key', 100)->primary();
                $t->text('value');
                $t->timestamps();
            });
        }
        $this->create('devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('token_hash', 64)->unique();
            $t->dateTime('revoked_at')->nullable();
            $t->dateTime('last_seen_at')->nullable();
            $t->timestamps();
        });
        $this->create('sync_events', function (Blueprint $t) {
            $t->bigIncrements('sequence');
            $t->uuid('id')->unique();
            $t->string('entity', 40);
            $t->string('entity_id', 100);
            $t->longText('payload');
            $t->boolean('deleted')->default(false);
            $t->unsignedBigInteger('version')->default(0);
            $t->string('actor');
            $t->string('origin');
            $t->dateTime('confirmed_at')->nullable();
            $t->dateTime('created_at');
            $t->index(['confirmed_at', 'sequence']);
            $t->index(['entity', 'entity_id']);
        });
        $this->create('sync_inbox', function (Blueprint $t) {
            $t->uuid('event_id')->primary();
            $t->unsignedBigInteger('sequence');
            $t->dateTime('created_at');
        });
        $this->create('sync_cursors', function (Blueprint $t) {
            $t->string('peer')->primary();
            $t->unsignedBigInteger('cursor')->default(0);
            $t->uuid('epoch')->nullable();
        });
        $this->create('sync_tombstones', function (Blueprint $t) {
            $t->string('entity', 40);
            $t->string('entity_id', 100);
            $t->unsignedBigInteger('version');
            $t->dateTime('created_at');
            $t->primary(['entity', 'entity_id']);
        });
        $this->create('audit_entries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('actor');
            $t->string('action');
            $t->string('entity_id')->nullable();
            $t->longText('details');
            $t->dateTime('created_at');
            $t->index('created_at');
        });
        $this->create('photo_cache', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->text('source_path');
            $t->string('cache_path');
            $t->unsignedBigInteger('bytes');
            $t->unsignedBigInteger('source_mtime');
            $t->dateTime('last_used_at')->index();
            $t->timestamps();
        });
        $this->create('translation_cache', function (Blueprint $t) {
            $t->string('hash', 64)->primary();
            $t->text('translation');
            $t->dateTime('created_at');
        });
        $this->create('provider_cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->longText('payload');
            $t->dateTime('expires_at');
        });
        $this->create('random_history', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $t->dateTime('created_at');
        });
    }

    public function down(): void
    {
        foreach (['random_history', 'provider_cache', 'translation_cache', 'photo_cache', 'audit_entries', 'sync_tombstones', 'sync_cursors', 'sync_inbox', 'sync_events', 'devices', 'shared_settings', 'local_settings', 'shopping_list_items', 'favorites', 'recipe_ratings', 'recipe_versions', 'recipe_sources', 'recipe_ingredients', 'recipes', 'bar_inventory', 'product_ingredient_mappings', 'products', 'ingredient_substitutions', 'ingredient_synonyms', 'ingredients', 'ingredient_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function create(string $name, Closure $definition): void
    {
        // Ein abgebrochener Erstaufbau kann auf MariaDB ohne doppelte Tabellen fortgesetzt werden.
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }
};
