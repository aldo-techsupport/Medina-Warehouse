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
        Schema::create('shopee_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('partner_key')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('shop_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->integer('expire_in')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync_stock')->default(true); // Otomatis push perubahan stok ke Shopee
            $table->string('environment')->default('sandbox'); // sandbox, production
            $table->timestamps();
        });

        Schema::create('shopee_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_sn')->unique();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('order_status')->default('READY_TO_SHIP'); // UNPAID, READY_TO_SHIP, PROCESSED, COMPLETED, CANCELLED
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('buyer_username')->nullable();
            $table->json('items_data')->nullable(); // detail item sku, qty, harga
            $table->boolean('stock_deducted')->default(false);
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopee_orders');
        Schema::dropIfExists('shopee_settings');
    }
};
