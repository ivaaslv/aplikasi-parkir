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
            'id_parkir' => ['nullable', 'integer', 'exists:tb_transaksi,id_parkir'],
            'kode_tiket' => ['nullable', 'string', 'max:40'],
            'plat_nomor' => ['nullable', 'string', 'max:40'],
            'karcis_hilang' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasId = $this->filled('id_parkir');
            $hasKode = $this->filled('kode_tiket');
            $hasPlat = $this->filled('plat_nomor');

            if (! $hasId && ! $hasKode && ! $hasPlat) {
                $validator->errors()->add(
                    'plat_nomor',
                    'Scan barcode tiket atau masukkan nomor tiket / plat nomor kendaraan yang keluar.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id_parkir.exists' => 'Data transaksi parkir tidak ditemukan.',
        ];
    }
}