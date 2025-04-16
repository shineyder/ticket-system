<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException; // Para capturar erros específicos do MongoDB

return new class extends Migration
{
    // Especifica que esta migration deve rodar na conexão 'mongodb'
    protected $connection = 'mongodb';

    /**
     * Run the migrations.
     * Cria coleções (implicitamente ou explicitamente se necessário) e índices.
     */
    public function up(): void
    {
        // --- Coleções do Laravel ---

        // Sessions
        $this->createCollectionAndIndexes('sessions', [
            // Índice para limpeza de sessões expiradas
            ['key' => ['last_activity' => 1], 'name' => 'sessions_last_activity_index'],
            // O campo 'id' será o '_id' do MongoDB, que já é único e indexado.
        ]);

        // Cache
        $this->createCollectionAndIndexes('cache', [
            // Índice para limpeza de cache expirado
            ['key' => ['expiration' => 1], 'name' => 'cache_expiration_index'],
            // O campo 'key' será o '_id' do MongoDB, que já é único e indexado.
        ]);

        // Cache Locks
        $this->createCollectionAndIndexes('cache_locks', [
            // Índice para limpeza de locks expirados
            ['key' => ['expiration' => 1], 'name' => 'cache_locks_expiration_index'],
            // O campo 'key' será o '_id' do MongoDB, que já é único e indexado.
        ]);

        // Users
        $this->createCollectionAndIndexes('users', [
            // Garante que o email seja único
            ['key' => ['email' => 1], 'name' => 'users_email_unique', 'unique' => true],
            // O campo 'id' do blueprint será o '_id' do MongoDB.
        ]);

        // Password Reset Tokens
        $this->createCollectionAndIndexes('password_reset_tokens', [
            // O campo 'email' será o '_id' do MongoDB, que já é único e indexado.
            // Índice para busca por token, se necessário
            ['key' => ['token' => 1], 'name' => 'password_reset_tokens_token_index'],
            // Índice para limpeza por data de criação
            ['key' => ['created_at' => 1], 'name' => 'password_reset_tokens_created_at_index'],
        ]);

        // --- Coleções da Aplicação ---

        // Ticket Events (Event Store)
        $this->createCollectionAndIndexes('ticket_events', [
            // Índice composto essencial para carregar eventos de um agregado na ordem correta
            ['key' => ['aggregate_id' => 1, 'sequence_number' => 1], 'name' => 'ticket_events_aggregate_sequence_index', 'unique' => true],
            // Índice opcional para buscar por tipo de evento
            // ['key' => ['event_type' => 1], 'name' => 'ticket_events_event_type_index'],
        ]);

        // Ticket Read Models (Projeção)
        $this->createCollectionAndIndexes('ticket_read_models', [
            // Índice único para buscar por ID do ticket (já criado no repo, mas bom garantir aqui)
            ['key' => ['ticket_id' => 1], 'name' => 'ticket_read_models_ticket_id_unique', 'unique' => true],
            // Índices para filtros e ordenação comuns na listagem
            ['key' => ['status' => 1], 'name' => 'ticket_read_models_status_index'],
            ['key' => ['priority' => 1], 'name' => 'ticket_read_models_priority_index'],
            ['key' => ['created_at' => 1], 'name' => 'ticket_read_models_created_at_index'],
            ['key' => ['last_updated_at' => 1], 'name' => 'ticket_read_models_last_updated_at_index'],
        ]);

        // --- Coleções de Filas ---
        $this->createCollectionAndIndexes('jobs', [
            ['key' => ['queue' => 1], 'name' => 'jobs_queue_index'],
        ]);

        $this->createCollectionAndIndexes('job_batches', [
            // O campo 'id' será o '_id' do MongoDB.
        ]);

        $this->createCollectionAndIndexes('failed_jobs', [
            ['key' => ['uuid' => 1], 'name' => 'failed_jobs_uuid_unique', 'unique' => true],
        ]);
    }

    /**
     * Reverse the migrations.
     * Remove os índices criados. A remoção das coleções é opcional e mais destrutiva.
     */
    public function down(): void
    {
        // --- Coleções do Laravel ---
        $this->dropIndexes('sessions', ['sessions_last_activity_index']); // Adicionar 'sessions_user_id_index' se criado
        $this->dropIndexes('cache', ['cache_expiration_index']);
        $this->dropIndexes('cache_locks', ['cache_locks_expiration_index']);
        $this->dropIndexes('users', ['users_email_unique']);
        $this->dropIndexes('password_reset_tokens', ['password_reset_tokens_token_index', 'password_reset_tokens_created_at_index']);

        // --- Coleções da Aplicação ---
        $this->dropIndexes('ticket_events', ['ticket_events_aggregate_sequence_index']); // Adicionar 'ticket_events_event_type_index' se criado
        $this->dropIndexes('ticket_read_models', [
            'ticket_read_models_ticket_id_unique',
            'ticket_read_models_status_index',
            'ticket_read_models_priority_index',
            'ticket_read_models_created_at_index',
            'ticket_read_models_last_updated_at_index',
        ]);

        // --- Coleções de Filas (Opcional) ---
        $this->dropIndexes('jobs', ['jobs_queue_index']);
        // job_batches não tem índices explícitos além do _id
        $this->dropIndexes('failed_jobs', ['failed_jobs_uuid_unique']);
    }

    /**
     * Helper para criar uma coleção (se não existir) e seus índices.
     *
     * @param string $collectionName Nome da coleção.
     * @param array $indexes Array de definições de índice (cada item é ['key' => [...], 'name' => '...', 'unique' => bool]).
     */
    private function createCollectionAndIndexes(string $collectionName, array $indexes): void
    {
        $db = DB::connection($this->connection)->getDatabase();

        // Tenta criar a coleção explicitamente (ignora erro se já existir)
        try {
            $db->createCollection($collectionName);
            echo "Coleção '$collectionName' criada ou já existente.\n";
        } catch (CommandException $e) {
            // Código 48: NamespaceExists (coleção já existe) - Ignorar este erro específico
            if ($e->getCode() !== 48) {
                throw $e; // Relança outros erros
            }
            echo "Coleção '$collectionName' já existente.\n";
        }

        $collection = $db->selectCollection($collectionName);

        // Cria os índices definidos
        if (!empty($indexes)) {
            try {
                $collection->createIndexes($indexes);
                echo "Índices para '$collectionName' criados com sucesso.\n";
            } catch (CommandException $e) {
                // Tratar erros de criação de índice se necessário, mas geralmente relançar
                echo "Erro ao criar índices para '$collectionName': " . $e->getMessage() . "\n";
                // throw $e; // Descomentar para parar a migração em caso de erro de índice
            }
        }
    }

    /**
     * Helper para remover índices de uma coleção.
     *
     * @param string $collectionName Nome da coleção.
     * @param array $indexNames Nomes dos índices a serem removidos.
     */
    private function dropIndexes(string $collectionName, array $indexNames): void
    {
        $collection = DB::connection($this->connection)->getCollection($collectionName);

        foreach ($indexNames as $indexName) {
            try {
                $collection->dropIndex($indexName);
                echo "Índice '$indexName' removido da coleção '$collectionName'.\n";
            } catch (CommandException $e) {
                // Código 27: IndexNotFound - Ignorar se o índice não existir
                if ($e->getCode() === 27) {
                    echo "Índice '$indexName' não encontrado na coleção '$collectionName' (ignorado).\n";
                } else {
                    echo "Erro ao remover índice '$indexName' da coleção '$collectionName': " . $e->getMessage() . "\n";
                    // throw $e; // Descomentar para parar a migração em caso de erro
                }
            } catch (\Exception $e) {
                echo "Erro inesperado ao remover índice '$indexName' da coleção '$collectionName': " . $e->getMessage() . "\n";
                // throw $e; // Descomentar para parar a migração em caso de erro
            }
        }
    }
};
