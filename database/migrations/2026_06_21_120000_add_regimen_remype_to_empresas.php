<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Régimen laboral que aplica a la empresa (afecta gratificación, CTS, vacaciones, salud).
            $table->enum('regimen_laboral', ['general', 'microempresa', 'pequena'])
                ->default('general')->after('direccion');
            // Acogimiento a la Ley MYPE (REMYPE).
            $table->string('remype_numero', 30)->nullable()->after('regimen_laboral');
            $table->date('remype_fecha')->nullable()->after('remype_numero');
            // Giro / actividad económica (se usa en la causa objetiva del contrato).
            $table->string('giro')->nullable()->after('remype_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['regimen_laboral', 'remype_numero', 'remype_fecha', 'giro']);
        });
    }
};
