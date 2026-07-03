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

# Migrar SIN borrar: conserva los datos, usuarios y cambios de contraseña.
# (Ya no se reconstruye: la base ya está creada y sembrada.)
echo "==> Migrando base de datos (conserva todo)..."
php artisan migrate --force || true

echo "==> Iniciando servidor en el puerto $PORT ..."
php artisan serve --host=0.0.0.0 --port="$PORT"
