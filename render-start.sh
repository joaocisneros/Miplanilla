#!/usr/bin/env bash
echo "==> Preparando MiPlanilla en Render..."

# Crear carpetas de almacenamiento que necesitan session/cache/views en modo archivo
mkdir -p storage/framework/sessions storage/framework/cache/data storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

# Enlace de storage (para archivos publicos)
php artisan storage:link || true

# Limpiar y cachear config (NO se cachea route: hay rutas con closure)
php artisan config:clear || true
php artisan cache:clear || true
php artisan config:cache || true

# Reconstruir la base si el esquema esta incompleto (falta ultimo_acceso)
# o si aun no existe el super admin. Si ya esta todo, solo migra (conserva datos).
CHECK=$(php artisan tinker --execute="try { echo (\Illuminate\Support\Facades\Schema::hasColumn('users','ultimo_acceso') && \App\Models\User::where('email','sistemasdesk04@gmail.com')->exists()) ? 'OK' : 'REBUILD'; } catch (\Throwable \$e) { echo 'REBUILD'; }" 2>/dev/null | tr -dc 'A-Z')

if [ "$CHECK" = "OK" ]; then
  echo "==> Todo en orden: migrando normal (conserva datos)."
  php artisan migrate --force || true
else
  echo "==> Reconstruyendo base y usuarios desde cero..."
  php artisan migrate:fresh --seed --force || true
fi

echo "==> Iniciando servidor en el puerto $PORT ..."
php artisan serve --host=0.0.0.0 --port="$PORT"
