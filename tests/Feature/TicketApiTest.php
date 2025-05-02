<?php

namespace Tests\Feature;

use App\Application\DTOs\TicketDTO;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Domain\ValueObjects\Status;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use DateTimeImmutable;

class TicketApiTest extends TestCase
{
    private const BASEURL = '/api/ticket';

    use DatabaseMigrations; // Garante DB limpo (tickets_test) e migrations rodadas

    // Helper para criar um ticket via API e retornar o ID
    private function createTicketViaApi(array $payload): ?string
    {
        $response = $this->postJson(self::BASEURL, $payload);
        return $response->json('ticket_id');
    }

    // Helper para buscar o DTO diretamente do Read Model (para asserções)
    private function getTicketDtoFromReadModel(string $ticketId): ?TicketDTO
    {
        // Resolve a implementação real do repositório de leitura
        $repo = $this->app->make(TicketReadRepositoryInterface::class);
        return $repo->findById($ticketId);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // --- Configuração para usar Redis neste teste ---
        config(['cache.default' => 'redis']);
        $this->app['cache']->store('redis')->flush();
    }

    // ========================================
    // Testes para POST /api/ticket (Criação)
    // ========================================

    /** @test */
    public function it_can_create_ticket_with_valid_data(): void
    {
        $payload = [
            'title' => 'Feature Test Ticket',
            'description' => 'Valid description.',
            'priority' => 'medium',
        ];

        $response = $this->postJson(self::BASEURL, $payload);

        $response
            ->assertStatus(201)
            ->assertJsonStructure(['message', 'ticket_id'])
            ->assertJson(['message' => 'Ticket criado!']);

        $ticketId = $response->json('ticket_id');
        $this->assertNotNull($ticketId);

        $dto = $this->getTicketDtoFromReadModel($ticketId);
        $this->assertNotNull($dto);
        $this->assertSame('Feature Test Ticket', $dto->title);
        $this->assertSame('medium', $dto->priority);
        $this->assertSame(Status::OPEN, $dto->status);
    }

    /** @test */
    public function it_uses_default_priority_low_if_not_provided(): void
    {
        $payload = [
            'title' => 'Default Priority Test',
            'description' => 'No priority sent.',
            // 'priority' => // Omitido
        ];

        $response = $this->postJson(self::BASEURL, $payload);

        $response->assertStatus(201);
        $ticketId = $response->json('ticket_id');

        // Verifica o read model para a prioridade padrão
        $dto = $this->getTicketDtoFromReadModel($ticketId);
        $this->assertNotNull($dto);
        $this->assertSame('low', $dto->priority);
    }

