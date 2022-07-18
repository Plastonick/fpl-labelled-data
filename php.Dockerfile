FROM php:8.1-cli-alpine

RUN apk add --no-cache libzip-dev zip icu-dev libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

RUN apk --no-cache add autoconf g++ make \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/pear; apk del autoconf g++ make;

RUN mkdir "/app"
WORKDIR "/app"
COPY . "/app"

ENTRYPOINT ["tail", "-f", "/dev/null"]
