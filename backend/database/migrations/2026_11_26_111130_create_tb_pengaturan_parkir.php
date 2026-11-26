<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tb_pengaturan_parkir', function (Blueprint $table) {
            $table->increments('id_pengaturan');
            $table->decimal('denda_karcis_hilang', 10, 0)->default(25000);
            $table->enum('skema_denda', ['tambahan', 'pengganti'])->default('tambahan');
            $table->timestamps();
        });

        DB::table('tb_pengaturan_parkir')->insert([
            'denda_karcis_hilang' => 25000,
            'skema_denda' => 'tambahan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_pengaturan_parkir');
    }
};