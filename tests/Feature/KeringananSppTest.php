<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\JenisTagihanTipe;
use App\Enums\Role;
use App\Models\JenisTagihan;
use App\Models\Santri;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class KeringananSppTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed();
        JenisTagihan::where('tipe', JenisTagihanTipe::Spp)->update(['nominal' => 380000]);
        $this->bendahara = User::factory()->pengurus(Role::Bendahara, Gender::Putra)->create();
    }

    public function test_generate_spp_memakai_nominal_keringanan_pada_rentangnya(): void
    {
        $udin = Santri::factory()->create();
        $lain = Santri::factory()->create();

        $this->actingAs($this->bendahara)->postJson("/api/santri/{$udin->id}/keringanan-spp", [
            'nominal' => 300000, 'mulai' => '2026-10', 'sampai' => '2026-11', 'keterangan' => 'Keadaan khusus',
        ])->assertCreated()->assertJsonPath('data.mulai', '2026-10');

        $service = app(TagihanService::class);
        foreach (['2026-09-01', '2026-10-01', '2026-12-01'] as $bulan) {
            $service->generateSpp(Carbon::parse($bulan));
        }

        $nominal = fn (Santri $s, string $periode) => Tagihan::where('santri_id', $s->id)->whereDate('periode', $periode)->value('nominal');
        $this->assertEquals(380000, $nominal($udin, '2026-09-01'));
        $this->assertEquals(300000, $nominal($udin, '2026-10-01'));
        $this->assertEquals(380000, $nominal($udin, '2026-12-01'));
        $this->assertEquals(380000, $nominal($lain, '2026-10-01'));
    }

    public function test_tagihan_yang_sudah_terbit_ikut_disesuaikan_dan_kembali_saat_dihapus(): void
    {
        $udin = Santri::factory()->create();
        $dicicil = Santri::factory()->create();
        app(TagihanService::class)->generateSpp(Carbon::parse('2026-10-01'));
        Tagihan::where('santri_id', $dicicil->id)->update(['terbayar' => 350000, 'status' => 'dicicil']);

        $id = $this->actingAs($this->bendahara)->postJson("/api/santri/{$udin->id}/keringanan-spp", [
            'nominal' => 300000, 'mulai' => '2026-10', 'sampai' => '2026-10',
        ])->assertCreated()->assertJsonPath('tagihan_disesuaikan', 1)->json('data.id');

        $this->assertSame(300000, $udin->tagihan()->sole()->nominal);

        // sudah dibayar 350.000, lebih dari nominal keringanan: dibiarkan
        $this->actingAs($this->bendahara)->postJson("/api/santri/{$dicicil->id}/keringanan-spp", [
            'nominal' => 300000, 'mulai' => '2026-10',
        ])->assertCreated()->assertJsonPath('tagihan_disesuaikan', 0);
        $this->assertSame(380000, $dicicil->tagihan()->sole()->nominal);

        $this->actingAs($this->bendahara)->deleteJson("/api/keringanan-spp/{$id}")
            ->assertOk()->assertJsonPath('tagihan_disesuaikan', 1);
        $this->assertSame(380000, $udin->tagihan()->sole()->nominal);
    }

    public function test_keringanan_nol_berarti_bebas_spp(): void
    {
        $yatim = Santri::factory()->create();
        app(TagihanService::class)->generateSpp(Carbon::parse('2026-10-01'));

        $this->actingAs($this->bendahara)->postJson("/api/santri/{$yatim->id}/keringanan-spp", [
            'nominal' => 0, 'mulai' => '2026-10',
        ])->assertCreated()->assertJsonPath('tagihan_disesuaikan', 1);

        $this->assertSame(0, $yatim->tagihan()->count());
        $this->assertSame(0, app(TagihanService::class)->generateSpp(Carbon::parse('2026-11-01')));
    }

    public function test_rentang_tidak_boleh_bertumpuk_dan_bendahara_putri_tidak_bisa_akses_putra(): void
    {
        $udin = Santri::factory()->create();
        $this->actingAs($this->bendahara)->postJson("/api/santri/{$udin->id}/keringanan-spp", [
            'nominal' => 300000, 'mulai' => '2026-10',
        ])->assertCreated();

        $this->actingAs($this->bendahara)->postJson("/api/santri/{$udin->id}/keringanan-spp", [
            'nominal' => 250000, 'mulai' => '2027-01', 'sampai' => '2027-02',
        ])->assertJsonValidationErrors('mulai');

        $this->actingAs($this->bendahara)->postJson("/api/santri/{$udin->id}/keringanan-spp", [
            'nominal' => 250000, 'mulai' => '2026-10', 'sampai' => '2026-09',
        ])->assertJsonValidationErrors('sampai');

        $bendaharaPutri = User::factory()->pengurus(Role::Bendahara, Gender::Putri)->create();
        $this->actingAs($bendaharaPutri)->getJson("/api/santri/{$udin->id}/keringanan-spp")->assertNotFound();
        $this->actingAs($udin->user)->getJson("/api/santri/{$udin->id}/keringanan-spp")->assertForbidden();
    }
}
