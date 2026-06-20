<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            MaestrosSeeder::class,
            ConceptosSeeder::class,
            EmpresasSeeder::class,
            EmpleadosRealesSeeder::class,
        ]);

        // Usuario ADMIN inicial (cambiar contraseña tras el primer ingreso)
        $admin = User::firstOrCreate(
            ['email' => 'admin@miplanilla.test'],
            ['name' => 'Administrador', 'password' => bcrypt('password')]
        );
        $admin->assignRole('ADMIN');
    }
}
