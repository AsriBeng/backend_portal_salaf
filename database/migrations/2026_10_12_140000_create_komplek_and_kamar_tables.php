<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komplek', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('gender', ['L', 'P'])->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('kamar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komplek_id')->constrained('komplek')->restrictOnDelete();
            $table->string('nama');
            $table->unsignedSmallInteger('kapasitas');
            $table->timestamps();

            $table->unique(['komplek_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar');
        Schema::dropIfExists('komplek');
    }
};
