<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n8n_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('host_url')->default('http://localhost:5678');
            $table->text('api_key')->nullable(); // encrypted
            $table->string('webhook_base_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_connected')->default(false);
            $table->json('settings')->nullable(); // extra config
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_status')->nullable(); // healthy, unreachable, auth_failed
            $table->timestamps();
        });

        Schema::create('n8n_workflow_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('n8n_integration_id')->constrained('n8n_integrations')->cascadeOnDelete();
            $table->string('n8n_workflow_id'); // ID in n8n
            $table->string('name');
            $table->string('template_key')->nullable(); // references config template key
            $table->string('trigger_event'); // which Cyclos event triggers this
            $table->string('webhook_url')->nullable(); // n8n webhook URL for this workflow
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('last_executed_at')->nullable();
            $table->integer('execution_count')->default(0);
            $table->timestamps();

            $table->index('trigger_event');
            $table->index('n8n_workflow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n8n_workflow_mappings');
        Schema::dropIfExists('n8n_integrations');
    }
};
