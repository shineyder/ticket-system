<?php

namespace Tests\Unit\Infrastructure\Messaging\Kafka\Listeners;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\DomainEvent;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Infrastructure\Messaging\Kafka\Listeners\PublishDomainEventsToKafka;
use DateTimeImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\MessageProducer;
use Junges\Kafka\Facades\Kafka;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PublishDomainEventsToKafkaTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const EVENT_KAFKA_PATTERN = '/^processed_event_kafka:';
    private Mockery\MockInterface|CacheManager $mockCacheManager;
    private PublishDomainEventsToKafka $listener;
    private string $testTopic = 'test-ticket-events';
    private string $testBroker = 'test-broker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCacheManager = Mockery::mock(CacheManager::class);

        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $this->listener = new PublishDomainEventsToKafka($this->mockCacheManager);
    }

    /** @test */
    public function it_publishes_persisted_domain_events_to_kafka(): void
    {
        // Arrange
        $aggregateId = 'kafka-test-123';
        $occurredOn = new DateTimeImmutable('2024-01-15T10:00:00+00:00');
        $eventId1 = 'event-id-1';
        $event1 = new TicketCreated(
            $aggregateId,
            'Kafka Title',
            'Kafka Desc',
            1, // Priority::MEDIUM
            $occurredOn,
            $eventId1
        );

        $eventId2 = 'event-id-2';
        $event2 = new TicketResolved($aggregateId, $occurredOn->modify('+1 hour'), $eventId2);

        $appEvent = new DomainEventsPersisted([$event1, $event2], $aggregateId, 'Ticket');

        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.topic', null)
            ->andReturn($this->testTopic);
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.broker', null)
            ->andReturn($this->testBroker);

        // Mock Kafka Facade e ProducerBuilder
        $mockProducerBuilder = Mockery::mock(MessageProducer::class);
        Kafka::shouldReceive('publish')
            ->times(2) // Uma vez para cada evento
            ->with($this->testBroker)
            ->andReturn($mockProducerBuilder);

        // Mock Cache checks for idempotency (assume not processed yet)
        $this->mockCacheManager->shouldReceive('has')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId1.'/'))
            ->once()
            ->andReturnFalse();

        $this->mockCacheManager->shouldReceive('has')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId2.'/'))
            ->once()
            ->andReturnFalse();

        // Expect calls for Event 1 (TicketCreated)
        $mockProducerBuilder->shouldReceive('onTopic')
            ->once()
            ->with($this->testTopic)
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withHeaders')
            ->once()
            ->with(['event_type' => TicketCreated::class])
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withBody')
            ->once()
            ->with(Mockery::on(function ($body) use ($event1) {
                $expectedPayload = json_encode([
                    'title' => $event1->title,
                    'description' => $event1->description,
                    'priority' => $event1->priority,
                    'occurred_on' => $event1->getOccurredOn()->format(\DateTime::ATOM),
                ]);
                return isset($body['payload']) && $body['payload'] === $expectedPayload;
            }))
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withBodyKey')
            ->once()
            ->with('key', $aggregateId) // Verifica a chave da mensagem
            ->andReturnSelf()
            ->ordered();

        // Mock Cache put AFTER send for event 1
        $this->mockCacheManager->shouldReceive('put')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId1.'/'), true, Mockery::any()) // Verifica chave e valor
            ->once()
            ->ordered();

        $mockProducerBuilder->shouldReceive('send')->once();

        // Expect calls for Event 2 (TicketResolved)
        $mockProducerBuilder->shouldReceive('onTopic')
            ->once()
            ->with($this->testTopic)
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withHeaders')
            ->once()
            ->with(['event_type' => TicketResolved::class])
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withBody')
            ->once()
            ->with(Mockery::on(function ($body) use ($event2) {
                $expectedPayload = json_encode([
                    'occurred_on' => $event2->getOccurredOn()->format(\DateTime::ATOM),
                ]);
                return isset($body['payload']) && $body['payload'] === $expectedPayload;
            }))
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withBodyKey')
            ->once()
            ->with('key', $aggregateId) // Verifica a chave da mensagem
            ->andReturnSelf()
            ->ordered();

        // Mock Cache put AFTER send for event 2
        $this->mockCacheManager->shouldReceive('put')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId2.'/'), true, Mockery::any()) // Verifica chave e valor
            ->once()
            ->ordered();

        $mockProducerBuilder->shouldReceive('send')->once();

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit via Mockery expectations)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_publishing_if_event_already_processed_in_cache(): void
    {
        // Arrange
        $aggregateId = 'kafka-skip-456';
        $eventId = 'event-skip-id';

        $event = new TicketCreated($aggregateId, 'Skip Title', 'Skip Desc', 0, null, $eventId);

        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.topic', null)
            ->andReturn($this->testTopic);

        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.broker', null)
            ->andReturn($this->testBroker);

        // Mock Cache check - Event IS already processed
        $this->mockCacheManager->shouldReceive('has')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId.'/'))
            ->once()
            ->andReturnTrue();

        // Assert that Kafka::publish is NEVER called
        Kafka::shouldReceive('publish')->never();

        // Assert that cache->put is NEVER called
        $this->mockCacheManager->shouldReceive('put')->never();

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_error_if_kafka_topic_is_not_configured(): void
    {
        // Arrange
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.topic', null)
            ->andReturnNull();
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.broker', null)
            ->andReturn($this->testBroker);
        Log::shouldReceive('error')
            ->once()
            ->with('Kafka topic alias "ticket-events" não definido ou tópico não configurado.');

        $appEvent = new DomainEventsPersisted([], 'id', 'Ticket');

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_error_if_kafka_broker_is_not_configured(): void
    {
        // Arrange
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.topic', null)
            ->once()
            ->andReturn($this->testTopic);
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.broker', null)
            ->andReturnNull();
        Log::shouldReceive('error')
            ->once()
            ->with('Kafka broker alias "ticket-events" não definido ou tópico não configurado.');

        $appEvent = new DomainEventsPersisted([], 'id', 'Ticket');

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_error_on_kafka_publishing_failure(): void
    {
        // Arrange
        $aggregateId = 'kafka-fail-456';
        $eventId = 'event-fail-id-' . Str::uuid()->toString();
        $event = new TicketCreated(
            $aggregateId,
            'Fail Title',
            'Fail Desc',
            0,
            null,
            $eventId
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');
        $exception = new \Exception('Kafka connection failed');

        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.topic', null)
            ->once()
            ->andReturn($this->testTopic);
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.broker', null)
            ->once()
            ->andReturn($this->testBroker);

        // Espera-se que retorne false para que a publicação seja tentada
        $this->mockCacheManager->shouldReceive('has')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId.'/'))
            ->once()
            ->andReturnFalse();

        $mockProducerBuilder = Mockery::mock(MessageProducer::class);
        Kafka::shouldReceive('publish')
            ->once()
            ->with($this->testBroker)
            ->andReturn($mockProducerBuilder);
        $mockProducerBuilder->shouldReceive('onTopic')->once()->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withHeaders')->once()->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withBody')->once()->andReturnSelf();
        $mockProducerBuilder->shouldReceive('withBodyKey')->once()->andReturnSelf();
        $mockProducerBuilder->shouldReceive('send')->once()->andThrow($exception); // Simula falha

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Erro ao publicar evento no Kafka',
                Mockery::on(function ($context) use ($aggregateId, $exception) {
                    return $context['topic'] === $this->testTopic &&
                           $context['event'] === TicketCreated::class &&
                           $context['aggregateId'] === $aggregateId &&
                           $context['error'] === $exception->getMessage();
                })
            );

        // Garante que cache->put() não seja chamado se a publicação falhar antes
        $this->mockCacheManager->shouldReceive('put')->never();

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_error_and_continues_if_json_encode_fails(): void
    {
        // Arrange: Create a mock event that returns invalid UTF-8 data
        $aggregateId = 'json-fail-789';
        $eventId = 'event-json-fail-' . Str::uuid()->toString();

        $mockEvent = Mockery::mock(DomainEvent::class);
        $mockEvent->shouldReceive('getAggregateId')->andReturn($aggregateId);
        $mockEvent->shouldReceive('getEventId')->andReturn($eventId);
        $mockEvent->shouldReceive('getOccurredOn')->andReturn(new DateTimeImmutable());
        // Payload with invalid UTF-8 sequence
        $mockEvent->shouldReceive('toPayload')->andReturn(['bad_data' => "\xB1\x31"]);

        $appEvent = new DomainEventsPersisted([$mockEvent], $aggregateId, 'Ticket');

        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.topic', null)
            ->once()
            ->andReturn($this->testTopic);
        Config::shouldReceive('get')
            ->with('kafka.topics.ticket-events.broker', null)
            ->once()
            ->andReturn($this->testBroker);

        // Expect cache check to return false (event not processed yet)
        $this->mockCacheManager->shouldReceive('has')
            ->with(Mockery::pattern(self::EVENT_KAFKA_PATTERN.$eventId.'/'))
            ->once()
            ->andReturnFalse();

        // Expect Log::error to be called due to json_encode failure
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Falha ao serializar payload do evento para JSON',
                Mockery::on(function ($context) use ($aggregateId, $mockEvent) {
                    return isset($context['event']) && $context['event'] === get_class($mockEvent) &&
                           isset($context['aggregateId']) && $context['aggregateId'] === $aggregateId;
                })
            );

        // Assert that Kafka::publish is NEVER called because of the 'continue'
        Kafka::shouldReceive('publish')->never();

        // Assert that cache->put is NEVER called because the processing loop continues
        $this->mockCacheManager->shouldReceive('put')->never();

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit via Mockery expectations)
        $this->assertTrue(true);
    }
}
