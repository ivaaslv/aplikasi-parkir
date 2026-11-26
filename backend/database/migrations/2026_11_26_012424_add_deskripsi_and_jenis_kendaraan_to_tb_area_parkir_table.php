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
        Schema::table('tb_area_parkir', function (Blueprint $table) {
            $table->string('deskripsi', 255)->nullable()->after('nama_area');

            $table->enum('jenis_kendaraan', ['motor', 'mobil', 'lainnya', 'semua'])->default('semua')->after('deskripsi'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_area_parkir', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'jenis_kendaraan']);
        });
    }
};
