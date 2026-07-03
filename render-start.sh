#!/usr/bin/env bash
echo "==> Preparando MiPlanilla en Render..."

# Crear carpetas de almacenamiento que necesitan session/cache/views en modo archivo
mkdir -p storage/framework/sessions storage/framework/cache/data storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

# Enlace de storage (para archivos publicos)
php artisan storage:link || true

# Limpiar caches (NO se cachea route porque hay rutas con closure)
php artisan config:clear || true
php artisan cache:clear || true
php artisan config:cache || true

# Migrar la base de datos (crea las tablas)
php artisan migrate --force || echo "AVISO: migrate tuvo un problema (revisar)"

# Sembrar datos de ejemplo SOLO la primera vez (si no hay empresas)
EMPRESAS=$(php artisan tinker --execute="echo \App\Models\Empresa::count();" 2>/dev/null | tr -dc '0-9')
if [ -z "$EMPRESAS" ] || [ "$EMPRESAS" = "0" ]; then
  echo "==> Primera vez: sembrando datos de ejemplo..."
  php artisan db:seed --force || echo "AVISO: seed tuvo un problema (revisar)"
else
  echo "==> Ya hay datos ($EMPRESAS empresas), no se siembra."
fi

echo "==> Iniciando servidor en el puerto $PORT ..."
php artisan serve --host=0.0.0.0 --port="$PORT"
