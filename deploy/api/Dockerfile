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

# =============================================================================
# CVE-2026-109618 / CVE-2026-109619 (PHP2Shell, 2026-10-22)
# =============================================================================
# La imagen se construye con PHP 8.3.36, que es la version PARCHEADA
# de la serie 8.3. Las versiones parcheadas de PHP son
# 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12.
#
# Verificado el 2026-10-22 en el servidor de desarrollo, con el tag exacto:
#
#     $ docker run --rm php:8.3-fpm-alpine php -v
#     PHP 8.3.36 (cli) (built: Oct 21 2026 22:54:11)
#
# El tag `php:8.3-fpm-alpine` es FLOTANTE: no se cambia, se deja constancia.
# El tag resuelve hoy a PHP 8.3.36, que es la version parcheada de la serie
# 8.3. Se deja escrito porque un tag flotante puede mudar sin que nadie lo
# note: la comprobacion va en el candado (api/tests/Unit/), que falla si el
# tag vuelve a quedar por debajo de la version parcheada.
#
# OJO: LA IMAGEN DESPLIEGADA HAY QUE RECONSTRUIRLA. El parche de PHP entra en
# el contenedor por la IMAGEN, no por este repositorio: mientras no se
# reconstruya y se redespliegue, el contenedor sigue con el PHP viejo. El
# endurecimiento de mas abajo (el ini y el pool de php-fpm) es la red de seguridad
# para ese mientras, y lo que sobrevive a un redeploy.
FROM php:8.3-fpm-alpine

# `postgresql-client` aporta `psql`, que usan el respaldo y la restauracion.
# Sin el, un respaldo no se puede hacer ni restaurar dentro del contenedor.
RUN apk update \
    && apk upgrade \
    && apk add --no-cache \
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

# Endurecimiento PHP2Shell (CVE-2026-109618 / CVE-2026-109619):
# la via CONFIRMADA (session.upload_progress) apagada, y los
# trabajadores de php-fpm reciclados cada 100 peticiones.
COPY deploy/api/docker/99-php2shell-hardening.ini /usr/local/etc/php/conf.d/99-php2shell-hardening.ini
COPY deploy/api/docker/zz-php2shell-fpm.conf /usr/local/etc/php-fpm.d/zz-php2shell-hardening.conf
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