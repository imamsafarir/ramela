<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('discount_type', 20); // percent, nominal
            $table->decimal('discount_value', 15, 2);
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->decimal('min_purchase', 15, 2)->default(0);
            $table->unsignedInteger('quota')->nullable(); // null = tak terbatas
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Scope promo. Tidak ada baris = berlaku global (semua toko).
        Schema::create('promo_store', function (Blueprint $table) {
            $table->foreignId('promo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->primary(['promo_id', 'store_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('store_id')->constrained();
            $table->decimal('total_amount', 15, 2);
            $table->foreignId('promo_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->unsignedInteger('total_weight')->default(0);
            $table->string('shipping_pricing_type', 20)->nullable();
            $table->decimal('final_amount', 15, 2);
            $table->string('status', 20)->default('pending'); // lihat App\Enums\OrderStatus
            $table->string('delivery_type')->default('courier');
            // Snapshot alamat tujuan (tidak berubah walau alamat user diedit)
            $table->string('recipient_name');
            $table->string('recipient_phone', 20);
            $table->string('shipping_city')->nullable();
            $table->string('shipping_district')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->text('shipping_address');
            $table->decimal('shipping_latitude', 10, 7)->nullable();
            $table->decimal('shipping_longitude', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name'); // snapshot
            $table->unsignedInteger('quantity');
            $table->decimal('price_at_transaction', 15, 2);
            $table->decimal('subtotal', 15, 2);
        });

        Schema::create('transaction_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('promo_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 15, 2);
            $table->timestamp('used_at')->useCurrent();
            $table->timestamp('cancelled_at')->nullable();
            $table->index(['promo_id', 'user_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('promo_logs');
        Schema::dropIfExists('transaction_status_logs');
        Schema::dropIfExists('transaction_details');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('promo_store');
        Schema::dropIfExists('promos');
    }
};
