<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\JenisTagihanTipe;
use App\Enums\Role;
use App\Models\JenisTagihan;
use App\Models\KasTransaksi;
use App\Models\KategoriKas;
use App\Models\Santri;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\PembayaranDiverifikasi;
use App\Notifications\TagihanBaru;
use App\Services\TagihanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KeuanganTest extends TestCase
{
    use RefreshDatabase;

    private JenisTagihan $spp;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
        $this->seed();
        $this->spp = JenisTagihan::where('tipe', JenisTagihanTipe::Spp)->first();
        $this->spp->update(['nominal' => 300000]);
    }

    public function test_generate_spp_tidak_dobel_dan_mengirim_notifikasi(): void
    {
        $santri = Santri::factory()->create();
        Santri::factory()->create(['status' => 'lulus']);

        $service = app(TagihanService::class);
        $bulan = Carbon::parse('2026-10-01');

        $this->assertSame(1, $service->generateSpp($bulan));
        $this->assertSame(0, $service->generateSpp($bulan));

        $tagihan = Tagihan::sole();
        $this->assertSame(300000, $tagihan->nominal);
        $this->assertSame('2026-10-10', $tagihan->jatuh_tempo->toDateString());
        Notification::assertSentTo($santri->user, TagihanBaru::class);
    }

    public function test_sisa_spp_bulan_lalu_tetap_terpisah(): void
    {
        $santri = Santri::factory()->create();
        $service = app(TagihanService::class);
        $service->generateSpp(Carbon::parse('2026-01-01'));

        $januari = Tagihan::sole();
        $januari->update(['terbayar' => 150000, 'status' => 'dicicil']);

        $service->generateSpp(Carbon::parse('2026-02-01'));

        $this->actingAs($santri->user)->getJson('/api/tagihan?belum_lunas=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('ringkasan.sisa', 450000);
    }

    public function test_alur_upload_bukti_lalu_disetujui_bendahara(): void
    {
        $santri = Santri::factory()->create();
        $bendahara = User::factory()->pengurus(Role::Bendahara, Gender::Putra)->create();
        $tagihan = $this->tagihan($santri);

        $id = $this->actingAs($santri->user)->postJson('/api/pembayaran', [
            'tagihan_id' => $tagihan->id,
            'nominal' => 150000,
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertCreated()->assertJsonPath('data.status', 'menunggu')->json('data.id');

        // Nominal kedua melebihi sisa setelah dikurangi yang masih menunggu
        $this->actingAs($santri->user)->postJson('/api/pembayaran', [
            'tagihan_id' => $tagihan->id,
            'nominal' => 200000,
            'bukti' => UploadedFile::fake()->image('bukti2.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('nominal');

        $this->actingAs($bendahara)->postJson("/api/pembayaran/{$id}/setujui")
            ->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        $tagihan->refresh();
        $this->assertSame(150000, $tagihan->terbayar);
        $this->assertSame('dicicil', $tagihan->status->value);
        $this->assertSame(150000, KasTransaksi::saldo(Gender::Putra));
        $this->assertSame(0, KasTransaksi::saldo(Gender::Putri));
        Notification::assertSentTo($santri->user, PembayaranDiverifikasi::class);

        // Tidak bisa disetujui dua kali
        $this->actingAs($bendahara)->postJson("/api/pembayaran/{$id}/setujui")->assertUnprocessable();
    }

    public function test_pembayaran_ditolak_tidak_mengubah_kas(): void
    {
        $santri = Santri::factory()->create();
        $bendahara = User::factory()->pengurus(Role::Bendahara, Gender::Putra)->create();
        $tagihan = $this->tagihan($santri);

        $id = $this->actingAs($santri->user)->postJson('/api/pembayaran', [
            'tagihan_id' => $tagihan->id,
            'nominal' => 300000,
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ])->json('data.id');

        $this->actingAs($bendahara)->postJson("/api/pembayaran/{$id}/tolak")->assertJsonValidationErrors('alasan');
        $this->actingAs($bendahara)->postJson("/api/pembayaran/{$id}/tolak", ['alasan' => 'Bukti tidak terbaca'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ditolak');

        $this->assertSame(0, $tagihan->refresh()->terbayar);
        $this->assertSame(0, KasTransaksi::saldo(Gender::Putra));
    }

    public function test_bendahara_input_langsung_dan_bendahara_putri_tidak_bisa_akses_putra(): void
    {
        $santri = Santri::factory()->create();
        $bendaharaPutra = User::factory()->pengurus(Role::Bendahara, Gender::Putra)->create();
        $bendaharaPutri = User::factory()->pengurus(Role::Bendahara, Gender::Putri)->create();
        $tagihan = $this->tagihan($santri);

        $this->actingAs($bendaharaPutri)->postJson('/api/pembayaran', [
            'tagihan_id' => $tagihan->id, 'nominal' => 300000, 'metode' => 'tunai',
        ])->assertNotFound();

        $this->actingAs($bendaharaPutra)->postJson('/api/pembayaran', [
            'tagihan_id' => $tagihan->id, 'nominal' => 300000, 'metode' => 'tunai',
        ])->assertCreated()->assertJsonPath('data.status', 'disetujui');

        $this->assertSame('lunas', $tagihan->refresh()->status->value);
        $this->assertSame(300000, KasTransaksi::saldo(Gender::Putra));
    }

    public function test_pengeluaran_tidak_boleh_melebihi_saldo_dan_admin_memilih_kas(): void
    {
        $admin = User::factory()->admin()->create();
        $keluar = KategoriKas::where('tipe', 'keluar')->first();
        $masuk = KategoriKas::where('tipe', 'masuk')->whereNull('kode')->first();

        $this->actingAs($admin)->postJson('/api/kas', [
            'tipe' => 'masuk', 'kategori_kas_id' => $masuk->id, 'nominal' => 100000, 'keterangan' => 'Donasi',
        ])->assertUnprocessable()->assertJsonValidationErrors('gender');

        $this->actingAs($admin)->postJson('/api/kas', [
            'tipe' => 'masuk', 'kategori_kas_id' => $masuk->id, 'nominal' => 100000, 'keterangan' => 'Donasi', 'gender' => 'P',
        ])->assertCreated();

        $this->actingAs($admin)->postJson('/api/kas', [
            'tipe' => 'keluar', 'kategori_kas_id' => $keluar->id, 'nominal' => 150000, 'keterangan' => 'Beli beras', 'gender' => 'P',
        ])->assertUnprocessable()->assertJsonValidationErrors('nominal');

        $this->actingAs($admin)->postJson('/api/kas', [
            'tipe' => 'keluar', 'kategori_kas_id' => $keluar->id, 'nominal' => 40000, 'keterangan' => 'Beli beras', 'gender' => 'P',
        ])->assertCreated();

        $this->actingAs($admin)->getJson('/api/kas/saldo')
            ->assertJsonPath('data.putri', 60000)
            ->assertJsonPath('data.putra', 0)
            ->assertJsonPath('data.total', 60000);
    }

    public function test_iuran_untuk_santri_putri_saja(): void
    {
        $admin = User::factory()->admin()->create();
        Santri::factory()->create();
        $putri = Santri::factory()->putri()->create();
        $iuran = JenisTagihan::create(['nama' => 'Iuran Ziarah', 'tipe' => 'iuran', 'nominal' => 250000]);

        $this->actingAs($admin)->postJson('/api/tagihan', [
            'jenis_tagihan_id' => $iuran->id,
            'gender' => 'P',
        ])->assertCreated()->assertJsonPath('jumlah', 1);

        $this->assertSame($putri->id, Tagihan::sole()->santri_id);
        Notification::assertSentTo($putri->user, TagihanBaru::class);
    }

    private function tagihan(Santri $santri): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $santri->id,
            'jenis_tagihan_id' => $this->spp->id,
            'judul' => 'SPP Oktober 2026',
            'periode' => '2026-10-01',
            'nominal' => 300000,
        ]);
    }
}
