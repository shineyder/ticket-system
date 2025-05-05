<?php

namespace App\Infrastructure\Persistence\MongoDB;

use App\Infrastructure\Persistence\Exceptions\MongoConnectionException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use MongoDB\Client;
use MongoDB\Database;
class MongoConnection
{
    private Client $client;
    private string $databaseName;

    /**
     * MongoConnection constructor.
     * Establishes the connection to the MongoDB server.
     * @param ConfigRepository $config
     */
    public function __construct(ConfigRepository $config)
    {
        // Ler a configuração do repositório injetado
        $host = $config->get('database.connections.mongodb.host', '127.0.0.1');
        $port = $config->get('database.connections.mongodb.port', 27017);
        $this->databaseName = $config->get('database.connections.mongodb.database', 'tickets');
        $username = $config->get('database.connections.mongodb.username');
        $password = $config->get('database.connections.mongodb.password');
        $optionsArray = $config->get('database.connections.mongodb.options', []); // Espera um array de opções

        $authPart = '';
        if ($username) {
            $authPart = rawurlencode($username);
            if ($password) {
                $authPart .= ':' . rawurlencode($password);
            }
            $authPart .= '@'; // Adiciona o @ no final da autenticação
        }

        // Constrói a string de opções a partir do array
        $optionsString = '';
        if (!empty($optionsArray)) {
            $optionsString = http_build_query($optionsArray);
        }

        // Build the DSN string
        $dsn = sprintf(
            'mongodb://%s%s:%d%s', // Formato: mongodb://[auth]host:port[/?options]
            $authPart, // %s -> string de autenticação (pode ser vazia)
            $host, // %s -> host (string)
            $port, // %d -> porta (inteiro)
            $optionsString ? '/?' . $optionsString : '' // %s -> opções construídas
        );

        try {
            // Create the MongoDB client instance
            $this->client = new Client($dsn);
        } catch (\Exception $e) {
            // Handle connection errors appropriately
            throw new MongoConnectionException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the MongoDB database instance.
     *
     * @param string|null $databaseName Optional database name override. Uses default if null.
     * @return Database
     */
    public function getDatabase(?string $databaseName = null): Database
    {
        return $this->client->selectDatabase($databaseName ?? $this->databaseName);
    }

    /**
     * Get the MongoDB client instance.
     * Useful for more advanced operations if needed.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}
