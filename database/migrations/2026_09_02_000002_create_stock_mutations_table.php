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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('type', [
                'inbound',            // Barang Masuk (Pembelian/Penerimaan)
                'outbound',           // Barang Keluar (Manual)
                'adjustment',         // Stok Opname / Koreksi
                'shopee_sale',        // Penjualan Shopee Otomatis
                'shopee_cancellation', // Pembatalan Pesanan Shopee (Restock)
            ])->index();
            $table->integer('qty'); // Positif untuk penambahan, negatif untuk pengurangan
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('notes')->nullable();
            $table->string('actor')->default('System');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
