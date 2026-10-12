<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('santri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('nis', 50)->unique();
            $table->string('card_token', 32)->unique();

            // Identitas
            $table->string('nama_lengkap');
            $table->string('nik', 16)->nullable()->unique();
            $table->string('no_kk', 16)->nullable();
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['L', 'P'])->index();
            $table->string('agama', 30)->default('Islam');
            $table->string('kewarganegaraan', 30)->default('WNI');
            $table->unsignedTinyInteger('anak_ke')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara')->nullable();
            $table->string('no_telepon', 20)->nullable();

            // Alamat asal (sesuai data kependudukan)
            $table->text('alamat');
            $table->string('rt', 3)->nullable();
            $table->string('rw', 3)->nullable();
            $table->string('kelurahan', 100);
            $table->string('kecamatan', 100);
            $table->string('kabupaten', 100);
            $table->string('provinsi', 100);
            $table->string('kode_pos', 5)->nullable();

            // Orang tua & wali
            $table->string('nama_ayah');
            $table->string('pekerjaan_ayah', 100)->nullable();
            $table->string('no_telepon_ayah', 20)->nullable();
            $table->string('nama_ibu');
            $table->string('pekerjaan_ibu', 100)->nullable();
            $table->string('no_telepon_ibu', 20)->nullable();
            $table->string('nama_wali')->nullable();
            $table->string('hubungan_wali', 50)->nullable();
            $table->string('no_telepon_wali', 20)->nullable();

            // Dokumen (path di storage privat)
            $table->string('foto_profil')->nullable();
            $table->string('file_kk')->nullable();
            $table->string('file_akta')->nullable();
            $table->string('file_ktp')->nullable();

            // Pondok
            $table->foreignId('kamar_id')->nullable()->constrained('kamar')->nullOnDelete();
            $table->enum('status', ['aktif', 'lulus', 'keluar'])->default('aktif')->index();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('santri');
    }
};
