<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quickbooks_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('realm_id')->nullable(); // QBO company (realm) ID
            $table->text('access_token')->nullable(); // encrypted
            $table->text('refresh_token')->nullable(); // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('environment')->default('production'); // production | sandbox
            $table->string('company_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_connected')->default(false);
            $table->json('settings')->nullable(); // extra config
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quickbooks_credentials');
    }
};
