<?php

namespace Tests\Feature;

use App\Models\Santri;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_sekretaris_putra_hanya_melihat_santri_putra(): void
    {
        $this->seed(DemoSeeder::class);
        $sekretaris = User::where('username', 'sekretaris_putra')->first();

        $data = $this->actingAs($sekretaris)->getJson('/api/santri?status=semua&per_page=100')->assertOk()->json('data');

        $this->assertCount(9, $data);
        $this->assertSame(['L'], array_values(array_unique(array_column($data, 'jenis_kelamin'))));
    }
}
