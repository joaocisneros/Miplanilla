#!/usr/bin/env bash
set -e

echo "==> Preparando MiPlanilla en Render..."

# Enlace de storage (para archivos publicos)
php artisan storage:link || true

# Limpiar y cachear configuracion
php artisan config:clear
php artisan config:cache
php artisan route:cache || true

# Migrar la base de datos (crea las tablas)
php artisan migrate --force

# Sembrar datos de ejemplo SOLO la primera vez (si no hay empresas)
EMPRESAS=$(php artisan tinker --execute="echo \App\Models\Empresa::count();" 2>/dev/null | tr -dc '0-9')
if [ -z "$EMPRESAS" ] || [ "$EMPRESAS" = "0" ]; then
  echo "==> Primera vez: sembrando datos de ejemplo..."
  php artisan db:seed --force || true
else
  echo "==> Ya hay datos ($EMPRESAS empresas), no se siembra."
fi

echo "==> Iniciando servidor en el puerto $PORT ..."
php artisan serve --host=0.0.0.0 --port="$PORT"
