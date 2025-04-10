<?php

namespace App\Infrastructure\Persistence\MongoDB;

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

        // Build the DSN string
        $dsn = sprintf(
            'mongodb://%s%s%s:%d%s',
            $username ? rawurlencode($username) : '',
            $password ? ':' . rawurlencode($password) : '',
            $username ? '@' : '', // Add '@' only if username is provided
            $host,
            $port,
            $options ? '/?' . $options : ''
        );

        try {
            // Create the MongoDB client instance
            $this->client = new Client($dsn);
        } catch (\Exception $e) {
            // Handle connection errors appropriately
            throw new RuntimeException("Could not connect to MongoDB: " . $e->getMessage(), 0, $e);
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
