<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dashboard_id');
            $table->string('widget_type');
            $table->string('title');
            $table->string('data_source');
            $table->json('config');
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('width')->default(6);
            $table->integer('height')->default(4);
            $table->timestamps();

            $table->foreign('dashboard_id')->references('id')->on('dashboards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
