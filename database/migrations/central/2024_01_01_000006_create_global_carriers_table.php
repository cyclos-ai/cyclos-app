<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_carriers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('scac_code')->unique();
            $table->string('type');
            $table->boolean('tracking_supported')->default(false);
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_carriers');
    }
};
