<?php

namespace Tests\Unit\Infrastructure\Persistence\MongoDB;

use App\Infrastructure\Persistence\Exceptions\MongoConnectionException;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use MongoDB\Client;
use MongoDB\Database;
use Tests\TestCase;

class MongoConnectionTest extends TestCase
{
    /**
     * Define variáveis de ambiente para um teste específico.
     * @param array<string, string|null> $envVars
     */
    private function setTestConfig(array $envVars): void
    {
        foreach ($envVars as $key => $value) {
            config()->set("database.connections.mongodb.$key", $value);
        }
    }

    /**
     * Usa Reflection para obter o DSN do cliente MongoDB privado.
     */
    private function getClientDsn(MongoConnection $connection): string
    {
        $reflection = new \ReflectionClass($connection);
        $clientProperty = $reflection->getProperty('client');
        $client = $clientProperty->getValue($connection);//NOSONAR
        return (string) $client; // Casting para string retorna o DSN
    }

    /** @test */
    public function it_builds_correct_dsn_with_defaults(): void
    {
        // Arrange
        $this->setTestConfig([
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'testdb',
            'username' => null,
            'password' => null,
            'options' => [], // Opções como array vazio
        ]);

        // Act
        $connection = $this->app->make(MongoConnection::class); // Resolve via container
        $dsn = $this->getClientDsn($connection);

        // Assert
        $this->assertEquals('mongodb://localhost:27017', $dsn);
        $this->assertInstanceOf(Database::class, $connection->getDatabase());
        $this->assertEquals('testdb', $connection->getDatabase()->getDatabaseName());
    }

    /** @test */
    public function it_builds_correct_dsn_with_username_and_password(): void
    {
        // Arrange: Define username e password (cobre linha 29)
        $this->setTestConfig([
            'host' => 'securehost',
            'port' => 27018,
            'database' => 'securedb',
            'username' => 'testuser',
            'password' => 'testpass',
            'options' => [],
        ]);

        // Act
        $connection = $this->app->make(MongoConnection::class);
        $dsn = $this->getClientDsn($connection);

        // Assert
        $this->assertEquals('mongodb://testuser:testpass@securehost:27018', $dsn);//NOSONAR
    }

    /** @test */
    public function it_builds_correct_dsn_with_username_only(): void
    {
        // Arrange: Define apenas username (NÃO aciona linha 29)
        $this->setTestConfig([
            'host' => 'userhost',
            'port' => 27019,
            'database' => 'userdb',
            'username' => 'onlyuser',
            'password' => null,
            'options' => [],
        ]);

        // Act
        $connection = $this->app->make(MongoConnection::class);
        $dsn = $this->getClientDsn($connection);

        // Assert
        $this->assertEquals('mongodb://onlyuser@userhost:27019', $dsn);
    }

    /** @test */
    public function it_builds_correct_dsn_with_options(): void
    {
        // Arrange
        $this->setTestConfig([
            'host' => 'optionhost',
            'port' => 27020,
            'database' => 'optiondb',
            'username' => null,
            'password' => null,
            'options' => ['replicaSet' => 'myReplica', 'readPreference' => 'primary'], // Opções como array
        ]);

        // Act
        $connection = $this->app->make(MongoConnection::class);
        $dsn = $this->getClientDsn($connection);

        // Assert
        $this->assertEquals('mongodb://optionhost:27020/?replicaSet=myReplica&readPreference=primary', $dsn);
    }

    /** @test */
    public function it_throws_mongo_connection_exception_on_client_creation_failure(): void
    {
        // Arrange: Define host inválido para forçar erro (cobre linha 49)
        $this->setTestConfig([
            'host' => 'invalid-host-format::', // Formato inválido
            'port' => 27017,
            'database' => 'faildb',
            'username' => null,
            'password' => null,
            'options' => [],
        ]);

        // Assert
        $this->expectException(MongoConnectionException::class);

        // Act: Resolve via container, o que chama o construtor
        $this->app->make(MongoConnection::class);
    }

    /** @test */
    public function get_database_returns_correct_database_instance(): void
    {
        // Arrange
        $this->setTestConfig([
            'host' => 'localhost', // Precisa de host/porta válidos para instanciar
            'port' => 27017,
            'database' => 'defaultdb',
            'username' => null,
            'password' => null,
            'options' => [],
        ]);
        $connection = $this->app->make(MongoConnection::class);

        // Act & Assert
        $this->assertEquals('defaultdb', $connection->getDatabase()->getDatabaseName());
        $this->assertEquals('overridedb', $connection->getDatabase('overridedb')->getDatabaseName());
    }

    /** @test */
    public function get_client_returns_client_instance(): void
    {
        // Arrange
        $this->setTestConfig([
            'host' => 'localhost',
            'port' => 27017,
            'database' => 'anydb',
            'username' => null,
            'password' => null,
            'options' => [],
        ]);
        $connection = $this->app->make(MongoConnection::class);

        // Act & Assert
        $this->assertInstanceOf(Client::class, $connection->getClient());
    }
}
