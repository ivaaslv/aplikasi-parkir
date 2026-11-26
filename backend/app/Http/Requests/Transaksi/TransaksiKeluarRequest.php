<?php

namespace App\Http\Requests\Transaksi;

use Illuminate\Foundation\Http\FormRequest;

class TransaksiKeluarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_parkir' => ['nullable', 'integer', 'exists:tb_transaksi,id_parkir', 'required_without:plat_nomor'],
            'plat_nomor' => ['nullable', 'string', 'max:20', 'required_without:id_parkir'],
            'karcis_hilang' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_parkir.exists' => 'Data transaksi parkir tidak ditemukan.',
            'id_parkir.required_without' => 'Isi id_parkir atau plat_nomor kendaraan yang keluar.',
            'plat_nomor.required_without' => 'Isi plat_nomor atau id_parkir kendaraan yang keluar.',
        ];
    }
}