<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ultimo_acceso')->nullable()->after('email_verified_at');
            $table->string('ultimo_acceso_ip', 45)->nullable()->after('ultimo_acceso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ultimo_acceso', 'ultimo_acceso_ip']);
        });
    }
};
