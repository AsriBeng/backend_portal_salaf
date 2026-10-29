<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keringanan_spp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('santri_id')->constrained('santri')->cascadeOnDelete();
            // nominal SPP yang ditagihkan selama keringanan berlaku, 0 = bebas SPP
            $table->decimal('nominal', 12, 0);
            // bulan pertama & terakhir (yyyy-mm-01), sampai null = tanpa batas
            $table->date('mulai');
            $table->date('sampai')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['santri_id', 'mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keringanan_spp');
    }
};
