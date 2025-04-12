<?php

namespace App\Providers;

use App\Application\Events\DomainEventsPersisted;
use App\Infrastructure\Messaging\Kafka\Listeners\PublishDomainEventsToKafka;
use App\Infrastructure\Persistence\MongoDB\Projections\UpdateTicketReadModelProjection;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        DomainEventsPersisted::class => [
            PublishDomainEventsToKafka::class,
            UpdateTicketReadModelProjection::class
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
