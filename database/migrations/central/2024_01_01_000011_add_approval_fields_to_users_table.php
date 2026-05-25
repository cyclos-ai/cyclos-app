<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('users', function (Blueprint $table) {
            $table->string('approval_status')->default('approved'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('company_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('users', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'rejection_reason', 'approved_by', 'approved_at', 'company_name']);
        });
    }
};