    /** @test */
    public function create_ticket_fails_with_missing_title(): void
    {
        $payload = [
            // 'title' => // Omitido
            'description' => 'Missing title.',
            'priority' => 'high',
        ];

        $this->postJson(self::BASEURL, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    /** @test */
    public function create_ticket_fails_with_title_too_long(): void
    {
        $payload = [
            'title' => str_repeat('a', 51), // 51 caracteres
            'description' => 'Long title.',
            'priority' => 'low',
        ];

        $this->postJson(self::BASEURL, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

     /** @test */
    public function create_ticket_fails_with_description_too_long(): void
    {
        $payload = [
            'title' => 'Valid Title',
            'description' => str_repeat('b', 256), // 256 caracteres
            'priority' => 'low',
        ];

        $this->postJson(self::BASEURL, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    /** @test */
    public function create_ticket_fails_with_invalid_priority(): void
    {
        $payload = [
            'title' => 'Invalid Priority',
            'description' => 'Sending wrong priority.',
            'priority' => 'urgent', // Valor inválido
        ];

        $this->postJson(self::BASEURL, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('priority');
    }

    // ========================================
    // Testes para PUT /api/ticket/{id} (Resolução)
    // ========================================

    /** @test */
    public function it_can_resolve_an_existing_open_ticket(): void
    {
        // Arrange: Cria um ticket aberto
        $ticketId = $this->createTicketViaApi([
            'title' => 'To Be Resolved',
            'description' => 'Resolve me.',
            'priority' => 'high',
        ]);
        $this->assertNotNull($ticketId);

        // Garante que o estado inicial no read model é 'open'
        $initialDto = $this->getTicketDtoFromReadModel($ticketId);
        $this->assertSame(Status::OPEN, $initialDto->status);

        // Act
        $response = $this->putJson("/api/ticket/{$ticketId}");

        // Assert
        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Ticket resolvido!']);

        // Verifica se o read model foi atualizado para 'resolved'
        $resolvedDto = $this->getTicketDtoFromReadModel($ticketId);
        $this->assertNotNull($resolvedDto);
        $this->assertSame(Status::RESOLVED, $resolvedDto->status);
        $this->assertNotNull($resolvedDto->resolvedAt);
    }

    /** @test */
    public function resolve_ticket_fails_for_non_existent_ticket(): void
    {
        $nonExistentId = '00000000-0000-0000-0000-000000000000'; // UUID inválido

        $this->putJson("/api/ticket/{$nonExistentId}")
            ->assertStatus(404); // Espera 404 (AggregateNotFoundException)
    }

    /** @test */
    public function resolve_ticket_fails_for_already_resolved_ticket(): void
    {
        // Arrange: Cria e resolve um ticket
        $ticketId = $this->createTicketViaApi([
            'title' => 'Already Resolved',
            'description' => 'This ticket is already resolved.',
            'priority' => 'low'
        ]);
        $this->putJson("/api/ticket/{$ticketId}")->assertStatus(200); // Resolve a primeira vez

        // Act: Tenta resolver novamente, espera erro (409 Conflict)
        $this->putJson("/api/ticket/{$ticketId}")->assertStatus(409);
    }

    // ========================================
    // Testes para GET /api/ticket/{id} (Busca por ID)
    // ========================================

    /** @test */
    public function it_can_get_an_existing_ticket_by_id(): void
    {
        $ticketId = $this->createTicketViaApi([
            'title' => 'Find Me By ID',
            'description' => 'Specific description.',
            'priority' => 'low',
        ]);

        $readRepo = $this->app->make(TicketReadRepositoryInterface::class);
        $dto = $readRepo->findById($ticketId);
        $this->assertNotNull($dto, "DTO não encontrado no read model após criação.");
        $this->assertNotNull($dto->createdAt, "DTO->createdAt não deveria ser nulo.");
        $expectedCreatedAtString = $dto->createdAt->format(DateTimeImmutable::ATOM); // Pega a data real do DTO

        // Act
        $response = $this->getJson("/api/ticket/{$ticketId}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([ // Verifica a estrutura e valores exatos
                'id' => $ticketId,
                'title' => 'Find Me By ID',
                'description' => 'Specific description.',
                'priority' => 'low',
                'status' => Status::OPEN,
                'createdAt' => $expectedCreatedAtString,
                'resolvedAt' => null,
            ]);
    }

    /** @test */
    public function get_ticket_by_id_fails_for_non_existent_ticket(): void
    {
        $nonExistentId = '11111111-1111-1111-1111-111111111111';

        $this->getJson("/api/ticket/{$nonExistentId}")
            ->assertStatus(404);
    }

    // ========================================
    // Testes para GET /api/ticket (Listagem)
    // ========================================

    /** @test */
    public function it_can_get_all_tickets_with_default_sort_created_at_desc(): void
    {
        // Arrange: Cria tickets com datas diferentes diretamente no read model
        $readRepo = $this->app->make(TicketReadRepositoryInterface::class);
        $time1 = new DateTimeImmutable('-2 days');
        $time2 = new DateTimeImmutable('-1 day');
        $time3 = new DateTimeImmutable(); // Mais recente

        $readRepo->save(new TicketDTO('id1', 'Old Ticket', '', 'low', 'open', $time1));
        $readRepo->save(new TicketDTO('id3', 'New Ticket', '', 'high', 'open', $time3)); // Salva fora de ordem
        $readRepo->save(new TicketDTO('id2', 'Mid Ticket', '', 'medium', 'open', $time2));

        // Act
        $response = $this->getJson(self::BASEURL);

        // Assert
        $response
            ->assertStatus(200)
            ->assertJsonCount(3) // Espera 3 tickets
            ->assertJsonPath('0.id', 'id3') // Mais recente primeiro
            ->assertJsonPath('1.id', 'id2')
            ->assertJsonPath('2.id', 'id1'); // Mais antigo por último
    }

    /** @test */
    public function it_can_get_all_tickets_sorted_by_title_asc(): void
    {
        // Arrange: Cria tickets com títulos diferentes
        $readRepo = $this->app->make(TicketReadRepositoryInterface::class);
        $readRepo->save(new TicketDTO('idC', 'Charlie', '', 'low', 'open'));
        $readRepo->save(new TicketDTO('idA', 'Alpha', '', 'high', 'open'));
        $readRepo->save(new TicketDTO('idB', 'Bravo', '', 'medium', 'open'));

        // Act
        $response = $this->getJson('/api/ticket?orderBy=title&orderDirection=asc');

        // Assert
        $response
            ->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonPath('0.id', 'idA') // Alpha
            ->assertJsonPath('1.id', 'idB') // Bravo
            ->assertJsonPath('2.id', 'idC'); // Charlie
    }

     /** @test */
    public function get_all_tickets_uses_default_sort_if_params_not_provided(): void
    {
        // Arrange: Cria tickets
        $readRepo = $this->app->make(TicketReadRepositoryInterface::class);
        $readRepo->save(new TicketDTO('id1', 'Ticket A', '', 'low', 'open', new DateTimeImmutable('-1 day')));
        $readRepo->save(new TicketDTO('id2', 'Ticket B', '', 'high', 'open', new DateTimeImmutable())); // Mais recente

        // Act
        $response = $this->getJson(self::BASEURL); // Sem query params

        // Assert: Deve ordenar por created_at desc (padrão)
        $response
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', 'id2') // Mais recente
            ->assertJsonPath('1.id', 'id1');
    }

    /** @test */
    public function get_all_tickets_fails_with_invalid_orderby_param(): void
    {
        $this->getJson('/api/ticket?orderBy=invalidField&orderDirection=asc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('orderBy');
    }

    /** @test */
    public function get_all_tickets_fails_with_invalid_orderdirection_param(): void
    {
        $this->getJson('/api/ticket?orderBy=title&orderDirection=descending') // Valor inválido
            ->assertStatus(422)
            ->assertJsonValidationErrors('orderDirection');
    }

    /** @test */
    public function get_all_tickets_returns_empty_array_when_no_tickets(): void
    {
        // Act
        $response = $this->getJson(self::BASEURL);

        // Assert
        $response
            ->assertStatus(200)
            ->assertJsonCount(0) // Espera um array vazio
            ->assertExactJson([]); // Verifica se é exatamente []
    }
}
