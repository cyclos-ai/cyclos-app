<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_api_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('carrier_scac', 10)->index();
            $table->string('carrier_name');
            $table->string('auth_type')->default('api_key');  // api_key, oauth2, consumer_key
            $table->text('api_key')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('consumer_key')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('environment')->default('production');  // sandbox, production
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();  // rate limits, custom URLs, etc.
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['carrier_scac']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_api_credentials');
    }
};
