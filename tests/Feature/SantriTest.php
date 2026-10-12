<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Kamar;
use App\Models\Komplek;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SantriTest extends TestCase
{
    use RefreshDatabase;

    private function dataSantri(array $extra = []): array
    {
        return [
            'nis' => '20260001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'tempat_lahir' => 'Jombang',
            'tanggal_lahir' => '2012-03-15',
            'alamat' => 'Dusun Krajan',
            'kelurahan' => 'Tebuireng',
            'kecamatan' => 'Diwek',
            'kabupaten' => 'Jombang',
            'provinsi' => 'Jawa Timur',
            'nama_ayah' => 'Abdullah',
            'nama_ibu' => 'Aminah',
            'tanggal_masuk' => '2026-07-01',
            ...$extra,
        ];
    }

    private function kamar(Gender $gender, int $kapasitas = 2): Kamar
    {
        $komplek = Komplek::create(['nama' => 'Komplek A '.$gender->value, 'gender' => $gender]);

        return Kamar::create(['komplek_id' => $komplek->id, 'nama' => 'Kamar 1', 'kapasitas' => $kapasitas]);
    }

    public function test_sekretaris_membuat_santri_dan_akun_login_dengan_tanggal_lahir(): void
    {
        $sekretaris = User::factory()->pengurus(Role::Sekretaris, Gender::Putra)->create();
        $kamar = $this->kamar(Gender::Putra);

        $this->actingAs($sekretaris)
            ->postJson('/api/santri', $this->dataSantri(['kamar_id' => $kamar->id]))
            ->assertCreated()
            ->assertJsonPath('data.jenis_kelamin', 'L')
            ->assertJsonPath('data.kamar.nama', 'Kamar 1');

        // Password awal = tanggal lahir ddmmyyyy
        $this->postJson('/api/login', ['username' => '20260001', 'password' => '15032012'])->assertOk();
    }

    public function test_kamar_penuh_ditolak(): void
    {
        $sekretaris = User::factory()->pengurus(Role::Sekretaris, Gender::Putra)->create();
        $kamar = $this->kamar(Gender::Putra, kapasitas: 1);
        Santri::factory()->create(['kamar_id' => $kamar->id]);

        $this->actingAs($sekretaris)
            ->postJson('/api/santri', $this->dataSantri(['kamar_id' => $kamar->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kamar_id');
    }

    public function test_kamar_putri_tidak_bisa_untuk_santri_putra(): void
    {
        $admin = User::factory()->admin()->create();
        $kamarPutri = $this->kamar(Gender::Putri);

        $this->actingAs($admin)
            ->postJson('/api/santri', $this->dataSantri(['jenis_kelamin' => 'L', 'kamar_id' => $kamarPutri->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kamar_id');
    }

    public function test_pengurus_putra_tidak_bisa_melihat_santri_putri(): void
    {
        $sekretarisPutra = User::factory()->pengurus(Role::Sekretaris, Gender::Putra)->create();
        $putra = Santri::factory()->create();
        $putri = Santri::factory()->putri()->create();

        $this->actingAs($sekretarisPutra)->getJson('/api/santri')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $putra->id);

        $this->actingAs($sekretarisPutra)->getJson("/api/santri/{$putri->id}")->assertNotFound();
    }

    public function test_admin_melihat_putra_dan_putri(): void
    {
        $admin = User::factory()->admin()->create();
        Santri::factory()->create();
        Santri::factory()->putri()->create();

        $this->actingAs($admin)->getJson('/api/santri')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/santri?gender=P')->assertJsonCount(1, 'data');
    }

    public function test_bendahara_tidak_melihat_nik_dan_dokumen(): void
    {
        $bendahara = User::factory()->pengurus(Role::Bendahara, Gender::Putra)->create();
        $santri = Santri::factory()->create(['nik' => '3517000000000001']);

        $this->actingAs($bendahara)->getJson("/api/santri/{$santri->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.nik')
            ->assertJsonMissingPath('data.dokumen');

        $this->actingAs($bendahara)->postJson('/api/santri', $this->dataSantri())->assertForbidden();
    }

    public function test_upload_dan_unduh_dokumen_privat(): void
    {
        Storage::fake('local');
        $sekretaris = User::factory()->pengurus(Role::Sekretaris, Gender::Putra)->create();
        $ketua = User::factory()->pengurus(Role::Ketua, Gender::Putra)->create();
        $santri = Santri::factory()->create();

        $this->actingAs($sekretaris)->postJson("/api/santri/{$santri->id}/dokumen", [
            'file_kk' => UploadedFile::fake()->create('kk.pdf', 200, 'application/pdf'),
            'foto_profil' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertOk();

        $santri->refresh();
        Storage::disk('local')->assertExists($santri->file_kk);

        $this->actingAs($sekretaris)->get("/api/santri/{$santri->id}/dokumen/file_kk")->assertOk();
        // Ketua boleh melihat foto, tapi tidak KK
        $this->actingAs($ketua)->get("/api/santri/{$santri->id}/dokumen/foto_profil")->assertOk();
        $this->actingAs($ketua)->getJson("/api/santri/{$santri->id}/dokumen/file_kk")->assertForbidden();
        // Santri sendiri boleh
        $this->actingAs($santri->user)->get("/api/santri/{$santri->id}/dokumen/file_kk")->assertOk();
    }

    public function test_santri_melihat_data_dirinya_sendiri_saja(): void
    {
        $santri = Santri::factory()->create(['nik' => '3517000000000009']);
        $lain = Santri::factory()->create();

        $this->actingAs($santri->user)->getJson('/api/profil-santri')
            ->assertOk()
            ->assertJsonPath('data.nis', $santri->nis)
            ->assertJsonPath('data.nik', '3517000000000009');

        // Santri tidak boleh membuka data santri lain
        $this->actingAs($santri->user)->getJson("/api/santri/{$lain->id}")->assertForbidden();
        $this->actingAs($santri->user)->getJson("/api/santri/{$lain->id}/dokumen/foto_profil")->assertNotFound();
    }

    public function test_verifikasi_kartu_lewat_qr(): void
    {
        $santri = Santri::factory()->create(['nama_lengkap' => 'Ahmad Fauzi']);

        $this->actingAs($santri->user)->getJson('/api/kartu-saya')
            ->assertOk()
            ->assertJsonPath('data.qr_url', url('/kartu/'.$santri->card_token));

        $this->get('/kartu/'.$santri->card_token)->assertOk()->assertSee('KARTU ASLI')->assertSee('Ahmad Fauzi');

        $santri->update(['status' => 'lulus']);
        $this->get('/kartu/'.$santri->card_token)->assertOk()->assertSee('KARTU TIDAK BERLAKU');

        $this->get('/kartu/kodeasal')->assertOk()->assertSee('KARTU TIDAK DITEMUKAN');
    }
}
