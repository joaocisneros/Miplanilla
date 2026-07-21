<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permite "JUBILADO" en sistema de pensiones: igual que NINGUNO para el
     * cálculo (sin descuento AFP/ONP) pero visible en pantalla como lo que es.
     */
    public function up(): void
    {
        $this->permitirPensiones(['AFP', 'ONP', 'NINGUNO', 'JUBILADO']);
    }

    public function down(): void
    {
        DB::table('contracts')->where('sistema_pensiones', 'JUBILADO')->update(['sistema_pensiones' => 'NINGUNO']);
        $this->permitirPensiones(['AFP', 'ONP', 'NINGUNO']);
    }

    private function permitirPensiones(array $valores): void
    {
        $driver = DB::getDriverName();
        $lista = "'".implode("','", $valores)."'";

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE contracts MODIFY sistema_pensiones ENUM($lista) NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS contracts_sistema_pensiones_check');
            DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_sistema_pensiones_check CHECK (sistema_pensiones::text IN ($lista))");
        }
    }
};
