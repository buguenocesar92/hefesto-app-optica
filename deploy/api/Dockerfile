# =============================================================================
# Imagen de la API de OpticaHefesto (Laravel + PHP + nginx + supervisord)
# =============================================================================
#
# UNA SOLA IMAGEN PARA TODOS LOS PROCESOS
# ----------------------------------------
# nginx, php-fpm y el worker de colas conviven en un contenedor y los coordina
# supervisord. Es lo que hacen las demas aplicaciones de Hefesto, y tiene una
# ventaja concreta: el despliegue es una imagen, no tres, y los tres procesos
# tienen exactamente la misma version de PHP y el mismo codigo.
#
# La alternativa (un contenedor por proceso) da un despliegue mas fino a costa de
# tres imagenes que hay que mantener alineadas.
#
# LA BASE
# -------
# `php:8.3-fpm-alpine`. La version de PHP es la que declara `composer.json`
# (`"php": "^8.3"`). Ver docs/04.
# =============================================================================

FROM php:8.3-fpm-alpine

# `postgresql-client` aporta `psql`, que usan el respaldo y la restauracion.
# Sin el, un respaldo no se puede hacer ni restaurar dentro del contenedor.
RUN apk add --no-cache \
        bash \
        postgresql-client \
        libpq-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pdo_mysql \
        bcmath \
        intl \
        opcache \
        zip \
        pcntl \
    && apk del --no-network $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Las dependencias primero, en una capa aparte: mientras `composer.json` no
# cambie, el cache de Docker no invalida la capa de `vendor`.
COPY api/composer.json api/composer.lock* ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY api/ ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --autoloader-optimize \
        --prefer-dist \
        --no-interaction \
    && composer dump-autoload --optimize --no-dev

# La clave y los permisos. `artisan key:generate` NO se corre en la imagen: la
# clave se pasa por entorno en cada despliegue, y generar una clave nueva en
# cada despliegue dejaria invalidas las sesiones y las cookies firmadas.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY deploy/api/docker/nginx.conf /etc/nginx/nginx.conf
COPY deploy/api/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/api/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/api/docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# El contenedor corre como root porque supervisord tiene que levantar nginx y
# php-fpm, que necesitan privilegios para cambiar de usuario. Lo que corre DENTRO
# (php-fpm y el worker) baja a `www-data`. Ver docs/07.
USER root

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]