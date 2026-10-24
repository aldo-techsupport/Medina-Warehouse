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
            $table->index('order_status', 'shopee_orders_order_status_index');
            $table->index('created_at', 'shopee_orders_created_at_index');
            $table->index('updated_at', 'shopee_orders_updated_at_index');
            $table->index(['order_status', 'created_at'], 'shopee_orders_status_created_at_index');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->index('created_at', 'stock_mutations_created_at_index');
            $table->index(['type', 'created_at'], 'stock_mutations_type_created_at_index');
        });

        Schema::table('packing_records', function (Blueprint $table) {
            $table->index('created_at', 'packing_records_created_at_index');
            $table->index(['status', 'created_at'], 'packing_records_status_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packing_records', function (Blueprint $table) {
            $table->dropIndex('packing_records_status_created_at_index');
            $table->dropIndex('packing_records_created_at_index');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropIndex('stock_mutations_type_created_at_index');
            $table->dropIndex('stock_mutations_created_at_index');
        });

        Schema::table('shopee_orders', function (Blueprint $table) {
            $table->dropIndex('shopee_orders_status_created_at_index');
            $table->dropIndex('shopee_orders_updated_at_index');
            $table->dropIndex('shopee_orders_created_at_index');
            $table->dropIndex('shopee_orders_order_status_index');
        });
    }
};
