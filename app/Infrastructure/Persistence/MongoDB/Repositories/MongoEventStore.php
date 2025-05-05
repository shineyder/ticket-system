<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Events\DomainEvent;
use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use App\Infrastructure\Persistence\Exceptions\EventClassNotFoundException;
use App\Infrastructure\Persistence\Exceptions\EventInstantiateFailedException;
use App\Infrastructure\Persistence\Exceptions\EventLoadFailedException;
use App\Infrastructure\Persistence\Exceptions\EventPersistenceFailedException;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoDBDriverException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
use DateTimeImmutable;
use Exception;
use ReflectionParameter;
use Throwable;

/**
 * Implementação do Event Store para o agregado Ticket usando MongoDB.
 * Armazena e recupera a sequência de eventos de domínio.
 */
class MongoEventStore implements TicketEventStoreInterface
{
    private Collection $collection; // Armazena a coleção MongoDB
    private const COLLECTION_NAME = 'ticket_events'; // Nome da coleção para os eventos

    /**
     * Construtor que injeta a classe MongoConnection personalizada.
     *
     * @param MongoConnection $connection Classe de conexão singleton.
     * @param Collection|null $collectionOverride Permite injetar uma coleção (para testes).
     */
    public function __construct(
        private MongoConnection $connection,
        private ?Collection $collectionOverride = null // Adicionado para testes
    ) {
        // Obtém a coleção 'ticket_events' usando a conexão injetada
        $this->collection = $this->collectionOverride ?? $this->connection
            ->getDatabase()
            ->selectCollection(self::COLLECTION_NAME);
    }

    /**
     * Persiste os eventos não commitados de um agregado Ticket no MongoDB.
     * Utiliza transações para garantir atomicidade ao salvar múltiplos eventos.
     *
     * @param Ticket $ticket O agregado Ticket contendo novos eventos.
     * @return DomainEvent[] Retorna os eventos que foram efetivamente salvos.
     * @throws EventPersistenceFailedException Em caso de falha na persistência ou na transação.
     * @infection-ignore-mutator CatchRemoval
     */
    public function save(Ticket $ticket): array
    {
        $aggregateId = $ticket->getId();
        $events = $ticket->pullUncommittedEvents(); // Pega e limpa os eventos não commitados do agregado

        if (empty($events)) {
            return []; // Nada a fazer se não houver novos eventos
        }

        $documents = [];

        // Obtém o último número de sequência para este agregado para continuar a contagem
        $currentSequence = $this->getLastSequenceNumber($aggregateId);

        foreach ($events as $event) {
            $currentSequence++; // Incrementa a sequência para cada novo evento
            // Prepara o documento BSON para o evento
            $documents[] = $this->prepareEventDocument($event, $aggregateId, $currentSequence);
        }

        // Tenta salvar os eventos dentro de uma transação MongoDB para garantir atomicidade
        $session = $this->connection->getClient()->startSession();
        try {
            $session->startTransaction();
            // Insere múltiplos documentos de evento na coleção
            $this->collection->insertMany($documents, ['session' => $session]);
            $session->commitTransaction(); // Confirma a transação se tudo correu bem

            // Retorna os eventos que foram salvos com sucesso
            return $events;
        } catch (MongoDBDriverException | Throwable $e) { // Captura exceções do driver ou outras
            // Se algo der errado, aborta a transação
            if ($session->isInTransaction()) {
                $session->abortTransaction();
            }
            // Relança a exceção encapsulada para a camada superior
            throw new EventPersistenceFailedException($aggregateId, $e);
        } finally {
            $session->endSession(); // Sempre termina a sessão, independentemente do resultado
        }
    }

