<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Congela la modalidad (planilla/honorarios) con la que se calculó cada detalle.
 * Antes se leía employee.modalidad en vivo: si el trabajador cambiaba de modalidad
 * después, los reportes de periodos YA generados se reclasificaban solos aunque
 * los montos seguían calculados con las reglas anteriores (bug).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payroll_details', 'modalidad')) {
            Schema::table('payroll_details', function (Blueprint $table) {
                $table->string('modalidad', 20)->default('planilla')->after('employee_id');
            });
        }

        // Backfill: a la fecha de esta migración no existe ningún caso de trabajador
        // que haya cambiado de modalidad entre periodos, así que se puede tomar con
        // seguridad la modalidad ACTUAL del empleado para todo lo ya generado.
        // (Bucle en PHP en vez de UPDATE...JOIN para que funcione igual en MySQL y PostgreSQL.)
        $idsHonorarios = DB::table('employees')->where('modalidad', 'honorarios')->pluck('id');
        if ($idsHonorarios->isNotEmpty()) {
            DB::table('payroll_details')
                ->whereIn('employee_id', $idsHonorarios)
                ->update(['modalidad' => 'honorarios']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_details', 'modalidad')) {
            Schema::table('payroll_details', function (Blueprint $table) {
                $table->dropColumn('modalidad');
            });
        }
    }
};
