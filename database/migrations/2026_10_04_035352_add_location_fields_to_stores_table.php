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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('address')->nullable()->after('tagline');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // Update toko yang sudah ada dengan koordinat pusat operasional resmi
        \Illuminate\Support\Facades\DB::table('stores')->where('slug', 'eats')->update([
            'address' => 'Jl. Pandanaran No. 58, Mugassari, Semarang Selatan, Kota Semarang',
            'latitude' => -6.989720,
            'longitude' => 110.421930,
        ]);

        \Illuminate\Support\Facades\DB::table('stores')->where('slug', 'hampers')->update([
            'address' => 'Jl. Pemuda No. 142, Sekayu, Semarang Tengah, Kota Semarang',
            'latitude' => -6.973050,
            'longitude' => 110.428510,
        ]);

        \Illuminate\Support\Facades\DB::table('stores')->where('slug', 'beton')->update([
            'address' => 'Kawasan Industri Candi Blok 8 No. 12, Ngaliyan, Kota Semarang',
            'latitude' => -6.987540,
            'longitude' => 110.345020,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['address', 'latitude', 'longitude']);
        });
    }
};
