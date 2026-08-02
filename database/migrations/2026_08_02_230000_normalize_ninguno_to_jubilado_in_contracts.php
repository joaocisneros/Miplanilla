<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contracts')
            ->where('sistema_pensiones', 'NINGUNO')
            ->update(['sistema_pensiones' => 'JUBILADO']);
    }

    public function down(): void
    {
        DB::table('contracts')
            ->where('sistema_pensiones', 'JUBILADO')
            ->update(['sistema_pensiones' => 'NINGUNO']);
    }
};
