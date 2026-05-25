<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrier_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('carrier_scac');
            $table->string('contract_type');
            $table->string('port_locode')->nullable();
            $table->string('terminal_firms_code')->nullable();
            $table->integer('free_days_demurrage')->default(0);
            $table->integer('free_days_detention')->default(0);
            $table->json('demurrage_rates');
            $table->json('detention_rates');
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_contracts');
    }
};
