<?php

namespace Tests\Feature;

use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_santri_login_dengan_nis(): void
    {
        $santri = Santri::factory()->create();

        $this->postJson('/api/login', ['username' => $santri->nis, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'santri')
            ->assertJsonPath('user.santri.nis', $santri->nis)
            ->assertJsonStructure(['token']);
    }

    public function test_password_salah_ditolak(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'salah'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_akun_nonaktif_tidak_bisa_login_dan_token_lama_ditolak(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $user->update(['is_active' => false]);

        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'password'])
            ->assertUnprocessable();

        $this->withToken($token)->getJson('/api/me')->assertForbidden();
    }

    public function test_ganti_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('hp-ini')->plainTextToken;
        $user->createToken('hp-lain');

        $this->withToken($token)->putJson('/api/me/password', [
            'password_lama' => 'password',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertOk();

        // Perangkat lain dikeluarkan, perangkat ini tetap login
        $this->assertSame(1, $user->tokens()->count());

        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'rahasia123'])->assertOk();
    }
}
