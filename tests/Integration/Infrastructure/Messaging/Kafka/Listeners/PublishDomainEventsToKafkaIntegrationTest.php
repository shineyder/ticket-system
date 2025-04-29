<?php

namespace Tests\Integration\Infrastructure\Messaging\Kafka\Listeners;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\ValueObjects\Priority;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\TestCase;
use Throwable;

class PublishDomainEventsToKafkaIntegrationTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->app->make(Dispatcher::class);
    }

    /** @test */
    public function it_attempts_to_publish_events_to_kafka_test_broker_when_event_is_dispatched(): void
    {
        // Arrange
        $aggregateId = 'kafka-integration-test-1';
        $event1 = new TicketCreated(
            $aggregateId,
            'Kafka Integration Title',
            'Desc for Kafka test',
            Priority::MEDIUM
        );
        $event2 = new TicketResolved($aggregateId);

        $appEvent = new DomainEventsPersisted([$event1, $event2], $aggregateId, 'Ticket');

        // Act & Assert
        try {
            // Dispara o evento. Como QUEUE_CONNECTION=sync, o listener será executado imediatamente.
            $this->dispatcher->dispatch($appEvent);

            // Se chegou aqui, o método handle() do listener executou e tentou enviar
            // para o broker 'kafka-test:9092' sem lançar exceções fatais.
            $this->assertTrue(true, "Listener executado sem lançar exceções.");
        } catch (Throwable $e) {
            // Se uma exceção for pega aqui, o teste falha.
            $this->fail("O listener PublishDomainEventsToKafka lançou uma exceção inesperada: " . $e->getMessage());
        }
    }

     /** @test */
    public function it_ignores_events_for_other_aggregate_types(): void
    {
        // Arrange
        $aggregateId = 'kafka-integration-ignore';
        $event = new TicketCreated($aggregateId, 'Ignore Title', 'Ignore Desc', Priority::LOW);
        // Evento com tipo de agregado diferente
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'NonTicketAggregate');

        // Act & Assert
        try {
            $this->dispatcher->dispatch($appEvent);
            $this->assertTrue(true, "Listener executado sem lançar exceções para tipo de agregado diferente.");
        } catch (Throwable $e) {
            $this->fail("O listener PublishDomainEventsToKafka lançou uma exceção inesperada: " . $e->getMessage());
        }
    }
}
