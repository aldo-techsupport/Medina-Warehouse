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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name');
            $table->string('category')->default('Umum');
            $table->string('unit')->default('Pcs');
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->integer('stock')->default(0); // Stok fisik di Gudang Utama
            $table->integer('safety_stock')->default(5); // Alert batas minimum
            $table->unsignedBigInteger('shopee_item_id')->nullable()->index();
            $table->unsignedBigInteger('shopee_model_id')->nullable()->index();
            $table->integer('shopee_stock')->default(0); // Terakhir tersinkron di Shopee
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
