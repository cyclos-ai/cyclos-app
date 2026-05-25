<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supported_scacs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('scac_code');
            $table->string('carrier_name');
            $table->string('mode');
            $table->string('tracking_url_pattern')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supported_scacs');
    }
};
