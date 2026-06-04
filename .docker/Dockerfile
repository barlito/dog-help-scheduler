FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    vim \
	acl \
#    supervisor \
	&& rm -rf /var/lib/apt/lists/*

# Create non-root user with configurable UID/GID
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN groupadd -g ${GROUP_ID} www && \
    useradd -u ${USER_ID} -g www -m -s /bin/bash www && \
    chown -R www:www /app /data /config

# add additional extensions here:
RUN install-php-extensions \
    @composer \
	intl \
	zip \
	opcache \
    apcu \
	pdo_pgsql \
    pcntl
#    bcmath \
#    amqp

# Shared PHP config (dev + prod) — notably date.timezone = Europe/Paris so dates
# are created/hydrated/displayed in the app timezone instead of UTC.
COPY --link .docker/php/app.ini $PHP_INI_DIR/conf.d/

ENV COMPOSER_HOME=/home/www/.composer
# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

# Dev Image
FROM frankenphp_base AS frankenphp_dev

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Switch to non-root user
USER www

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]

### Composer deps stage — vendor pré-installé (couche cache séparée des sources) ###
FROM frankenphp_base AS composer_deps
WORKDIR /app
COPY composer.* symfony.* ./
RUN set -eux; \
	composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

### Prod Image ###
FROM frankenphp_base AS frankenphp_prod
ENV APP_ENV=prod

ARG APP_VERSION=dev
ENV APP_VERSION=$APP_VERSION

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Tuning OPcache prod
COPY --link .docker/php/app.prod.ini $PHP_INI_DIR/conf.d/

# Caddyfile baked dans l'image (en dev il est monté en volume pour live edit)
COPY --link .docker/franken/Caddyfile /etc/caddy/Caddyfile

# Vendor composer pré-installé (couche cache séparée)
COPY --from=composer_deps /app/vendor ./vendor
COPY --link composer.* symfony.* ./

# Sources app (migrations incluses pour le doctrine:migrations:migrate au deploy)
COPY --link . ./

# Nettoyage des fichiers inutiles en prod
RUN rm -rf .docker .github .git

# Autoload optimisé + dump-env prod + post-install (cache:clear, assets:install EasyAdmin)
RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; \
	chown -R www:www var public; \
	sync

USER www

### Worker Prod Image — même image, on consomme Messenger au lieu de servir le web ###
FROM frankenphp_prod AS frankenphp_worker_prod
USER root
# Le worker ne lance pas Caddy : neutralise le HEALTHCHECK de la base (sonde :2019)
# qui le marquerait à tort "unhealthy".
HEALTHCHECK NONE
USER www
CMD [ "php", "bin/console", "messenger:consume", "scheduler_app", "async", "--time-limit=3600", "-v" ]
