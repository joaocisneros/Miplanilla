<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('representante_legal')->nullable()->after('direccion');
            $table->string('representante_dni', 15)->nullable()->after('representante_legal');
            $table->string('representante_cargo')->nullable()->after('representante_dni');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['representante_legal', 'representante_dni', 'representante_cargo']);
        });
    }
};
