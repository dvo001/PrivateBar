<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('users', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('name');
            $t->string('email')->unique();
            $t->dateTime('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        $this->create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
        foreach (['invitations', 'password_reset_tokens'] as $name) {
            $this->create($name, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('email')->index();
                $t->string('token_hash', 64)->unique();
                $t->dateTime('expires_at');
                $t->dateTime('used_at')->nullable();
                $t->dateTime('revoked_at')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }
        $this->create('access_attempts', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->unsignedInteger('failures')->default(0);
            $t->dateTime('locked_until')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['access_attempts', 'password_reset_tokens', 'invitations', 'sessions', 'users'] as $table) {
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
