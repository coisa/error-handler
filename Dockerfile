ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY . .
# Resolve for this PHP version; the checked-in lock is the PHP 8.5 reference set.
RUN composer update --no-plugins --no-scripts --no-interaction --prefer-dist --no-progress
CMD ["composer", "--no-plugins", "check"]
