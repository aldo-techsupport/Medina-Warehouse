<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shopee_settings', function (Blueprint $table) {
            $table->string('api_url', 500)->nullable()->after('id');
            $table->text('api_key')->nullable()->after('api_url');
            $table->boolean('remote_connected')->default(false)->after('is_active');
            $table->text('remote_auth_url')->nullable()->after('environment');
            $table->string('remote_dashboard_url', 500)->nullable()->after('remote_auth_url');
        });

        // Clear any legacy direct Shopee credentials from the frontend database
        DB::table('shopee_settings')->update([
            'partner_id' => null,
            'partner_key' => null,
            'access_token' => null,
            'refresh_token' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopee_settings', function (Blueprint $table) {
            $table->dropColumn([
                'api_url',
                'api_key',
                'remote_connected',
                'remote_auth_url',
                'remote_dashboard_url',
            ]);
        });
    }
};
