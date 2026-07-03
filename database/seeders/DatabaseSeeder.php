<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Roles y permisos (base para todo)
        $this->call(RolePermissionSeeder::class);

        // 2) Usuarios: se crean SIEMPRE, antes que los datos de ejemplo,
        //    para que el acceso funcione aunque algún seeder de datos falle.

        // SUPER ADMINISTRADOR (único admin principal). La contraseña puede
        // sobreescribirse con la variable de entorno SUPER_ADMIN_PASSWORD.
        $superadmin = User::updateOrCreate(
            ['email' => 'sistemasdesk04@gmail.com'],
            [
                'name' => 'Joao Cisneros',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'Cisneros0404')),
                'email_verified_at' => now(),
            ]
        );
        $superadmin->syncRoles(['ADMIN']);

        // USUARIO DEL CLIENTE (para que pueda entrar a probar).
        $cliente = User::updateOrCreate(
            ['email' => 'jvcisness@gmail.com'],
            [
                'name' => 'CISNEROS SUMA JAVIER',
                'password' => Hash::make(env('CLIENTE_PASSWORD', 'acs123456')),
                'email_verified_at' => now(),
            ]
        );
        $cliente->syncRoles(['ADMIN']);

        // 3) Datos de ejemplo: si alguno falla, se avisa pero NO bloquea a los usuarios.
        foreach ([MaestrosSeeder::class, ConceptosSeeder::class, EmpresasSeeder::class, EmpleadosRealesSeeder::class] as $seeder) {
            try {
                $this->call($seeder);
            } catch (\Throwable $e) {
                $this->command->warn("Seeder {$seeder} no se pudo ejecutar: ".$e->getMessage());
            }
        }
    }
}
