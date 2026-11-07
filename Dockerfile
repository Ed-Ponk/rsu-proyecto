# syntax=docker/dockerfile:1
# ============================================================================
# Imagen del Sistema RSU (Laravel 12 + PHP 8.3 + Apache).
# Se construye en tres etapas para que la imagen final sea liviana y no lleve
# herramientas de desarrollo, pruebas, ni archivos secretos (.env).
# ============================================================================

# ---------- Etapa 1: dependencias PHP (Composer) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --ignore-platform-reqs: las extensiones (gd, intl...) se instalan en la imagen final, no en esta etapa.
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts --ignore-platform-reqs

# ---------- Etapa 2: recursos del front-end (Vite) ----------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
# Tailwind lee las vistas y los paquetes de Jetstream para saber qué clases usar.
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------- Etapa 3: imagen final (PHP + Apache) ----------
FROM php:8.3-apache AS app

LABEL org.opencontainers.image.title="RSU - Sistema de Residuos Sólidos Urbanos" \
      org.opencontainers.image.description="Laravel 12 + PHP 8.3 + Apache. Municipalidad Distrital de José Leonardo Ortiz." \
      org.opencontainers.image.source="https://github.com/Ed-Ponk/rsu-proyecto" \
      org.opencontainers.image.version="1.2"

ENV TZ=America/Lima \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

# Extensiones de PHP que necesita la aplicación (imágenes de vehículos, ubigeo, mapas, números, etc.).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev libicu-dev curl unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip intl bcmath exif opcache \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

# Configuración de PHP (producción) y de Apache (DocumentRoot en /public y .htaccess de Laravel).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-rsu.ini"
COPY docker/apache-laravel.conf /etc/apache2/conf-available/laravel.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enconf laravel

WORKDIR /var/www/html

# Código de la aplicación + dependencias + assets compilados.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Carpetas de trabajo de Laravel con permisos para Apache (www-data).
RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/uploads \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && chmod -R ug+rwX storage bootstrap/cache public/uploads \
    && php artisan package:discover --ansi

# Script de arranque: espera a la base de datos, migra, siembra y deja la app lista.
COPY docker/entrypoint.sh /usr/local/bin/rsu-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/rsu-entrypoint && chmod +x /usr/local/bin/rsu-entrypoint

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
    CMD curl -fsS http://localhost/up >/dev/null || exit 1

ENTRYPOINT ["rsu-entrypoint"]
CMD ["apache2-foreground"]
