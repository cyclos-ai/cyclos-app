<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('container_number')->index();
            $table->uuid('mbl_id')->nullable();
            $table->uuid('booking_id')->nullable();
            $table->uuid('vessel_id')->nullable();
            $table->string('status')->index()->default('NOT_TRACKING');
            $table->string('size')->nullable();
            $table->string('type')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('weight_unit')->default('KG');
            $table->string('seal_number')->nullable();
            $table->string('carrier_scac')->nullable();
            $table->string('shipping_line')->nullable();
            $table->string('pol')->nullable();
            $table->string('pod')->nullable();
            $table->string('final_destination')->nullable();
            $table->timestamp('eta')->nullable();
            $table->timestamp('ata')->nullable();
            $table->timestamp('etd')->nullable();
            $table->timestamp('atd')->nullable();
            $table->timestamp('empty_return_date')->nullable();
            $table->timestamp('outgate_date')->nullable();
            $table->timestamp('last_free_day_demurrage')->nullable();
            $table->timestamp('last_free_day_detention')->nullable();
            $table->boolean('is_priority')->default(false);
            $table->text('priority_note')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            // Foreign keys to mbls, bookings, vessels deferred — see 100045_add_deferred_foreign_keys.php
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
