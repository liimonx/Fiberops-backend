<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->string('status');
            $table->decimal('location_lat', 10, 7);
            $table->decimal('location_lng', 10, 7);
            $table->timestamps();

            $table->index(['organization_id', 'kind']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('plan');
            $table->string('status');
            $table->string('billing_status')->default('paid');
            $table->string('related_onu_id')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('severity');
            $table->string('status')->default('new');
            $table->string('related_asset_id')->nullable();
            $table->string('technician')->nullable();
            $table->text('notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('priority');
            $table->string('work_type');
            $table->string('status')->default('new');
            $table->string('assignee_id')->nullable();
            $table->string('related_incident_id')->nullable();
            $table->string('related_asset_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('planning_proposals', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('organization')->nullable();
            $table->json('integrations')->nullable();
            $table->json('billing')->nullable();
            $table->json('team')->nullable();
            $table->timestamps();
        });

        Schema::create('generated_reports', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('format');
            $table->string('title');
            $table->string('status')->default('ready');
            $table->string('period');
            $table->timestamp('generated_at');
            $table->string('generated_by');
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->json('download_payload')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('planning_proposals');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('assets');
    }
};
