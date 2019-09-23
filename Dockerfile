FROM php:7.2-alpine

ARG APP_DIR="/app"
ARG APP_VERSION="latest"

ENV APP_DIR="${APP_DIR}" \
    APP_VERSION="${APP_VERSION}"

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk add --update \
        make \
        git \
        zip \
        unzip \
    ;

RUN addgroup app && \
    adduser -D -h ${APP_DIR} -G app app && \
    mkdir -p ${APP_DIR}/.composer

WORKDIR ${APP_DIR}
USER app

COPY --chown=app . ${APP_DIR}/

RUN make install
