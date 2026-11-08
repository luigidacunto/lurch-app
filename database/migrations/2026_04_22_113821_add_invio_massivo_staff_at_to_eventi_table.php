<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->timestamp('invio_massivo_staff_at')->nullable()->after('form_staff_aperto_at');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn('invio_massivo_staff_at');
        });
    }
};
