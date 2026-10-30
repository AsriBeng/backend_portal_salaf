<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Keringanan SPP per santri diganti SPP khusus berkelompok: satu nominal & rentang bulan untuk banyak santri. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_khusus', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // nominal SPP yang ditagihkan selama berlaku, 0 = bebas SPP
            $table->decimal('nominal', 12, 0);
            // bulan pertama & terakhir (yyyy-mm-01), sampai null = tanpa batas
            $table->date('mulai');
            $table->date('sampai')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('spp_khusus_santri', function (Blueprint $table) {
            $table->foreignId('spp_khusus_id')->constrained('spp_khusus')->cascadeOnDelete();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            $table->primary(['spp_khusus_id', 'santri_id']);
        });

        if (! Schema::hasTable('keringanan_spp')) {
            return;
        }

        // keringanan lama dengan nominal, rentang & keterangan yang sama digabung jadi satu SPP khusus
        $grup = DB::table('keringanan_spp')->orderBy('id')->get()
            ->groupBy(fn ($k) => implode('|', [$k->nominal, $k->mulai, $k->sampai, $k->keterangan]));

        foreach ($grup as $daftar) {
            $k = $daftar->first();
            $id = DB::table('spp_khusus')->insertGetId([
                'nama' => (int) $k->nominal === 0 ? 'Bebas SPP' : 'SPP khusus Rp '.number_format((int) $k->nominal, 0, ',', '.'),
                'nominal' => $k->nominal,
                'mulai' => $k->mulai,
                'sampai' => $k->sampai,
                'keterangan' => $k->keterangan,
                'created_by' => $k->created_by,
                'created_at' => $k->created_at,
                'updated_at' => $k->updated_at,
            ]);
            DB::table('spp_khusus_santri')->insertOrIgnore(
                $daftar->map(fn ($k) => ['spp_khusus_id' => $id, 'santri_id' => $k->santri_id])->all()
            );
        }

        Schema::drop('keringanan_spp');
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_khusus_santri');
        Schema::dropIfExists('spp_khusus');
    }
};
