<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('mbl_id')->nullable();
            $table->uuid('container_id')->nullable();
            $table->uuid('booking_id')->nullable();
            $table->string('original_name');
            $table->string('path');                       // storage path on $disk
            $table->string('disk')->default('local');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('document_type')->nullable();  // delivery_order, bill_of_lading, ...
            $table->uuid('uploaded_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'mbl_id']);
            $table->index(['organization_id', 'container_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
