<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class KendaraanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'plat_nomor' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:20'],
            'jenis_kendaraan' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:50'],
            'warna' => ['nullable', 'string', 'max:30'],
            'pemilik' => ['nullable', 'string', 'max:100'],
            'id_user' => ['nullable', 'integer', 'exists:tb_user,id_user'],
        ];
    }

    public function messages(): array
    {
        return [
            'plat_nomor.required' => 'Plat nomor wajib diisi.',
            'jenis_kendaraan.required' => 'Jenis kendaraan wajib diisi.',
            'id_user.exists' => 'User pencatat tidak ditemukan.',
        ];
    }
}