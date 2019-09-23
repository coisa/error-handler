FROM php:7.2-alpine

ARG APP_DIR="/app"
ARG APP_VERSION="latest"

ENV APP_DIR="${APP_DIR}" \
    APP_VERSION="${APP_VERSION}"

RUN apk add --update \
        make \
        git \
        zip \
        unzip \
    ;

RUN apk add --no-cache $PHPIZE_DEPS && \
    pecl install xdebug-2.6.0 && \
    docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN addgroup app && \
    adduser -D -h ${APP_DIR} -G app app && \
    mkdir -p ${APP_DIR}/.composer

WORKDIR ${APP_DIR}
USER app

COPY --chown=app . ${APP_DIR}/

RUN make install
