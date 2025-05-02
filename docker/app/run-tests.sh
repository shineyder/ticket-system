#!/bin/sh

# Define explicitamente o ambiente como 'testing' para este processo
export APP_ENV=testing
export QUEUE_CONNECTION=sync

# Limpa o cache de configuração para garantir que as variáveis do phpunit.xml sejam lidas
php artisan config:clear

# Executa o PHPUnit passando todos os argumentos recebidos pelo script
vendor/bin/phpunit "$@"
