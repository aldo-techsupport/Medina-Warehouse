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
        if (! Schema::hasColumn('shopee_orders', 'recipient_address')) {
            Schema::table('shopee_orders', function (Blueprint $table) {
                $table->json('recipient_address')->nullable()->after('items_data');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('shopee_orders', 'recipient_address')) {
            Schema::table('shopee_orders', function (Blueprint $table) {
                $table->dropColumn('recipient_address');
            });
        }
    }
};
