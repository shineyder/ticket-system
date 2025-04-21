# Sai imediatamente se um comando falhar
set -e

# Espera um pouco extra (opcional, mas pode ajudar em casos raros de race condition com o DB)
# sleep 5

echo "Running Laravel migrations..."
# Executa as migrations. O --force é essencial para ambientes não interativos.
php artisan migrate --force
echo "Migrations finished."

# Agora, executa o comando original que foi passado para o container
# (que será o CMD do Dockerfile, ou seja, 'php-fpm')
# O 'exec' substitui o processo do shell pelo php-fpm,
# o que é importante para o correto tratamento de sinais (como SIGTERM para parar o container).
exec "$@"