    /**
     * Carrega um agregado Ticket a partir de seu histórico de eventos no MongoDB.
     *
     * @param string $aggregateId
     * @return Ticket O agregado Ticket reconstituído.
     * @throws AggregateNotFoundException Se nenhum evento for encontrado para o ID fornecido.
     * @throws \Exception Em caso de outras falhas ao carregar ou reconstituir.
     * @infection-ignore-mutator CatchRemoval
     */
    public function load(string $aggregateId): Ticket
    {
        try {
            // Busca todos os documentos de evento para o agregado, ordenados pela sequência
            $cursor = $this->collection->find(
                ['aggregate_id' => $aggregateId],
                ['sort' => ['sequence_number' => 1]] // Ordenação para aplicar eventos na ordem correta!
            );

            $eventsData = $cursor->toArray(); // Converte para um array de documentos

            // Se nenhum evento for encontrado, o agregado não existe
            if (empty($eventsData)) {
                throw new AggregateNotFoundException($aggregateId, 'Ticket');
            }

            $history = [];
            // Reconstrói cada objeto de evento a partir dos dados do documento
            foreach ($eventsData as $eventData) {
                $history[] = $this->reconstituteEvent($eventData);
            }

            // Usa o método estático do agregado para reconstruir seu estado a partir do histórico de eventos
            return Ticket::reconstituteFromHistory($aggregateId, $history);

        } catch (MongoDBDriverException | AggregateNotFoundException | EventClassNotFoundException | EventInstantiateFailedException $e) {
            // Relança exceções específicas ou do driver
            throw $e;
        } catch (Throwable $e) {
            // Captura qualquer outro erro durante o carregamento/reconstituição
            throw new EventLoadFailedException($aggregateId, $e->getMessage(), $e);
        }
    }

    /**
     * Busca o último número de sequência para um agregado específico na coleção de eventos.
     *
     * @param string $aggregateId
     * @return int O último número de sequência ou 0 se nenhum evento existir.
     */
    private function getLastSequenceNumber(string $aggregateId): int
    {
        $lastEvent = $this->collection->findOne(
            ['aggregate_id' => $aggregateId],
            [
                'sort' => ['sequence_number' => -1], // Ordena decrescente para pegar o maior
                'projection' => ['sequence_number' => 1] // Pega apenas o campo sequence_number
            ]
        );

        // Retorna o número da sequência ou 0 se for o primeiro evento
        return $lastEvent['sequence_number'] ?? 0;
    }

    /**
     * Prepara um documento BSON para ser inserido no MongoDB a partir de um DomainEvent.
     *
     * @param DomainEvent $event O evento de domínio.
     * @param string $aggregateId
     * @param int $sequenceNumber O número de sequência deste evento.
     * @return array O documento BSON (como array PHP) pronto para inserção.
     */
    private function prepareEventDocument(DomainEvent $event, string $aggregateId, int $sequenceNumber): array
    {
        $payloadData = $event->toPayload();

        return [
            '_id' => new ObjectId(), // Gera um ObjectId único para o documento do evento
            'aggregate_id' => $aggregateId,
            'aggregate_type' => 'Ticket', // Tipo do agregado
            'event_type' => get_class($event), // Classe do evento para reconstituição
            'event_id' => $event->getEventId(),
            'payload' => json_encode($payloadData), // Serializa o payload como JSON
            'sequence_number' => $sequenceNumber, // Número de ordem do evento para este agregado
            'occurred_on' => new UTCDateTime($event->getOccurredOn()), // Converte para BSON UTCDateTime
            'version' => 1, // Versão do schema do evento
        ];
    }

    /**
     * Reconstrói um objeto DomainEvent a partir dos dados recuperados do MongoDB.
     *
     * @param object|array $eventData Dados do evento do MongoDB (geralmente um objeto BSONDocument ou array).
     * @return DomainEvent O objeto de evento reconstituído.
     * @throws \Exception Se a classe do evento não for encontrada ou não puder ser instanciada.
     * @infection-ignore-mutator CatchRemoval
     */
    private function reconstituteEvent(object|array $eventData): DomainEvent
    {
        $data = (array) $eventData; // Garante que é um array para acesso fácil
        $eventType = $data['event_type'];
        $eventId = $data['event_id'] ?? null;
        $aggregateId = $data['aggregate_id'];

        if (!class_exists($eventType)) {
            throw new EventClassNotFoundException($eventType);
        }

        try{
            $payload = $this->decodePayload($data);

            // Converte para DateTimeImmutable aqui para garantir o tipo correto
            $occurredOn = $this->convertOccurredOnToImmutable($data);

            return $this->instantiateEvent($eventType, $aggregateId, $payload, $occurredOn, $eventId);
        } catch (\ReflectionException | \TypeError | Exception $e) {
            throw new EventInstantiateFailedException($eventType, $e);
        }
    }

