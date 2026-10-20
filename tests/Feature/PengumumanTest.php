<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Santri;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PengumumanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengumuman_putra_hanya_sampai_ke_putra(): void
    {
        Notification::fake();
        $ketuaPutra = User::factory()->pengurus(Role::Ketua, Gender::Putra)->create();
        $admin = User::factory()->admin()->create();
        $santriPutra = Santri::factory()->create();
        $santriPutri = Santri::factory()->putri()->create();

        $this->actingAs($ketuaPutra)->postJson('/api/pengumuman', [
            'judul' => 'Ro\'an', 'isi' => 'Kerja bakti hari Jumat', 'target' => 'putri',
        ])->assertUnprocessable()->assertJsonValidationErrors('target');

        $this->actingAs($ketuaPutra)->postJson('/api/pengumuman', [
            'judul' => 'Ro\'an', 'isi' => 'Kerja bakti hari Jumat', 'target' => 'putra',
        ])->assertCreated();

        Notification::assertSentTo([$santriPutra->user, $admin], PengumumanBaru::class);
        Notification::assertNotSentTo([$santriPutri->user, $ketuaPutra], PengumumanBaru::class);

        $this->actingAs($santriPutra->user)->getJson('/api/pengumuman')->assertJsonCount(1, 'data');
        $this->actingAs($santriPutri->user)->getJson('/api/pengumuman')->assertJsonCount(0, 'data');
    }

    public function test_pengumuman_semua_sampai_ke_semua_dan_santri_tidak_bisa_membuat(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $santriPutra = Santri::factory()->create();
        $santriPutri = Santri::factory()->putri()->create();

        $this->actingAs($admin)->postJson('/api/pengumuman', [
            'judul' => 'Libur', 'isi' => 'Libur maulid', 'target' => 'semua',
        ])->assertCreated();

        Notification::assertSentTo([$santriPutra->user, $santriPutri->user], PengumumanBaru::class);

        $this->actingAs($santriPutra->user)->postJson('/api/pengumuman', [
            'judul' => 'x', 'isi' => 'x', 'target' => 'semua',
        ])->assertForbidden();
    }

    public function test_bendahara_bisa_membuat_dan_mengubah_pengumuman_bagiannya(): void
    {
        Notification::fake();
        $bendaharaPutri = User::factory()->pengurus(Role::Bendahara, Gender::Putri)->create();
        $santriPutri = Santri::factory()->putri()->create();
        $santriPutra = Santri::factory()->create();

        $this->actingAs($bendaharaPutri)->postJson('/api/pengumuman', [
            'judul' => 'Iuran', 'isi' => 'Iuran bulanan', 'target' => 'putra',
        ])->assertUnprocessable()->assertJsonValidationErrors('target');

        $id = $this->actingAs($bendaharaPutri)->postJson('/api/pengumuman', [
            'judul' => 'Iuran', 'isi' => 'Iuran bulanan dibayar paling lambat tanggal 10', 'target' => 'putri',
        ])->assertCreated()->json('data.id');

        Notification::assertSentTo($santriPutri->user, PengumumanBaru::class);
        Notification::assertNotSentTo($santriPutra->user, PengumumanBaru::class);

        $this->actingAs($bendaharaPutri)->postJson("/api/pengumuman/{$id}", ['judul' => 'Iuran bulanan'])->assertOk();
        $this->actingAs($bendaharaPutri)->deleteJson("/api/pengumuman/{$id}")->assertOk();
    }

    public function test_dashboard_sesuai_peran(): void
    {
        $santri = Santri::factory()->create();
        $sekretaris = User::factory()->pengurus(Role::Sekretaris, Gender::Putra)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($santri->user)->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.santri.nis', $santri->nis);
        $this->actingAs($sekretaris)->getJson('/api/dashboard')->assertOk()->assertJsonMissingPath('data.keuangan');
        $this->actingAs($admin)->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.cakupan', 'Putra & Putri');
    }
}
