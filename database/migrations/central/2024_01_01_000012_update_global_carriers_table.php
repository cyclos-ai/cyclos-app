<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_carriers', function (Blueprint $table) {
            $table->renameColumn('scac_code', 'scac');
            $table->renameColumn('type', 'api_type');
            $table->renameColumn('tracking_supported', 'supports_tracking');
        });

        Schema::table('global_carriers', function (Blueprint $table) {
            $table->string('group_name')->nullable()->after('name');
            $table->string('tracking_url')->nullable()->after('api_type');
            $table->json('aliases')->nullable()->after('website');
            $table->boolean('is_active')->default(true)->after('aliases');
        });
    }

    public function down(): void
    {
        Schema::table('global_carriers', function (Blueprint $table) {
            $table->dropColumn(['group_name', 'tracking_url', 'aliases', 'is_active']);
        });

        Schema::table('global_carriers', function (Blueprint $table) {
            $table->renameColumn('scac', 'scac_code');
            $table->renameColumn('api_type', 'type');
            $table->renameColumn('supports_tracking', 'tracking_supported');
        });
    }
};
