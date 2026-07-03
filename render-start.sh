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

# Detectar si el esquema esta completo (columna ultimo_acceso presente).
# Si falta (esquema incompleto o base nueva), se reconstruye todo desde cero.
COLCHECK=$(php artisan tinker --execute="try { echo \Illuminate\Support\Facades\Schema::hasColumn('users','ultimo_acceso') ? 'COMPLETO' : 'FALTA'; } catch (\Throwable \$e) { echo 'FALTA'; }" 2>/dev/null | tr -dc 'A-Z')

if [ "$COLCHECK" = "COMPLETO" ]; then
  echo "==> Esquema completo: migrando normal (conserva datos)."
  php artisan migrate --force || true
else
  echo "==> Esquema incompleto o base nueva: reconstruyendo desde cero..."
  php artisan migrate:fresh --seed --force || php artisan migrate --force || true
fi

echo "==> Iniciando servidor en el puerto $PORT ..."
php artisan serve --host=0.0.0.0 --port="$PORT"
