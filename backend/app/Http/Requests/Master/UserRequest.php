<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $userId = $this->route('user');

        return [
            'nama_lengkap' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'username' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:50',
                Rule::unique('tb_user', 'username')->ignore($userId, 'id_user'),
            ],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:6'],
            'role' => [$isUpdate ? 'sometimes' : 'required', 'in:admin,petugas,owner'],
            'status_aktif' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role harus berupa admin, petugas, atau owner.',
        ];
    }
}