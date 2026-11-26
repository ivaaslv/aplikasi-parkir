<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaParkirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $areaId = $this->route('area_parkir');

        $terisiRules = ['nullable', 'integer', 'min:0'];
        if (! $isUpdate) {
            $terisiRules[] = 'lte:kapasitas';
        }

        return [
            'nama_area' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:100',
                Rule::unique('tb_area_parkir', 'nama_area')->ignore($areaId, 'id_area'),
            ],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'jenis_kendaraan' => [$isUpdate ? 'sometimes' : 'required', 'in:motor,mobil,lainnya,semua'],
            'kapasitas' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1'],
            'terisi' => $terisiRules,
        ];
    }

    public function messages(): array
    {
        return [
            'nama_area.required' => 'Nama area parkir wajib diisi.',
            'nama_area.unique' => 'Nama area parkir sudah terdaftar.',
            'kapasitas.required' => 'Kapasitas area wajib diisi.',
            'kapasitas.min' => 'Kapasitas minimal 1 slot.',
            'terisi.min' => 'Jumlah slot terisi tidak boleh kurang dari 0.',
            'terisi.lte' => 'Jumlah slot terisi tidak boleh melebihi kapasitas.',
        ];
    }
}