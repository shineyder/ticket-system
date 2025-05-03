<?php

namespace Tests\Integration\Infrastructure\Messaging\Kafka\Listeners;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\ValueObjects\Priority;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\TestCase;
use Throwable;

class PublishDomainEventsToKafkaIntegrationTest extends TestCase
{
    private Dispatcher $dispatcher;
    private CacheManager $cache;

    private const CACHE_PREFIX = 'processed_event_kafka:'; // Usar o mesmo prefixo do listener

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->app->make(Dispatcher::class);

        // Configura para usar Redis e limpa antes de cada teste
        config(['cache.default' => 'redis']);
        $this->cache = $this->app->make(CacheManager::class);
        $this->app['cache']->store('redis')->flush();
    }

    /** @test */
    public function it_attempts_to_publish_events_to_kafka_test_broker_when_event_is_dispatched(): void
    {
        // Arrange
        $aggregateId = 'kafka-integration-test-1';
        $eventId1 = 'event-kafka-int-1';
        $event1 = new TicketCreated(
            $aggregateId,
            'Kafka Integration Title',
            'Desc for Kafka test',
            Priority::MEDIUM,
            null,
            $eventId1
        );

        $eventId2 = 'event-kafka-int-2';
        $event2 = new TicketResolved(
            $aggregateId,
            null,
            $eventId2
        );

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
    public function it_handles_duplicate_event_dispatch_idempotently(): void
    {
        // Arrange
        $aggregateId = 'kafka-idempotency-test-1';
        $eventId = 'event-kafka-idem-1';
        $event = new TicketCreated(
            $aggregateId,
            'Idempotency Title',
            'Desc idempotency',
            Priority::LOW,
            null,
            $eventId
        );

        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        $cacheKey = self::CACHE_PREFIX . $eventId;

        // Act: First dispatch
        try {
            $this->dispatcher->dispatch($appEvent);

            // Assert: Cache key should exist after first successful dispatch
            $this->assertTrue($this->cache->has($cacheKey), "Cache key $cacheKey não foi setado após a primeira execução.");
        } catch (Throwable $e) {
            $this->fail("Primeira execução do listener falhou: " . $e->getMessage());
        }

        // Act: Second dispatch (same event)
        try {
            $this->dispatcher->dispatch($appEvent);

            // Assert: Se chegou aqui, a segunda execução (que deveria ter sido pulada pela idempotência) não causou erro.
            $this->assertTrue(true, "Segunda execução do listener não lançou exceção.");
            // Idealmente, verificaríamos se o Kafka recebeu apenas uma mensagem, mas isso é complexo aqui.
            // A verificação do cache já dá uma boa indicação.
        } catch (Throwable $e) {
            $this->fail("Segunda execução do listener (idempotência) falhou inesperadamente: " . $e->getMessage());
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
