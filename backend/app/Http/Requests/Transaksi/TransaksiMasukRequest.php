<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;

class TransaksiMasukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plat_nomor' => ['required', 'string', 'max:20'],
            'jenis_kendaraan' => ['required', 'string', 'max:50'],
            'warna' => ['nullable', 'string', 'max:30'],
            'pemilik' => ['nullable', 'string', 'max:100'],
            'id_area' => ['required', 'integer', 'exists:tb_area_parkir,id_area'],
        ];
    }

    public function messages(): array
    {
        return [
            'plat_nomor.required' => 'Plat nomor wajib diisi.',
            'jenis_kendaraan.required' => 'Jenis kendaraan wajib diisi.',
            'id_area.required' => 'Area parkir wajib dipilih.',
            'id_area.exists' => 'Area parkir tidak ditemukan.',
        ];
    }
}