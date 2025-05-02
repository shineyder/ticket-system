# ==================== ESTÁGIO DE CONSTRUÇÃO ====================
FROM composer:2.8 AS builder

# Install necessary build tools and the mongodb extension BEFORE composer install
RUN apk update && apk add --no-cache autoconf build-base librdkafka-dev libtool libzip-dev m4 php-pear \
    && pecl install -o -f mongodb-2.0.0 rdkafka \
    && docker-php-ext-enable mongodb rdkafka

WORKDIR /app
COPY composer.* ./
RUN composer install --no-scripts --optimize-autoloader --prefer-dist --no-progress
COPY . .

# ==================== Estágio de Desenvolvimento ====================
FROM php:8.3-fpm AS dev

# Copia o executável do Composer do estágio builder
COPY --from=builder /usr/bin/composer /usr/local/bin/composer

# Install xdebug using PECL
RUN pecl install -o -f xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get update -qq && apt-get install -y git librdkafka-dev librdkafka1 libssl-dev libzip-dev unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install -o -f mongodb rdkafka \
    && docker-php-ext-enable mongodb rdkafka \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www
COPY --from=builder /app .
COPY .env .env

# Copia o script de entrypoint para dentro do container
COPY docker/app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Copia o script de teste para dentro do container
COPY docker/app/run-tests.sh /usr/local/bin/run-tests.sh

# Dá permissão de execução aos scripts
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/run-tests.sh

# Define o script como o ponto de entrada do container
ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 9000

CMD ["php-fpm"]

# ==================== ESTÁGIO FINAL (PRODUÇÃO) ====================
FROM php:8.3-cli

# Instala dependências essenciais, configura usuário não-root, permissões e limpa cache em uma única camada
RUN apt-get update -qq && apt-get install -y \
    librdkafka-dev librdkafka1 libssl-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install -o -f mongodb rdkafka \
    && docker-php-ext-enable mongodb rdkafka \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && groupadd -r appuser && useradd -r -g appuser appuser \
    && mkdir -p /var/www/storage \
    && chown -R appuser:appuser /var/www

WORKDIR /var/www
COPY --from=builder --chown=appuser:appuser /app .

USER appuser

CMD ["--host=0.0.0.0", "--port=8000"]
