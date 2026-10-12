<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /** Publik: dipakai di halaman login dan kartu santri. */
    public function pondok(): JsonResponse
    {
        return response()->json([
            'data' => [
                'nama_pondok' => Setting::value('nama_pondok'),
                'alamat_pondok' => Setting::value('alamat_pondok'),
                'telepon_pondok' => Setting::value('telepon_pondok'),
                'website_pondok' => Setting::value('website_pondok'),
                'logo_url' => url('/logo'),
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $values = Setting::values();
        $values['logo_url'] = url('/logo');
        unset($values['logo']);

        return response()->json(['data' => $values]);
    }

    /** Kirim sebagai multipart/form-data jika mengganti logo. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_pondok' => ['sometimes', 'string', 'max:255'],
            'alamat_pondok' => ['nullable', 'string', 'max:500'],
            'telepon_pondok' => ['nullable', 'string', 'max:30'],
            'website_pondok' => ['nullable', 'url', 'max:255'],
            'spp_jatuh_tempo_hari' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        foreach (array_diff_key($data, ['logo' => true]) as $key => $value) {
            Setting::put($key, $value ?? '');
        }

        if ($request->hasFile('logo')) {
            if ($lama = Setting::value('logo')) {
                Storage::delete($lama);
            }
            Setting::put('logo', $request->file('logo')->store('pondok'));
        }

        return $this->index();
    }
}
