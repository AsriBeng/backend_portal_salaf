<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\JenisTagihanTipe;
use App\Enums\Role;
use App\Models\JenisTagihan;
use App\Models\Santri;
use App\Models\SppKhusus;
use App\Models\Tagihan;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SppKhususTest extends TestCase
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

    public function test_generate_spp_memakai_nominal_spp_khusus_untuk_santri_anggotanya(): void
    {
        $udin = Santri::factory()->create();
        $amir = Santri::factory()->create();
        $lain = Santri::factory()->create();

        $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'Potongan khusus', 'nominal' => 300000, 'mulai' => '2026-10', 'sampai' => '2026-11',
            'keterangan' => 'Keadaan khusus', 'santri_ids' => [$udin->id, $amir->id],
        ])->assertCreated()->assertJsonPath('data.mulai', '2026-10')->assertJsonCount(2, 'data.santri');

        $service = app(TagihanService::class);
        foreach (['2026-09-01', '2026-10-01', '2026-12-01'] as $bulan) {
            $service->generateSpp(Carbon::parse($bulan));
        }

        $nominal = fn (Santri $s, string $periode) => Tagihan::where('santri_id', $s->id)->whereDate('periode', $periode)->value('nominal');
        $this->assertEquals(380000, $nominal($udin, '2026-09-01'));
        $this->assertEquals(300000, $nominal($udin, '2026-10-01'));
        $this->assertEquals(300000, $nominal($amir, '2026-10-01'));
        $this->assertEquals(380000, $nominal($udin, '2026-12-01'));
        $this->assertEquals(380000, $nominal($lain, '2026-10-01'));
    }

    public function test_tagihan_terbit_ikut_disesuaikan_saat_santri_ditambah_dikeluarkan_atau_dihapus(): void
    {
        $udin = Santri::factory()->create();
        $amir = Santri::factory()->create();
        $dicicil = Santri::factory()->create();
        app(TagihanService::class)->generateSpp(Carbon::parse('2026-10-01'));
        Tagihan::where('santri_id', $dicicil->id)->update(['terbayar' => 350000, 'status' => 'dicicil']);

        // sudah dibayar 350.000, lebih dari nominal SPP khusus: dibiarkan
        $id = $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'Potongan', 'nominal' => 300000, 'mulai' => '2026-10', 'santri_ids' => [$udin->id, $dicicil->id],
        ])->assertCreated()->assertJsonPath('tagihan_disesuaikan', 1)->json('data.id');

        $this->assertSame(300000, $udin->tagihan()->sole()->nominal);
        $this->assertSame(380000, $dicicil->tagihan()->sole()->nominal);

        // udin dikeluarkan, amir dimasukkan
        $this->actingAs($this->bendahara)->putJson("/api/spp-khusus/{$id}", [
            'nama' => 'Potongan', 'nominal' => 300000, 'mulai' => '2026-10', 'santri_ids' => [$amir->id, $dicicil->id],
        ])->assertOk()->assertJsonPath('tagihan_disesuaikan', 2);

        $this->assertSame(380000, $udin->tagihan()->sole()->nominal);
        $this->assertSame(300000, $amir->tagihan()->sole()->nominal);

        $this->actingAs($this->bendahara)->deleteJson("/api/spp-khusus/{$id}")
            ->assertOk()->assertJsonPath('tagihan_disesuaikan', 1);
        $this->assertSame(380000, $amir->tagihan()->sole()->nominal);
    }

    public function test_nominal_nol_berarti_bebas_spp(): void
    {
        $yatim = Santri::factory()->create();
        app(TagihanService::class)->generateSpp(Carbon::parse('2026-10-01'));

        $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'Bebas SPP yatim', 'nominal' => 0, 'mulai' => '2026-10', 'santri_ids' => [$yatim->id],
        ])->assertCreated()->assertJsonPath('tagihan_disesuaikan', 1);

        $this->assertSame(0, $yatim->tagihan()->count());
        $this->assertSame(0, app(TagihanService::class)->generateSpp(Carbon::parse('2026-11-01')));
    }

    public function test_validasi_bentrok_dan_hak_akses(): void
    {
        $udin = Santri::factory()->create();
        $siti = Santri::factory()->putri()->create();

        $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'A', 'nominal' => 300000, 'mulai' => '2026-10', 'santri_ids' => [$udin->id],
        ])->assertCreated();

        // udin sudah masuk SPP khusus lain yang tanpa batas waktu
        $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'B', 'nominal' => 250000, 'mulai' => '2027-01', 'sampai' => '2027-02', 'santri_ids' => [$udin->id],
        ])->assertJsonValidationErrors('santri_ids');

        $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'B', 'nominal' => 250000, 'mulai' => '2026-10', 'sampai' => '2026-09', 'santri_ids' => [],
        ])->assertJsonValidationErrors(['sampai', 'santri_ids']);

        // bendahara putra tidak bisa memasukkan santri putri
        $this->actingAs($this->bendahara)->postJson('/api/spp-khusus', [
            'nama' => 'C', 'nominal' => 250000, 'mulai' => '2026-10', 'santri_ids' => [$siti->id],
        ])->assertJsonValidationErrors('santri_ids');

        // SPP khusus campuran buatan admin: bendahara putra hanya melihat santri putra dan tidak bisa mengubahnya
        $admin = User::factory()->admin()->create();
        $campuran = $this->actingAs($admin)->postJson('/api/spp-khusus', [
            'nama' => 'Campuran', 'nominal' => 200000, 'mulai' => '2026-10', 'santri_ids' => [$siti->id, Santri::factory()->create()->id],
        ])->assertCreated()->assertJsonPath('data.bisa_kelola', true)->json('data.id');

        $data = collect($this->actingAs($this->bendahara)->getJson('/api/spp-khusus')->assertOk()->json('data'))->keyBy('id');
        $this->assertCount(1, $data[$campuran]['santri']);
        $this->assertFalse($data[$campuran]['bisa_kelola']);
        $this->actingAs($this->bendahara)->deleteJson("/api/spp-khusus/{$campuran}")->assertForbidden();

        $this->actingAs($udin->user)->getJson('/api/spp-khusus')->assertForbidden();
    }

    public function test_migrasi_memindahkan_keringanan_lama(): void
    {
        $migrasi = require database_path('migrations/2026_10_30_090000_create_spp_khusus_table.php');
        $udin = Santri::factory()->create();
        $amir = Santri::factory()->create();

        $migrasi->down();
        Schema::create('keringanan_spp', function ($t) {
            $t->id();
            $t->foreignId('santri_id');
            $t->decimal('nominal', 12, 0);
            $t->date('mulai');
            $t->date('sampai')->nullable();
            $t->string('keterangan')->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
        });
        DB::table('keringanan_spp')->insert([
            ['santri_id' => $udin->id, 'nominal' => 300000, 'mulai' => '2026-10-01', 'sampai' => null, 'keterangan' => null],
            ['santri_id' => $amir->id, 'nominal' => 300000, 'mulai' => '2026-10-01', 'sampai' => null, 'keterangan' => null],
        ]);

        $migrasi->up();

        $this->assertFalse(Schema::hasTable('keringanan_spp'));
        $this->assertSame(300000, $udin->sppKhusus()->sole()->nominal);
        $this->assertSame(1, SppKhusus::count());
        $this->assertSame(2, SppKhusus::sole()->santri()->count());
    }
}
