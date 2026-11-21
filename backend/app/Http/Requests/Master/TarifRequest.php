<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TarifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $tarifId = $this->route('tarif');

        return [
            'jenis_kendaraan' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('tb_tarif', 'jenis_kendaraan')->ignore($tarifId, 'id_tarif'),
            ],
            'tarif_per_jam' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_kendaraan.required' => 'Jenis kendaraan wajib diisi.',
            'jenis_kendaraan.unique' => 'Tarif untuk jenis kendaraan ini sudah ada.',
            'tarif_per_jam.required' => 'Tarif per jam wajib diisi.',
            'tarif_per_jam.numeric' => 'Tarif per jam harus berupa angka.',
            'tarif_per_jam.min' => 'Tarif per jam tidak boleh kurang dari 0.',
        ];
    }
}