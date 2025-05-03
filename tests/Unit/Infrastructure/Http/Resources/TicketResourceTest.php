<?php

namespace Tests\Unit\Infrastructure\Http\Resources;

use App\Application\DTOs\TicketDTO;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Http\Resources\TicketResource;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\URL;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class TicketResourceTest extends TestCase
{
    private string $testId = 'test-uuid-123';
    private string $baseUrl = 'http://localhost';

    private const TICKET_URL = '/api/v1/ticket/';
    private const TICKET_URL_WITHOUT_SLASH = '/api/v1/ticket';

    protected function setUp(): void
    {
        parent::setUp();

        // Define as rotas nomeadas necessárias para que route() funcione nos testes
        RouteFacade::shouldReceive('has')->andReturn(true); // Diz que as rotas existem
        RouteFacade::shouldReceive('current')->andReturn(null); // Evita erros internos do route()

        // Adiciona a expectativa para getRoutes() para evitar o erro do Mockery
        RouteFacade::shouldReceive('getRoutes')->andReturn(new RouteCollection());

        // Simula a geração das URLs esperadas
        URL::shouldReceive('route')
            ->with('tickets.show', ['id' => $this->testId], true)
            ->andReturn($this->baseUrl . self::TICKET_URL . $this->testId);
        URL::shouldReceive('route')
            ->with('tickets.index', [], true)
            ->andReturn($this->baseUrl . self::TICKET_URL_WITHOUT_SLASH);
        URL::shouldReceive('route')
            ->with('tickets.resolve', ['id' => $this->testId], true)
            ->andReturn($this->baseUrl . self::TICKET_URL . $this->testId);
    }

    /** @test */
    public function it_transforms_open_ticket_dto_correctly_with_resolve_link(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2024-03-15T10:00:00Z');
        $dto = new TicketDTO(
            $this->testId,
            'Test Title Open',
            'Test Description',
            'medium',
            Status::OPEN,
            $createdAt,
            null // resolvedAt é null para OPEN
        );

        $resource = new TicketResource($dto);
        $request = Request::create(self::TICKET_URL . $this->testId, 'GET'); // Simula um request

        // Act
        $result = $resource->toArray($request);

        // Assert
        $this->assertEquals($this->testId, $result['id']);
        $this->assertEquals('Test Title Open', $result['title']);
        $this->assertEquals(Status::OPEN, $result['status']);
        $this->assertEquals($createdAt->format(DateTimeImmutable::ATOM), $result['createdAt']);
        $this->assertNull($result['resolvedAt']);

        $this->assertArrayHasKey('_links', $result);
        $this->assertEquals(['href' => $this->baseUrl . self::TICKET_URL . $this->testId], $result['_links']['self']);
        $this->assertEquals(['href' => $this->baseUrl . self::TICKET_URL_WITHOUT_SLASH], $result['_links']['collection']);
        $this->assertArrayHasKey('resolve', $result['_links']); // Deve ter o link resolve
        $this->assertEquals(
            ['href' => $this->baseUrl . self::TICKET_URL . $this->testId, 'method' => 'PUT'],
            $result['_links']['resolve']
        );
    }

    /** @test */
    public function it_transforms_resolved_ticket_dto_correctly_without_resolve_link(): void
    {
        // Arrange
        $createdAt = new DateTimeImmutable('2024-03-14T09:00:00Z');
        $resolvedAt = new DateTimeImmutable('2024-03-15T11:30:00Z');
        $dto = new TicketDTO(
            $this->testId,
            'Test Title Resolved',
            'Resolved Description',
            'low',
            Status::RESOLVED,
            $createdAt,
            $resolvedAt
        );

        $resource = new TicketResource($dto);
        $request = Request::create(self::TICKET_URL . $this->testId, 'GET');

        // Act
        $result = $resource->toArray($request);

        // Assert
        $this->assertEquals(Status::RESOLVED, $result['status']);
        $this->assertEquals($resolvedAt->format(DateTimeImmutable::ATOM), $result['resolvedAt']);
        $this->assertArrayHasKey('_links', $result);
        $this->assertArrayNotHasKey('resolve', $result['_links']); // NÃO deve ter o link resolve
        $this->assertEquals(['href' => $this->baseUrl . self::TICKET_URL . $this->testId], $result['_links']['self']);
        $this->assertEquals(['href' => $this->baseUrl . self::TICKET_URL_WITHOUT_SLASH], $result['_links']['collection']);
    }
}
