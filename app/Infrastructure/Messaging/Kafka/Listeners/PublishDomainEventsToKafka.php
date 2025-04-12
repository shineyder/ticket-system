<?php

namespace App\Infrastructure\Messaging\Kafka\Listeners;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\DomainEvent;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Support\Facades\Log;
use Throwable;
use ReflectionClass;
use ReflectionProperty;

/**
 * Listener que ouve por DomainEventsPersisted e publica os eventos no Kafka.
 */
class PublishDomainEventsToKafka
{
    /**
     * Manipula o evento DomainEventsPersisted.
     *
     * @param DomainEventsPersisted $event
     * @return void
     */
    public function handle(DomainEventsPersisted $event): void
    {
        // Obtém o nome do tópico do arquivo de configuração (usando o alias 'ticket-events')
        $topic = config('kafka.topics.ticket-events.topic'); // Acessa o tópico pelo alias

        if (!$topic) {
            Log::error('Kafka topic alias "ticket-events" não definido ou tópico não configurado.');
            return;
        }

        foreach ($event->domainEvents as $domainEvent) {
            try {
                // Prepara o payload serializando as propriedades do evento
                $payloadArray = $this->serializeEventPayload($domainEvent);
                $payloadJson = json_encode($payloadArray);

                if ($payloadJson === false) {
                    Log::error(
                        'Falha ao serializar payload do evento para JSON',
                        [
                            'event' => get_class($domainEvent),
                            'aggregateId' => $domainEvent->getAggregateId()
                        ]
                    );
                    continue; // Pula para o próximo evento
                }

                // Prepara o corpo da mensagem Kafka
                $messageBody = ['payload' => $payloadJson]; // Encapsula o JSON dentro de 'payload'

                // Prepara headers (ex: tipo do evento)
                $headers = ['event_type' => get_class($domainEvent)];

                // Usa o ID do agregado como chave da mensagem Kafka (bom para particionamento)
                $messageKey = $domainEvent->getAggregateId();

                // Publica a mensagem no Kafka
                Kafka::publishOn($topic)
                    ->withKey($messageKey)
                    ->withHeaders($headers)
                    ->withBody($messageBody)
                    ->send();

                Log::debug(
                    'Evento publicado no Kafka',
                    [
                        'topic' => $topic,
                        'event' => get_class($domainEvent),
                        'aggregateId' => $messageKey
                    ]
                );

            } catch (Throwable $e) {
                // Loga qualquer erro durante a publicação no Kafka
                Log::error(
                    'Erro ao publicar evento no Kafka',
                    [
                        'topic' => $topic,
                        'event' => get_class($domainEvent),
                        'aggregateId' => $domainEvent->getAggregateId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString() // Remover para produção, pode ser verboso
                    ]
                );
                // Considerar: Lançar exceção? Colocar em fila de retentativa? Depende da criticidade.
            }
        }
    }

    /**
     * Serializa o payload de um DomainEvent para um array.
     *
     * @param DomainEvent $event
     * @return array
     */
    private function serializeEventPayload(DomainEvent $event): array
    {
        $payload = [];
        $reflection = new ReflectionClass($event);

        // Propriedades públicas (incluindo readonly do PHP 8+)
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propName = $property->getName();
            // Ignora a propriedade 'occurredOn' se ela for gerenciada separadamente
            if ($propName === 'occurredOn' && $reflection->hasMethod('getOccurredOn')) {
                continue;
            }
            if ($property->isInitialized($event)) {
                $value = $property->getValue($event);
                // Se for um Value Object com método value(), pega o valor primitivo
                if (is_object($value) && method_exists($value, 'value')) {
                    $payload[$propName] = $value->value();
                } else {
                    $payload[$propName] = $value;
                }
            }
        }
        // Adiciona occurredOn explicitamente no formato ISO8601
        $payload['occurred_on'] = $event->getOccurredOn()->format(DateTimeImmutable::ISO8601);

        return $payload;
    }
}
