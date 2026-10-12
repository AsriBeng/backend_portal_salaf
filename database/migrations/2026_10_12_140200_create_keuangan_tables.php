<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('tipe', ['spp', 'iuran'])->index();
            $table->decimal('nominal', 12, 0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained('santri')->restrictOnDelete();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->restrictOnDelete();
            $table->string('judul');
            // bulan SPP (yyyy-mm-01), null untuk iuran
            $table->date('periode')->nullable();
            $table->decimal('nominal', 12, 0);
            $table->decimal('terbayar', 12, 0)->default(0);
            $table->enum('status', ['belum_bayar', 'dicicil', 'lunas'])->default('belum_bayar')->index();
            $table->date('jatuh_tempo')->nullable();
            // null = dibuat otomatis oleh sistem
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // mencegah SPP dobel di bulan yang sama
            $table->unique(['santri_id', 'jenis_tagihan_id', 'periode']);
        });

        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan')->restrictOnDelete();
            $table->decimal('nominal', 12, 0);
            $table->enum('metode', ['transfer', 'tunai']);
            $table->enum('sumber', ['santri', 'bendahara']);
            $table->string('bukti')->nullable();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu')->index();
            $table->text('alasan_tolak')->nullable();
            $table->date('tanggal_bayar');
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kategori_kas', function (Blueprint $table) {
            $table->id();
            // kode sistem, misal "pembayaran-santri" (dipakai otomatis saat pembayaran disetujui)
            $table->string('kode', 50)->nullable()->unique();
            $table->string('nama');
            $table->enum('tipe', ['masuk', 'keluar']);
            $table->timestamps();
        });

        Schema::create('kas_transaksi', function (Blueprint $table) {
            $table->id();
            $table->enum('gender', ['L', 'P'])->index();
            $table->enum('tipe', ['masuk', 'keluar'])->index();
            $table->foreignId('kategori_kas_id')->constrained('kategori_kas')->restrictOnDelete();
            $table->decimal('nominal', 12, 0);
            $table->date('tanggal')->index();
            $table->text('keterangan');
            $table->foreignId('pembayaran_id')->nullable()->unique()->constrained('pembayaran')->restrictOnDelete();
            $table->string('bukti')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_transaksi');
        Schema::dropIfExists('kategori_kas');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('jenis_tagihan');
    }
};
