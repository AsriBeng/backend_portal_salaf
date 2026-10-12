<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Models\KasTransaksi;
use App\Models\Pembayaran;
use App\Models\Santri;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_bisa_diulang_dan_akun_bisa_login(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(14, Santri::count());
        $this->assertSame(1, User::where('username', 'sekretaris_putra')->count());

        foreach (['sekretaris_putra', 'bendahara_putri', '2025001'] as $username) {
            $this->postJson('/api/login', ['username' => $username, 'password' => DemoSeeder::DEMO_PASSWORD])->assertOk();
        }
    }

    public function test_demo_keuangan_terisi_dan_tidak_dobel(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        // 13 santri aktif × 2 bulan SPP + 8 iuran ziarah putra aktif
        $this->assertSame(13 * 2 + 8, Tagihan::count());
        $this->assertSame(3, Pembayaran::where('status', 'menunggu')->count());
        // Putra: 150.000 + 75.000 − 100.000; putri: 150.000
        $this->assertSame(125000, KasTransaksi::saldo(Gender::Putra));
        $this->assertSame(150000, KasTransaksi::saldo(Gender::Putri));
    }

    public function test_sekretaris_putra_hanya_melihat_santri_putra(): void
    {
        $this->seed(DemoSeeder::class);
        $sekretaris = User::where('username', 'sekretaris_putra')->first();

        $data = $this->actingAs($sekretaris)->getJson('/api/santri?status=semua&per_page=100')->assertOk()->json('data');

        $this->assertCount(9, $data);
        $this->assertSame(['L'], array_values(array_unique(array_column($data, 'jenis_kelamin'))));
    }
}
