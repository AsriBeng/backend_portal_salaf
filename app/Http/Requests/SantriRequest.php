<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\SantriStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SantriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $santri = $this->route('santri');
        $req = $santri ? 'sometimes' : 'required';

        return [
            'nis' => [$req, 'string', 'max:50', 'alpha_dash', Rule::unique('santri')->ignore($santri), Rule::unique('users', 'username')->ignore($santri?->user_id)],
            // Kosongkan untuk memakai tanggal lahir (ddmmyyyy) sebagai password awal
            'password' => [$santri ? 'prohibited' : 'nullable', Password::min(6)],

            'nama_lengkap' => [$req, 'string', 'max:255'],
            'nik' => ['nullable', 'digits:16', Rule::unique('santri')->ignore($santri)],
            'no_kk' => ['nullable', 'digits:16'],
            'tempat_lahir' => [$req, 'string', 'max:100'],
            'tanggal_lahir' => [$req, 'date', 'before:today'],
            'jenis_kelamin' => [$santri ? 'prohibited' : 'nullable', Rule::enum(Gender::class)],
            'agama' => ['sometimes', 'string', 'max:30'],
            'kewarganegaraan' => ['sometimes', 'string', 'max:30'],
            'anak_ke' => ['nullable', 'integer', 'min:1', 'max:30'],
            'jumlah_saudara' => ['nullable', 'integer', 'min:0', 'max:30'],
            'no_telepon' => ['nullable', 'string', 'max:20'],

            'alamat' => [$req, 'string'],
            'rt' => ['nullable', 'string', 'max:3'],
            'rw' => ['nullable', 'string', 'max:3'],
            'kelurahan' => [$req, 'string', 'max:100'],
            'kecamatan' => [$req, 'string', 'max:100'],
            'kabupaten' => [$req, 'string', 'max:100'],
            'provinsi' => [$req, 'string', 'max:100'],
            'kode_pos' => ['nullable', 'digits:5'],

            'nama_ayah' => [$req, 'string', 'max:255'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:100'],
            'no_telepon_ayah' => ['nullable', 'string', 'max:20'],
            'nama_ibu' => [$req, 'string', 'max:255'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:100'],
            'no_telepon_ibu' => ['nullable', 'string', 'max:20'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'hubungan_wali' => ['nullable', 'string', 'max:50'],
            'no_telepon_wali' => ['nullable', 'string', 'max:20'],

            'kamar_id' => ['nullable', 'exists:kamar,id'],
            'status' => ['sometimes', Rule::enum(SantriStatus::class)],
            'tanggal_masuk' => [$req, 'date'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],

            // Dokumen boleh langsung dikirim saat tambah/ubah (multipart/form-data)
            'foto_profil' => ['nullable', 'image', 'max:2048'],
            'file_kk' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'file_akta' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'file_ktp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }
}
