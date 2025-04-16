<?php

namespace App\Infrastructure\Persistence\MongoDB;

use App\Infrastructure\Persistence\Exceptions\MongoConnectionException;
use MongoDB\Client;
use MongoDB\Database;
use RuntimeException;

class MongoConnection
{
    private Client $client;
    private string $databaseName;

    /**
     * MongoConnection constructor.
     * Establishes the connection to the MongoDB server.
     */
    public function __construct()
    {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', 27017);
        $this->databaseName = env('DB_DATABASE', 'tickets');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $options = env('DB_OPTIONS', '');

        $authPart = '';
        if ($username) {
            $authPart = rawurlencode($username);
            if ($password) {
                $authPart .= ':' . rawurlencode($password);
            }
            $authPart .= '@'; // Adiciona o @ no final da autenticação
        }

        // Build the DSN string
        $dsn = sprintf(
            'mongodb://%s%s:%d%s', // Formato: mongodb://[auth]host:port[/?options]
            $authPart, // %s -> string de autenticação (pode ser vazia)
            $host, // %s -> host (string)
            $port, // %d -> porta (inteiro)
            $options ? '/?' . $options : '' // %s -> opções (pode ser vazia)
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
