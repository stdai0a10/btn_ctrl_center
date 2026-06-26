ARG PHP_VERSION=8.2
ARG ALPINE_VER=3.23

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VER} as php_builder

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
COPY . /var/www/app

RUN composer install --no-interaction --no-plugins --no-scripts --no-dev --prefer-dist && \
    composer dump-autoload --optimize

FROM node:24 AS node_builder

WORKDIR /var/www/app
COPY . /var/www/app

RUN npm install &&\
    npm run build

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VER}

ARG PUID=2000
ARG PGID=2000
ENV PUID ${PUID}
ENV PGID ${PGID}

### php
RUN apk add --no-cache --update --virtual .build-deps shadow $PHPIZE_DEPS libtool libzip-dev openssl-dev zlib-dev && \
    usermod -u ${PUID} www-data && \
    groupmod -g ${PGID} www-data && \
    docker-php-ext-install opcache pdo_mysql && \
    apk del .build-deps

### php opcache
ARG OPCACHE_ENABLE=1
ENV OPCACHE_ENABLE ${OPCACHE_ENABLE}
ENV OPCACHE_MEMORY_CONSUMPTION 256
ENV OPCACHE_INTERNED_STRINGS_BUFFER 16
ENV OPCACHE_MAX_ACCELERATED_FILES 7963

### supervisor
RUN apk add --no-cache --update supervisor

### crontab
ENV SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.46/supercronic-linux-amd64 \
    SUPERCRONIC_SHA1SUM=5bcefed628e32adc08e32634db2d10e9230dbca0 \
    SUPERCRONIC=supercronic-linux-amd64

RUN curl -fsSLO "$SUPERCRONIC_URL" \
 && echo "${SUPERCRONIC_SHA1SUM}  ${SUPERCRONIC}" | sha1sum -c - \
 && chmod +x "$SUPERCRONIC" \
 && mv "$SUPERCRONIC" "/usr/local/bin/${SUPERCRONIC}" \
 && ln -s "/usr/local/bin/${SUPERCRONIC}" /usr/local/bin/supercronic

### nginx
RUN apk add --no-cache nginx

### project
ADD docker/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod 755 /docker-entrypoint.sh

ADD php.ini /usr/local/etc/php/php.ini
ADD php.d/ /usr/local/etc/php/conf.d/
ADD php-fpm.conf /usr/local/etc/php-fpm.conf

ADD nginx.conf /etc/nginx/nginx.conf
ADD nginx.d/ /etc/nginx/http.d/

ADD supervisord.conf /etc/supervisord.conf
ADD supervisor.d/ /etc/supervisor/conf.d/

COPY --from=php_builder /var/www/app/vendor /var/www/app/vendor
COPY --from=node_builder /var/www/app/public/build /var/www/app/public/build
COPY . /var/www/app
WORKDIR /var/www/app
RUN chmod -R 777 storage/

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD /usr/bin/supervisord -c /etc/supervisord.conf
