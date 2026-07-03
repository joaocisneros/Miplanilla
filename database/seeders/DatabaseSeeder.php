<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Roles y permisos (protegido: si falla, igual se crean los usuarios)
        try {
            $this->call(RolePermissionSeeder::class);
        } catch (\Throwable $e) {
            $this->command->warn('RolePermissionSeeder: '.$e->getMessage());
        }

        // 2) Usuarios: se crean SIEMPRE (aunque falle todo lo demás).
        $this->crearUsuario('sistemasdesk04@gmail.com', 'Joao Cisneros', 'Cisneros0404');
        $this->crearUsuario('jvcisness@gmail.com', 'CISNEROS SUMA JAVIER', 'acs123456');

        // 3) Datos de ejemplo: si alguno falla, se avisa pero NO bloquea nada.
        foreach ([MaestrosSeeder::class, ConceptosSeeder::class, EmpresasSeeder::class, EmpleadosRealesSeeder::class] as $seeder) {
            try {
                $this->call($seeder);
            } catch (\Throwable $e) {
                $this->command->warn("Seeder {$seeder}: ".$e->getMessage());
            }
        }
    }

    /** Crea o actualiza un usuario y le asigna rol ADMIN si el rol existe. */
    private function crearUsuario(string $email, string $name, string $password): void
    {
        try {
            $u = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($password), 'email_verified_at' => now()]
            );
            if (Role::where('name', 'ADMIN')->exists()) {
                $u->syncRoles(['ADMIN']);
            }
            $this->command->info("Usuario listo: {$email}");
        } catch (\Throwable $e) {
            $this->command->warn("No se pudo crear {$email}: ".$e->getMessage());
        }
    }
}
