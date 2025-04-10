<?php

namespace App\Infrastructure\Providers;

use App\Application\Commands\CreateTicket\CreateTicketHandler;
use App\Application\Commands\ResolveTicket\ResolveTicketHandler;
use App\Application\Query\GetAllTickets\GetAllTicketsHandler;
use App\Application\Query\GetTicketById\GetTicketByIdHandler;
use App\Domain\Interfaces\Repositories\TicketRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoTicketRepository;
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

        // Bind the interface to the concrete implementation
        $this->app->bind(TicketRepository::class, MongoTicketRepository::class);

        // No futuro haverá Event Store e Read Models
        // $this->app->bind(TicketEventStoreInterface::class, MongoTicketEventStore::class);
        // $this->app->bind(TicketReadRepositoryInterface::class, MongoTicketReadRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