    private function decodePayload(array $data): array
    {
        return json_decode($data['payload'], true);
    }

    private function convertOccurredOnToImmutable(array $data): DateTimeImmutable
    {
        $occurredOn = $data['occurred_on'];
        if ($occurredOn instanceof UTCDateTime) {
            $dateTime = $occurredOn->toDateTime()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get())); // Timezone local
            return DateTimeImmutable::createFromMutable($dateTime);
        }
        return new DateTimeImmutable('@' . $occurredOn); // Se não for UTCDateTime (improvável)
    }

    /**
     * Instancia um objeto DomainEvent usando seu construtor e os dados fornecidos.
     *
     * @param string $eventType Classe do evento.
     * @param string $aggregateId O ID do agregado.
     * @param array $payload Dados do payload.
     * @param DateTimeImmutable $occurredOn Momento da ocorrência.
     * @param string|null $eventId O ID original do evento (pode ser null se não salvo).
     * @return DomainEvent
     * @throws ReflectionException | TypeError | Exception
     */
    private function instantiateEvent(
        string $eventType,
        string $aggregateId,
        array $payload,
        DateTimeImmutable $occurredOn,
        ?string $eventId = null
    ): DomainEvent
    {
        $reflectionClass = new \ReflectionClass($eventType);
        $constructor = $reflectionClass->getConstructor();
        $args = [];

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                // Delega a lógica de resolução do valor para o método auxiliar
                $args[$param->getName()] = $this->resolveConstructorArgument(
                    $param,
                    $aggregateId,
                    $payload,
                    $occurredOn,
                    $eventId
                );
            }
        }

        // Instancia o evento passando todos os argumentos resolvidos
        return $reflectionClass->newInstanceArgs(array_values($args));
    }

    /**
     * Resolve o valor para um único parâmetro do construtor do evento.
     * Encapsula a lógica de verificar payload, VOs, occurredOn e valores padrão.
     *
     * @param ReflectionParameter $param O parâmetro do construtor sendo resolvido.
     * @param string $aggregateId O ID do agregado.
     * @param array $payload O payload decodificado do evento.
     * @param DateTimeImmutable $occurredOn O timestamp do evento.
     * @param string|null $eventId O ID original do evento recuperado.
     * @return mixed O valor resolvido para o argumento.
     * @throws \InvalidArgumentException Se um parâmetro obrigatório não puder ser resolvido.
     */
    private function resolveConstructorArgument(
        ReflectionParameter $param,
        string $aggregateId,
        array $payload,
        DateTimeImmutable $occurredOn,
        ?string $eventId = null
    ): mixed {
        $paramName = $param->getName();
        $resolvedValue = null;

        // --- Lógica específica para parâmetros especiais ---
        if ($paramName === 'id') {
            $resolvedValue = $aggregateId;
        } elseif ($paramName === 'occurredOn') {
            $resolvedValue = $occurredOn;
        } elseif ($paramName === 'eventId') {
            $resolvedValue = $eventId;
        } else {
            // --- Lógica para parâmetros do payload ---
            // Verificar se o parâmetro existe no payload
            if (array_key_exists($paramName, $payload)) {
                $valueFromPayload = $payload[$paramName];
                $resolvedValue = $valueFromPayload;
            }
            // Se a chave não existe no payload, $resolvedValue permanece null
        }

        return $resolvedValue;
    }
}
