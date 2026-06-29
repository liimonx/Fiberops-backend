<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('pppoe_username')->nullable()->after('related_onu_id');
            $table->index(['organization_id', 'pppoe_username']);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->string('monitor_host')->nullable()->after('location_lng');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'pppoe_username']);
            $table->dropColumn('pppoe_username');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('monitor_host');
        });
    }
};
