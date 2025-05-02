<?php

namespace Tests\Integration\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\DTOs\TicketDTO;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoTicketReadRepository;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;
use DateTimeImmutable;

class MongoTicketReadRepositoryIntegrationTest extends TestCase
{
    private const DATEFORMAT = 'Y-m-d\TH:i:s.v';
    use DatabaseMigrations; // Garante DB limpo e migrations rodadas a cada teste

    private MongoTicketReadRepository $readRepository;
    private string $collectionName = 'ticket_read_models'; // Nome da coleção
    private MongoConnection $mongoConnection;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolve a implementação real do container do Laravel
        $this->readRepository = $this->app->make(MongoTicketReadRepository::class);

        $this->mongoConnection = $this->app->make(MongoConnection::class);

        $this->assertInstanceOf(MongoTicketReadRepository::class, $this->readRepository);
    }

    /**
     * Helper para buscar um documento diretamente no DB.
     */
    private function findReadModelInDb(string $ticketId): ?array
    {
        $document = $this->mongoConnection->getDatabase()
            ->selectCollection($this->collectionName)
            ->findOne(['ticket_id' => $ticketId]);

        return $document ? (array) $document : null;
    }

    /** @test */
    public function it_can_save_a_new_ticket_dto_via_upsert(): void
    {
        // Arrange
        $ticketId = 'read-repo-save-1';
        $now = new DateTimeImmutable();
        $dto = new TicketDTO(
            id: $ticketId,
            title: 'Read Repo Save Test',
            description: 'Saving a new DTO.',
            priority: 'high',
            status: 'open',
            createdAt: $now,
            resolvedAt: null
        );

        // Act
        $this->readRepository->save($dto);

        // Assert: Verifica diretamente no banco
        $dbData = $this->findReadModelInDb($ticketId);

        $this->assertNotNull($dbData);
        $this->assertSame($ticketId, $dbData['ticket_id']);
        $this->assertSame('Read Repo Save Test', $dbData['title']);
        $this->assertSame('Saving a new DTO.', $dbData['description']);
        $this->assertSame('high', $dbData['priority']);
        $this->assertSame('open', $dbData['status']);
        $this->assertInstanceOf(UTCDateTime::class, $dbData['created_at']);
        $this->assertEquals(
            $now->format(self::DATEFORMAT),
            $dbData['created_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );
        $this->assertNull($dbData['resolved_at']);
        $this->assertInstanceOf(UTCDateTime::class, $dbData['last_updated_at']);
    }

    /** @test */
    public function it_can_update_an_existing_ticket_dto_via_upsert(): void
    {
        // Arrange: Save initial DTO
        $ticketId = 'read-repo-update-1';
        $initialTime = new DateTimeImmutable('-1 hour');
        $initialDto = new TicketDTO($ticketId, 'Initial Title', 'Initial Desc', 'low', 'open', $initialTime);
        $this->readRepository->save($initialDto);
        $dbDataInitial = $this->findReadModelInDb($ticketId); // Pega o estado inicial do DB

        // Arrange: Create updated DTO
        sleep(1); // Garante timestamp diferente para last_updated_at
        $updateTime = new DateTimeImmutable();
        $resolvedTime = new DateTimeImmutable('+5 minutes');
        $updatedDto = new TicketDTO(
            id: $ticketId,
            title: 'Updated Title', // Changed
            description: 'Updated Desc', // Changed
            priority: 'medium', // Changed
            status: 'resolved', // Changed
            createdAt: $updateTime, // Este NÃO deve ser usado na atualização
            resolvedAt: $resolvedTime // Set
        );

        // Act
        $this->readRepository->save($updatedDto);

        // Assert: Verifica diretamente no banco
        $dbDataUpdated = $this->findReadModelInDb($ticketId);

        $this->assertNotNull($dbDataUpdated);
        $this->assertSame($ticketId, $dbDataUpdated['ticket_id']);
        $this->assertSame('Updated Title', $dbDataUpdated['title']);
        $this->assertSame('Updated Desc', $dbDataUpdated['description']);
        $this->assertSame('medium', $dbDataUpdated['priority']);
        $this->assertSame('resolved', $dbDataUpdated['status']);

        // Verifica se createdAt NÃO foi alterado ($setOnInsert funcionou)
        $this->assertInstanceOf(UTCDateTime::class, $dbDataUpdated['created_at']);
        $this->assertEquals(
            $initialTime->format(self::DATEFORMAT), // Deve ser igual ao tempo inicial
            $dbDataUpdated['created_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );

        // Verifica resolvedAt
        $this->assertInstanceOf(UTCDateTime::class, $dbDataUpdated['resolved_at']);
        $this->assertEquals(
            $resolvedTime->format(self::DATEFORMAT),
            $dbDataUpdated['resolved_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );

        // Verifica se last_updated_at foi atualizado
        $this->assertInstanceOf(UTCDateTime::class, $dbDataUpdated['last_updated_at']);
        $this->assertNotEquals($dbDataInitial['last_updated_at'], $dbDataUpdated['last_updated_at']);
    }

    /** @test */
    public function it_can_find_a_ticket_dto_by_id(): void
    {
        // Arrange
        $ticketId = 'read-repo-find-1';
        $now = new DateTimeImmutable();
        $dtoToSave = new TicketDTO($ticketId, 'Find Me', 'Desc find', 'low', 'open', $now);
        $this->readRepository->save($dtoToSave);

        // Act
        $foundDto = $this->readRepository->findById($ticketId);

        // Assert
        $this->assertInstanceOf(TicketDTO::class, $foundDto);
        $this->assertSame($ticketId, $foundDto->id);
        $this->assertSame('Find Me', $foundDto->title);
        $this->assertSame('Desc find', $foundDto->description);
        $this->assertSame('low', $foundDto->priority);
        $this->assertSame('open', $foundDto->status);
        $this->assertEquals($now->format(self::DATEFORMAT), $foundDto->createdAt->format(self::DATEFORMAT));
        $this->assertNull($foundDto->resolvedAt);
    }

    /** @test */
    public function find_by_id_returns_null_if_not_found(): void
    {
        // Act
        $foundDto = $this->readRepository->findById('non-existent-read-id');

        // Assert
        $this->assertNull($foundDto);
    }

    /** @test */
    public function it_can_find_all_ticket_dtos_with_default_sort_desc_created_at(): void
    {
        // Arrange
        $time1 = new DateTimeImmutable('-2 days');
        $time2 = new DateTimeImmutable('-1 day');
        $time3 = new DateTimeImmutable();

        $dto1 = new TicketDTO('id1', 'Ticket 1', '', 'low', 'open', $time1);
        $dto3 = new TicketDTO('id3', 'Ticket 3', '', 'high', 'open', $time3); // Mais recente
        $dto2 = new TicketDTO('id2', 'Ticket 2', '', 'medium', 'open', $time2);

        $this->readRepository->save($dto1);
        $this->readRepository->save($dto3); // Salva fora de ordem
        $this->readRepository->save($dto2);

        // Act
        $results = $this->readRepository->findAll(); // Default sort

        // Assert
        $this->assertCount(3, $results);
        $this->assertSame('id3', $results[0]->id); // Mais recente primeiro
        $this->assertSame('id2', $results[1]->id);
        $this->assertSame('id1', $results[2]->id); // Mais antigo por último
        $this->assertInstanceOf(TicketDTO::class, $results[0]);
    }

    /** @test */
    public function it_can_find_all_ticket_dtos_with_custom_sort_asc_title(): void
    {
        // Arrange
        $dtoC = new TicketDTO('idC', 'Charlie', '', 'low', 'open');
        $dtoA = new TicketDTO('idA', 'Alpha', '', 'high', 'open');
        $dtoB = new TicketDTO('idB', 'Bravo', '', 'medium', 'open');

        $this->readRepository->save($dtoC);
        $this->readRepository->save($dtoA);
        $this->readRepository->save($dtoB);

        // Act
        $results = $this->readRepository->findAll('title', 'asc');

        // Assert
        $this->assertCount(3, $results);
        $this->assertSame('idA', $results[0]->id); // Alpha primeiro
        $this->assertSame('idB', $results[1]->id); // Bravo
        $this->assertSame('idC', $results[2]->id); // Charlie por último
    }
}
