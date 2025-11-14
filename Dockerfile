ARG APP_USER_NAME=app
ARG APP_USER_UID=1000
ARG APP_USER_GID=1000

FROM ghcr.io/roadrunner-server/roadrunner:2025.1.3 AS roadrunner

FROM php:8.4-cli AS base
ARG APP_USER_NAME
ARG APP_USER_UID
ARG APP_USER_GID
ARG DEBIAN_FRONTEND=noninteractive

ENV COMPOSER_FLAGS="--prefer-dist --no-interaction"
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_PROCESS_TIMEOUT=3600
ENV DD_PHP_TRACER_VERSION=1.12.1
ENV APP_USER_NAME=$APP_USER_NAME
ENV APP_USER_UID=$APP_USER_UID
ENV APP_USER_GID=$APP_USER_GID

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

WORKDIR /tmp/

RUN apt-get update -q \
 && apt-get install -y --no-install-recommends \
      ca-certificates \
      git \
      jq \
      unzip \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions @composer pdo_mysql sockets

# create app user
RUN groupadd -g $APP_USER_GID $APP_USER_NAME \
 && useradd -m -u $APP_USER_UID -g $APP_USER_GID $APP_USER_NAME

## Datadog install
RUN curl -Lf "https://github.com/DataDog/dd-trace-php/releases/download/${DD_PHP_TRACER_VERSION}/datadog-setup.php" > /tmp/datadog-setup.php \
 && php /tmp/datadog-setup.php --php-bin=all \
 && rm /tmp/datadog-setup.php

# install roadrunner
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY docker/.rr.yaml /code/

# configure php
COPY ./docker/php.ini /usr/local/etc/php/php.ini

WORKDIR /code/
EXPOSE 8080
ENTRYPOINT ["/code/bin/app-entrypoint.sh"]
CMD ["rr", "serve"]


FROM base AS dev
ARG APP_USER_NAME

RUN install-php-extensions xdebug

USER $APP_USER_NAME
CMD ["rr", "serve", "-c", "docker/.rr.dev.yaml", "-w", "."]


FROM base AS app
ARG APP_USER_NAME

RUN docker-php-ext-enable opcache

COPY composer.* symfony.lock docker/.env.local.php /code/
RUN composer install $COMPOSER_FLAGS --no-scripts

COPY . /code/

RUN mkdir -p public/docs \
 && cp docs/swagger.yaml public/docs \
 && mkdir var/ \
 && chown -R "${APP_USER_NAME}:${APP_USER_NAME}" var/

USER $APP_USER_NAME
