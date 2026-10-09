# Fresh Statamic 6 site with the addon installed from this checkout (copied to /addon).
# Build context is the repo root: docker compose -f docker/site/compose.yaml build
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpng-dev libjpeg-dev libwebp-dev libzip-dev libicu-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd zip intl exif \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer/composer:2-bin /composer /usr/bin/composer

WORKDIR /site
RUN composer create-project statamic/statamic . --no-interaction

COPY composer.json /addon/
COPY config /addon/config
COPY resources /addon/resources
COPY routes /addon/routes
COPY src /addon/src
RUN composer config repositories.tinify path /addon \
 && composer require thijsdezoete/tinify-statamic:@dev --no-interaction \
 && sed -i 's#^APP_URL=.*#APP_URL=http://localhost:8080#' .env
# The starter kit defaults to Eloquent users without a model; flat-file users are enough here.
RUN sed -i "s/'repository' => 'eloquent'/'repository' => 'file'/" config/statamic/users.php \
 && php please make:user admin@example.com --password=password --super

EXPOSE 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
