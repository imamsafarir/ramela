<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembatalan pesanan mengembalikan kuota, tapi log tetap ada sebagai jejak audit.
        Schema::table('promo_logs', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('used_at');
        });
    }

    public function down(): void
    {
        Schema::table('promo_logs', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
