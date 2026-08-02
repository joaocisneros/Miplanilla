# Preparar primero Laravel y sus dependencias PHP. El frontend importa Ziggy
# desde vendor/tightenco/ziggy, por lo que Composer debe ejecutarse antes.
FROM php:8.2-cli AS php-app

RUN apt-get update && apt-get install -y \
        git unzip libpq-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip gd mbstring bcmath xml \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --optimize-autoloader --no-interaction --no-progress

# Compilar Vue/Tailwind usando el Ziggy instalado por Composer.
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . /app
COPY --from=php-app /app/vendor/tightenco/ziggy /app/vendor/tightenco/ziggy
RUN npm run build

# Imagen final: Laravel preparado y el frontend recien compilado.
FROM php-app

COPY --from=frontend /app/public/build /app/public/build

RUN chmod -R 775 storage bootstrap/cache && chmod +x /app/render-start.sh

ENV PORT=10000
EXPOSE 10000

CMD ["/app/render-start.sh"]
