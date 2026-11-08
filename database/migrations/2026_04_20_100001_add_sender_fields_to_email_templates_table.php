<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('from_name')->nullable()->after('attivo');
            $table->string('from_address')->nullable()->after('from_name');
            $table->string('reply_to')->nullable()->after('from_address');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn(['from_name', 'from_address', 'reply_to']);
        });
    }
};
