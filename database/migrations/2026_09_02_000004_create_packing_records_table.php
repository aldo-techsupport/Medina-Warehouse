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
        // Add tracking_number and shipping_carrier to shopee_orders if not exists
        Schema::table('shopee_orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('order_sn')->index();
            $table->string('shipping_carrier')->nullable()->after('tracking_number'); // SPX, J&T, SiCepat, dll.
        });

        // Create packing_records table
        Schema::create('packing_records', function (Blueprint $table) {
            $table->id();
            $table->string('order_sn')->index();
            $table->string('tracking_number')->nullable()->index();
            $table->foreignId('shopee_order_id')->nullable()->constrained('shopee_orders')->nullOnDelete();
            $table->enum('status', ['completed', 'blocked_cancelled', 'in_progress'])->default('completed');
            $table->string('packer_name')->default('Staff Packing');
            $table->json('items_checked')->nullable(); // detail item dan status verifikasi
            $table->string('video_path')->nullable(); // path file video di storage
            $table->integer('video_duration')->nullable(); // dalam detik
            $table->unsignedBigInteger('file_size')->nullable(); // bytes
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_records');
        Schema::table('shopee_orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_number', 'shipping_carrier']);
        });
    }
};
