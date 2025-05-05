<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\DTOs\TicketDTO;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use MongoDB\BSON\UTCDateTime;

class MongoTicketReadRepository implements TicketReadRepositoryInterface
{
    private Collection $collection;
    private const COLLECTION_NAME = 'ticket_read_models';

    public function __construct(MongoConnection $connection)
    {
        $this->collection = $connection
            ->getDatabase()
            ->selectCollection(self::COLLECTION_NAME);
    }

    public function save(TicketDTO $ticketDto): void
    {
        $filter = ['ticket_id' => $ticketDto->id];

        $updateData = [
            '$set' => [
                'title' => $ticketDto->title,
                'description' => $ticketDto->description,
                'priority' => $ticketDto->priority,
                'status' => $ticketDto->status,
                'resolved_at' => $ticketDto->resolvedAt ? new UTCDateTime($ticketDto->resolvedAt) : null,
                'last_updated_at' => new UTCDateTime(), // Sempre atualiza o timestamp da projeção
            ],
            '$setOnInsert' => [ // Campos definidos apenas na primeira inserção
                'ticket_id' => $ticketDto->id,
                'created_at' => $ticketDto->createdAt ? new UTCDateTime($ticketDto->createdAt) : new UTCDateTime(),
            ]
        ];

        try {
            $this->collection->updateOne(
                $filter,
                $updateData,
                ['upsert' => true] // Cria o documento se não existir, atualiza se existir
            );
        } catch (MongoDriverException $e) {
            throw new PersistenceOperationFailedException(
                "Erro ao salvar read model do ticket com ID {$ticketDto->id}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function findById(string $ticketId): ?TicketDTO
    {
        try {
            $document = $this->collection->findOne(['ticket_id' => $ticketId]);

            if (!$document) {
                return null;
            }

            return $this->mapDocumentToDTO($document);
        } catch (MongoDriverException $e) {
            throw new PersistenceOperationFailedException(
                "Erro ao buscar read model do ticket com ID {$ticketId}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function findAll(string $orderBy = 'created_at', string $orderDirection = 'desc'): array
    {
        // Validar campos de ordenação para segurança
        $allowedSortFields = ['ticket_id', 'title', 'priority', 'status', 'created_at', 'resolved_at', 'last_updated_at'];
        if (!in_array($orderBy, $allowedSortFields)) {
            $orderBy = 'created_at';
        }
        $sortDirection = strtolower($orderDirection) === 'asc' ? 1 : -1;

        try {
            $cursor = $this->collection->find([], ['sort' => [$orderBy => $sortDirection]]);
            $tickets = [];

            foreach ($cursor as $document) {
                $tickets[] = $this->mapDocumentToDTO($document);
            }

            return $tickets;
        } catch (MongoDriverException $e) {
            throw new PersistenceOperationFailedException(
                "Erro ao buscar todos os read models de tickets: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mapeia um documento MongoDB para um TicketDTO.
     */
    private function mapDocumentToDTO(object|array $document): TicketDTO
    {
        $data = (array) $document;

        // Converter BSON UTCDateTime para DateTimeImmutable ou null
        $createdAt = isset($data['created_at']) && $data['created_at'] instanceof UTCDateTime
            ? $data['created_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            : null;

        $resolvedAt = isset($data['resolved_at']) && $data['resolved_at'] instanceof UTCDateTime
            ? $data['resolved_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            : null;

        return new TicketDTO(
            $data['ticket_id'],
            $data['title'] ?? '',
            $data['description'],
            $data['priority'] ?? 'low',
            $data['status'] ?? 'open',
            $createdAt,
            $resolvedAt
        );
    }
}
