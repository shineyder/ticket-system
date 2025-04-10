<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Interfaces\Repositories\TicketRepository;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Illuminate\Support\Str;
use MongoDB\Driver\Exception\Exception as MongoDriverException;

class MongoTicketRepository implements TicketRepository {
    private Collection $collection;
    private const COLLECTION_NAME = 'tickets';


    public function __construct(
        private MongoConnection $connection
    ) {
        $this->collection = $this->connection
            ->getDatabase()
            ->selectCollection(self::COLLECTION_NAME);
    }

    /**
     * Generates a new unique identifier for a ticket.
     *
     * @return string
     */
    public function nextIdentity(): string
    {
        // Using UUID v4 as a common standard for unique IDs
        return Str::uuid()->toString();
    }

    /**
     * Saves a ticket in the database.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function save(Ticket $ticket): void {
        // Prepare data for MongoDB, using BSON types where appropriate
        $ticketData = [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'priority' => $ticket->priority->value(),
            'status' => $ticket->status->value(),
        ];

        try {
            // Verifica se documento existe
            $existing = $this->collection->findOne(['id' => $ticket->id]);

            $now = new UTCDateTime(); // Momento atual para timestamps

            if ($existing) {
                // --- Atualização ---
                $ticketData['updated_at'] = $now;

                // Atualiza o documento existente, usando $set
                $this->collection->updateOne(
                    ['id' => $ticket->id],
                    ['$set' => $ticketData]
                );
            } else {
                // --- Inserção ---
                $ticketData['created_at'] = $now;
                $ticketData['updated_at'] = $now;

                // Insere novo documento
                $this->collection->insertOne($ticketData);
            }
        } catch (MongoDriverException $e) {
            // Lança uma exceção mais genérica da camada de persistência
            // Esse erro é um falso positivo, pode ignorar
            throw new PersistenceOperationFailedException(
                "Erro ao salvar ticket com ID {$ticket->id}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Finds a ticket by its unique identifier.
     *
     * @param string $id
     * @return Ticket|null
     */
    public function findById(string $id): ?Ticket
    {
        try {
            $document = $this->collection->findOne(['id' => $id]);

            if (!$document) {
                return null;
            }

            return $this->mapDocumentToTicket($document);
        } catch (MongoDriverException $e) {
            throw new PersistenceOperationFailedException(
                "Erro ao buscar ticket com ID {$id}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Retrieves all tickets.
     *
     * @return Ticket[]
     */
    public function findAll(
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array
    {
        try {
            $sortDirection = $orderDirection === 'desc' ? -1 : 1;
            $cursor = $this->collection->find([], ['sort' => [$orderBy => $sortDirection]]);
            $tickets = [];

            foreach ($cursor as $document) {
                $tickets[] = $this->mapDocumentToTicket($document);
            }

            return $tickets;

        } catch (MongoDriverException $e) {
            throw new PersistenceOperationFailedException(
                "Erro ao buscar todos os tickets: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Helper method to map a MongoDB document (stdClass or array) to a Ticket entity.
     *
     * @param object|array $document The document retrieved from MongoDB.
     * @return Ticket
     */
    private function mapDocumentToTicket(object|array $document): Ticket
    {
        // MongoDB driver might return an object (like BSONDocument) or array
        $data = (array) $document;

        // Create the Status value object from the stored string value
        $status = new Status($data['status']);

        // Create the Priority value object from the stored string value
        $priority = new Priority($data['priority']);

        // Create the Ticket entity
        return new Ticket(
            $data['id'],
            $data['title'],
            $data['description'] ?? '',
            $priority,
            $status
        );
    }
}
