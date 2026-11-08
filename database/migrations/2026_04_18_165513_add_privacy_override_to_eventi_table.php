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
            $table->text('privacy_override_informativa')->nullable()->after('orario_campo');
            $table->string('privacy_override_informativa_pdf')->nullable()->after('privacy_override_informativa');
            $table->text('privacy_override_foto_video')->nullable()->after('privacy_override_informativa_pdf');
            $table->string('privacy_override_foto_video_pdf')->nullable()->after('privacy_override_foto_video');
        });
    }

    public function down(): void
    {
        Schema::table('eventi', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_override_informativa',
                'privacy_override_informativa_pdf',
                'privacy_override_foto_video',
                'privacy_override_foto_video_pdf',
            ]);
        });
    }
};
