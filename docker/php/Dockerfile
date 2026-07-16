# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    gosu \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    postgresql-client \
    $PHPIZE_DEPS \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) \
    curl \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    bcmath \
    opcache \
    pcntl \
    soap \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apt-get purge -y --auto-remove $PHPIZE_DEPS \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY app.ini /usr/local/etc/php/conf.d/99-app.ini
COPY fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=755 entrypoint.sh /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data \
  && mkdir -p /var/vault \
  && chown -R www-data:www-data /var/vault

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM runtime AS prod

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=app --chown=www-data:www-data . /var/www/html

RUN composer install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --prefer-dist \
      --no-scripts \
      --classmap-authoritative \
  && chown -R www-data:www-data \
      /var/www/html/bootstrap/cache \
      /var/www/html/storage

FROM runtime AS dev
