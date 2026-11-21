<?php

namespace App\Http\Requests\Laporan;

use Illuminate\Foundation\Http\FormRequest;

class LaporanPendapatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'jenis_kendaraan' => ['nullable', 'string', 'max:50'],
            'id_area' => ['nullable', 'integer', 'exists:tb_area_parkir,id_area'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_mulai.date' => 'Format tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Format tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'id_area.exists' => 'Area parkir tidak ditemukan.',
        ];
    }
}