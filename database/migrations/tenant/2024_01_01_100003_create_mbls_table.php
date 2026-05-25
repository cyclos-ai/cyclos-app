<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mbls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('mbl_number')->index();
            $table->string('carrier_scac')->nullable();
            $table->uuid('vessel_id')->nullable();
            $table->string('pol')->nullable();
            $table->string('pod')->nullable();
            $table->timestamp('etd')->nullable();
            $table->timestamp('eta')->nullable();
            $table->timestamp('atd')->nullable();
            $table->timestamp('ata')->nullable();
            $table->integer('container_count')->default(0);
            $table->string('status')->default('active');
            $table->string('shipper_name')->nullable();
            $table->string('consignee_name')->nullable();
            $table->string('notify_party')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('vessel_id')->references('id')->on('vessels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mbls');
    }
};
