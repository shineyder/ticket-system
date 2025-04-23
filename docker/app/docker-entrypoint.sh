#!/bin/sh
# Sai imediatamente se um comando falhar
set -e

# Espera um pouco extra (opcional, mas pode ajudar em casos raros de race condition com o DB)
# sleep 5

echo "Running Laravel migrations..."
# Executa as migrations. O --force é essencial para ambientes não interativos.
php artisan migrate --force
echo "Migrations finished."

# Força o php-fpm a rodar em foreground, ignorando a diretiva 'daemonize' na config
# Isso garante que o processo principal permaneça ativo para o Docker.
exec "$@"
