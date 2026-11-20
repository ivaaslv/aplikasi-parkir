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
        Schema::create('tb_transaksi', function (Blueprint $table) {
            $table->increments('id_parkir');
            $table->unsignedInteger('id_kendaraan');
            $table->dateTime('waktu_masuk');
            $table->dateTime('waktu_keluar')->nullable();
            $table->unsignedInteger('id_tarif');
            $table->integer('durasi_jam')->default(0);
            $table->decimal('biaya_total', 10, 0)->default(0);
            $table->enum('status', ['masuk', 'keluar'])->default('masuk');
            $table->unsignedInteger('id_user');
            $table->unsignedInteger('id_area');
            $table->timestamps();

            $table->foreign('id_kendaraan')->references('id_kendaraan')->on('tb_kendaraan')->onDelete('cascade');
            $table->foreign('id_tarif')->references('id_tarif')->on('tb_tarif')->onDelete('restrict');
            $table->foreign('id_user')->references('id_user')->on('tb_user')->onDelete('restrict');
            $table->foreign('id_area')->references('id_area')->on('tb_area_parkir')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_transaksi');
    }
};
