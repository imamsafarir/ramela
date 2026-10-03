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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('delivery_type')->default('courier')->after('status');
            $table->decimal('shipping_cost', 12, 2)->default(0)->after('discount_amount');
            $table->string('shipping_city')->nullable()->after('recipient_phone');
            $table->string('shipping_district')->nullable()->after('shipping_city');
            $table->string('shipping_postal_code')->nullable()->after('shipping_district');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_type',
                'shipping_cost',
                'shipping_city',
                'shipping_district',
                'shipping_postal_code',
            ]);
        });
    }
};
