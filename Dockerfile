# ==================== ESTÁGIO DE CONSTRUÇÃO ====================
FROM composer:2.7 AS builder

WORKDIR /app
COPY . .
RUN composer install --no-scripts --optimize-autoloader --prefer-dist --no-progress

# ==================== Estágio de Desenvolvimento ====================
FROM php:8.3-cli AS dev

RUN docker-php-ext-install xdebug && docker-php-ext-enable xdebug \
    && apt-get update -qq && apt-get install -y git librdkafka-dev librdkafka1 libzip-dev unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install -o -f mongodb rdkafka \
    && docker-php-ext-enable mongodb rdkafka \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && wget -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www
COPY --from=builder /app .

# ==================== ESTÁGIO FINAL (PRODUÇÃO) ====================
FROM php:8.3-cli

# Instala dependências essenciais, configura usuário não-root, permissões e limpa cache em uma única camada
RUN apt-get update -qq && apt-get install -y \
    librdkafka-dev librdkafka1 libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install -o -f mongodb rdkafka \
    && docker-php-ext-enable mongodb rdkafka \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && groupadd -r appuser && useradd -r -g appuser appuser \
    && mkdir -p /var/www/storage \
    && chown -R appuser:appuser /var/www

WORKDIR /var/www
COPY --from=builder --chown=appuser:appuser /app .

USER appuser

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
