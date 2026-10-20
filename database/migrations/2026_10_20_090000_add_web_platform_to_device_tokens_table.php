<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Token push dari Portal Salaf versi web (browser). */
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->enum('platform', ['android', 'ios', 'web'])->change();
        });
    }

    public function down(): void
    {
        DB::table('device_tokens')->where('platform', 'web')->delete();

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->enum('platform', ['android', 'ios'])->change();
        });
    }
};
