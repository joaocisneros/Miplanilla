<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->where('numero_documento', '99999999')
            ->where('apellido_paterno', 'PRUEBA')
            ->where('apellido_materno', 'RECIBOS')
            ->where('nombres', 'JUAN CARLOS')
            ->delete();
    }

    public function down(): void
    {
        // Un registro de prueba eliminado no se restaura.
    }
};
