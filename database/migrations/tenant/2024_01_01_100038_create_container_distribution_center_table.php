<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_distribution_center', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('container_id');
            $table->uuid('distribution_center_id');
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('containers')->cascadeOnDelete();
            $table->foreign('distribution_center_id')->references('id')->on('distribution_centers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_distribution_center');
    }
};
