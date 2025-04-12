<?php

namespace App\Infrastructure\Providers;

use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoEventStore;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoTicketReadRepository;
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

        // Bind da interface do Read Repository
        $this->app->bind(
            TicketReadRepositoryInterface::class,
            MongoTicketReadRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
