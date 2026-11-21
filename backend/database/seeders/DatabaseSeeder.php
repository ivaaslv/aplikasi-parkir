<?php

namespace Database\Seeders;

use App\Models\AreaParkir;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::insert([
            [
                'nama_lengkap' => 'Admin Parkir',
                'username' => 'admin',
                'password' => Hash::make('password1234'),
                'role' => 'admin',
                'status_aktif' => 1,
            ],
            [
                'nama_lengkap' => 'Petugas Pos',
                'username' => 'petugas',
                'password' => Hash::make('password1234'),
                'role' => 'petugas',
                'status_aktif' => 1, 
            ],
            [
                'nama_lengkap' => 'Owner Lahan',
                'username' => 'owner',
                'password' => Hash::make('password1234'),
                'role' => 'owner',
                'status_aktif' => 1,
            ]
        ]);

        Tarif::insert([
            ['jenis_kendaraan' => 'motor', 'tarif_per_jam' => 2000],
            ['jenis_kendaraan' => 'mobil', 'tarif_per_jam' => 5000],
            ['jenis_kendaraan' => 'lainnya', 'tarif_per_jam' => 10000],
            
        ]);

        AreaParkir::insert([
            ['nama_area' => 'Area Motor A', 'kapasitas' => 100, 'terisi' => 0],
            ['nama_area' => 'Area Mobil B', 'kapasitas' => 50, 'terisi' => 0],
        ]);
    }
}
