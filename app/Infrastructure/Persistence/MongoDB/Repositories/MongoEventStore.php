<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Events\DomainEvent;
use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoDBDriverException;
use MongoDB\BSON\UTCDateTime;
use DateTimeImmutable;
use Exception;
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
     */
    public function __construct(
        private MongoConnection $connection
    ) {
        // Obtém a coleção 'ticket_events' usando a conexão injetada
        $this->collection = $this->connection
            ->getDatabase()
            ->selectCollection(self::COLLECTION_NAME);
    }

    /**
     * Persiste os eventos não commitados de um agregado Ticket no MongoDB.
     * Utiliza transações para garantir atomicidade ao salvar múltiplos eventos.
     *
     * @param Ticket $ticket O agregado Ticket contendo novos eventos.
     * @return DomainEvent[] Retorna os eventos que foram efetivamente salvos.
     * @throws \Exception Em caso de falha na persistência ou na transação.
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
            throw new Exception("Falha ao salvar eventos para o agregado {$aggregateId}: " . $e->getMessage(), 0, $e);
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

        } catch (MongoDBDriverException | AggregateNotFoundException $e) {
            // Relança exceções específicas ou do driver
            throw $e;
        } catch (Throwable $e) {
            // Captura qualquer outro erro durante o carregamento/reconstituição
            throw new Exception("Falha ao carregar agregado {$aggregateId}: " . $e->getMessage(), 0, $e);
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
        // Serializa as propriedades públicas do evento para o payload.
        $payload = [];
        $reflection = new \ReflectionClass($event);
        // Itera sobre propriedades públicas readonly
        foreach ($reflection->getProperties(\ReflectionProperty::IS_READONLY | \ReflectionProperty::IS_PUBLIC) as $property) {
            $payload[$property->getName()] = $property->getValue($event);
        }
        // Adiciona propriedades públicas não-readonly se houver (menos comum em eventos imutáveis)
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC & ~\ReflectionProperty::IS_READONLY) as $property) {
            if ($property->isInitialized($event)) { // Evita erro se não inicializada
                $payload[$property->getName()] = $property->getValue($event);
            }
        }

        return [
            '_id' => new \MongoDB\BSON\ObjectId(), // Gera um ObjectId único para o documento do evento
            'aggregate_id' => $aggregateId,
            'aggregate_type' => 'Ticket', // Tipo do agregado
            'event_type' => get_class($event), // Classe do evento para reconstituição
            'payload' => json_encode($payload), // Serializa o payload como JSON
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
     */
    private function reconstituteEvent(object|array $eventData): DomainEvent
    {
        $data = (array) $eventData; // Garante que é um array para acesso fácil
        $eventType = $data['event_type'];

        if (!class_exists($eventType)) {
            throw new Exception("Classe de evento '{$eventType}' não encontrada durante a reconstituição.");
        }

        $payload = json_decode($data['payload'], true);
        // Converte BSON UTCDateTime de volta para DateTimeImmutable
        $occurredOn = $data['occurred_on'] instanceof UTCDateTime
            ? $data['occurred_on']->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get())) // Timezone local
            : new DateTimeImmutable('@' . $data['occurred_on']); // Fallback se não for UTCDateTime (improvável)

        // Tenta instanciar o evento usando Reflection para mapear o payload aos parâmetros do construtor.
        // Assume que os nomes no payload correspondem aos nomes dos parâmetros.
        try {
            $reflectionClass = new \ReflectionClass($eventType);
            $constructor = $reflectionClass->getConstructor();
            $args = [];

            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    $paramName = $param->getName();

                    // Verifica se o parâmetro existe no payload JSON
                    if (array_key_exists($paramName, $payload)) {
                        $valueFromPayload = $payload[$paramName]; // Valor do payload
                        $paramType = $param->getType(); // Obtém o tipo esperado pelo construtor

                        // --- Tratamento para Value Objects ---
                        if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                            $typeName = $paramType->getName();

                            // Se o tipo esperado for Status, cria uma instância de Status
                            if ($typeName === Status::class) {
                                // Assume que o payload contém o valor string do status
                                $args[$paramName] = new Status($valueFromPayload);
                                continue; // Pula para o próximo parâmetro
                            }

                            // Se o tipo esperado for Priority, cria uma instância de Priority
                            if ($typeName === Priority::class) {
                                // Assume que o payload contém o valor int da prioridade
                                // Garante que seja int, mesmo que o JSON o tenha como string
                                $args[$paramName] = new Priority((int)$valueFromPayload);
                                continue; // Pula para o próximo parâmetro
                            }
                        }
                        // --- Fim do Tratamento para Value Objects ---

                        // Se não for um VO conhecido, usa o valor diretamente do payload
                        $args[$paramName] = $valueFromPayload;

                    } elseif ($param->isDefaultValueAvailable()) {
                        // Se não está no payload mas tem valor padrão, usa o padrão
                        $args[$paramName] = $param->getDefaultValue();
                    }
                }
            }

            $event = $reflectionClass->newInstanceArgs($args);

            // Tenta definir occurredOn via Reflection se for uma propriedade privada/protegida
            // e não foi definida pelo construtor.
            if ($reflectionClass->hasProperty('occurredOn')) {
                $prop = $reflectionClass->getProperty('occurredOn');
                if ($prop->isReadOnly() && !$prop->isInitialized($event)) {
                    // Se for readonly e não inicializada, precisamos usar reflection para "enganar"
                    \Closure::bind(function ($event, $occurredOn) {
                        $event->occurredOn = $occurredOn;
                    }, null, $eventType)->__invoke($event, $occurredOn);
                } elseif (!$prop->isPublic() && !$prop->isReadOnly()) {
                    $prop->setAccessible(true);
                    $prop->setValue($event, $occurredOn);
                    $prop->setAccessible(false);
                }
            }

            return $event;
        } catch (\ReflectionException | \TypeError | Exception $e) {
            throw new Exception("Falha ao instanciar evento '{$eventType}': " . $e->getMessage(), 0, $e);
        }
    }
}
