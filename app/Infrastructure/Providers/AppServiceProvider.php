<?php

namespace App\Infrastructure\Providers;

use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Infrastructure\Persistence\Cache\CachingTicketReadRepository;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoEventStore;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoTicketReadRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind MongoConnection as a singleton to reuse the connection
        $this->app->singleton(MongoConnection::class, function () {
            return new MongoConnection();
        });

        // Bind da interface do Event Store para a implementação MongoDB
        $this->app->bind(
            TicketEventStoreInterface::class,
            MongoEventStore::class
        );

        // --- Configuração do Decorator de Cache para Read Repository ---

        // 1. Registra a implementação concreta do MongoDB (pode ser usada internamente pelo decorator)
        $this->app->bind(MongoTicketReadRepository::class, function ($app) {
            return new MongoTicketReadRepository($app->make(MongoConnection::class));
        });

        // 2. Registra o Decorator de Cache, injetando a implementação concreta e o Cache
        $this->app->bind(CachingTicketReadRepository::class, function ($app) {
            return new CachingTicketReadRepository(
                $app->make(MongoTicketReadRepository::class), // Injeta a implementação real
                $app->make(CacheManager::class) // Injeta o  cache manager
            );
        });

        // 3. Faz o bind da INTERFACE principal para resolver o DECORATOR
        $this->app->bind(
            TicketReadRepositoryInterface::class,
            CachingTicketReadRepository::class // Agora, quem pedir a interface receberá o decorator
        );

        // --- Fim da Configuração do Decorator ---
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
