<?php

namespace Tests\Unit\Infrastructure\Messaging\Kafka\Listeners;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Infrastructure\Messaging\Kafka\Listeners\PublishDomainEventsToKafka;
use DateTimeImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\MessageProducer;
use Junges\Kafka\Facades\Kafka;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PublishDomainEventsToKafkaTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PublishDomainEventsToKafka $listener;
    private string $testTopic = 'test-ticket-events';
    private string $testBroker = 'test-broker';

    protected function setUp(): void
    {
        parent::setUp(); // Importante para inicializar o ambiente Laravel/Facades

        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $this->listener = $this->app->make(PublishDomainEventsToKafka::class); // Resolve via container
    }

    /** @test */
    public function it_publishes_persisted_domain_events_to_kafka(): void
    {
        // Arrange
        $aggregateId = 'kafka-test-123';
        $occurredOn = new DateTimeImmutable('2024-01-15T10:00:00+00:00');
        $event1 = new TicketCreated(
            $aggregateId,
            'Kafka Title',
            'Kafka Desc',
            1, // Priority::MEDIUM
            $occurredOn
        );
        $event2 = new TicketResolved($aggregateId, $occurredOn->modify('+1 hour'));

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
            ->andReturnSelf();
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
            ->andReturnSelf();
        $mockProducerBuilder->shouldReceive('send')->once();

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit via Mockery expectations)
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
    }

    /** @test */
    public function it_logs_error_on_kafka_publishing_failure(): void
    {
        // Arrange
        $aggregateId = 'kafka-fail-456';
        $event = new TicketCreated($aggregateId, 'Fail Title', 'Fail Desc', 0);
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

        // Act
        $this->listener->handle($appEvent);

        // Assert (implicit)
    }
}
