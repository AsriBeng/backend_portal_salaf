<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoPenggunaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_pengurus_mengganti_dan_menghapus_foto_sendiri(): void
    {
        $bendahara = User::factory()->pengurus(Role::Bendahara, Gender::Putra)->create();

        $url = $this->actingAs($bendahara)->postJson('/api/me/foto', ['foto' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()
            ->json('data.foto_url');
        $this->assertNotNull($url);
        $lama = $bendahara->refresh()->foto;
        Storage::disk('local')->assertExists($lama);

        // Ganti foto: file lama dihapus dan URL berubah (cache aplikasi ikut diperbarui)
        $urlBaru = $this->actingAs($bendahara)->postJson('/api/me/foto', ['foto' => UploadedFile::fake()->image('b.jpg')])
            ->json('data.foto_url');
        $this->assertNotSame($url, $urlBaru);
        Storage::disk('local')->assertMissing($lama);

        // Pengguna lain (termasuk santri) bisa melihat foto pengurus
        $santri = Santri::factory()->create();
        $this->actingAs($santri->user)->get("/api/users/{$bendahara->id}/foto")->assertOk();

        $this->actingAs($bendahara)->deleteJson('/api/me/foto')->assertOk()->assertJsonPath('data.foto_url', null);
    }

    public function test_santri_tidak_bisa_mengganti_foto_lewat_profil(): void
    {
        $santri = Santri::factory()->create();

        $this->actingAs($santri->user)
            ->postJson('/api/me/foto', ['foto' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
    }

    public function test_admin_mengganti_foto_pengurus_lain_tapi_bukan_santri(): void
    {
        $admin = User::factory()->admin()->create();
        $sekretaris = User::factory()->pengurus(Role::Sekretaris, Gender::Putri)->create();
        $santri = Santri::factory()->create();

        $this->actingAs($admin)->postJson("/api/users/{$sekretaris->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk();
        $this->assertNotNull($sekretaris->refresh()->foto);

        $this->actingAs($admin)->postJson("/api/users/{$santri->user_id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')])
            ->assertNotFound();

        // Pengurus biasa tidak bisa mengganti foto orang lain
        $this->actingAs($sekretaris)->postJson("/api/users/{$admin->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')])
            ->assertForbidden();
    }
}
