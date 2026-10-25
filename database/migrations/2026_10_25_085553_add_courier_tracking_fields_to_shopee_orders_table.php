<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shopee_orders', function (Blueprint $table) {
            $table->string('logistics_status')->nullable()->after('shipping_carrier')->index();
            $table->json('tracking_data')->nullable()->after('logistics_status');
            $table->timestamp('shipped_at')->nullable()->after('tracking_data')->index();
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('last_tracked_at')->nullable()->after('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopee_orders', function (Blueprint $table) {
            $table->dropColumn([
                'logistics_status',
                'tracking_data',
                'shipped_at',
                'delivered_at',
                'last_tracked_at',
            ]);
        });
    }
};
