# Imagen base con PHP 8.2
FROM php:8.2-cli

# Dependencias del sistema y extensiones PHP que usa el sistema
RUN apt-get update && apt-get install -y \
        git unzip libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd mbstring bcmath xml \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copiar el proyecto
COPY . /app

# Instalar dependencias PHP (optimizado)
RUN composer install --optimize-autoloader --no-interaction --no-progress

# Permisos de escritura para Laravel
RUN chmod -R 775 storage bootstrap/cache && chmod +x /app/render-start.sh

# Render inyecta el puerto en $PORT
ENV PORT=10000
EXPOSE 10000

CMD ["/app/render-start.sh"]
