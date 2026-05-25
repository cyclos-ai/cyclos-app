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
            $table->string('carrier_scac', 10);
            $table->string('carrier_name');
            $table->string('auth_type');               // api_key, consumer_key, oauth2
            $table->text('api_key')->nullable();
            $table->text('consumer_key')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('api_url')->nullable();
            $table->string('sandbox_url')->nullable();
            $table->string('environment')->default('production');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('carrier_scac');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_api_credentials');
    }
};
