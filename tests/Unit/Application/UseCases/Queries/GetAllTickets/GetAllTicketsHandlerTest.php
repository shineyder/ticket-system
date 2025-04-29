<?php

namespace Tests\Unit\Application\UseCases\Queries\GetAllTickets;

use App\Application\DTOs\TicketDTO;
use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsHandler;
use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsQuery;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class GetAllTicketsHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketReadRepositoryInterface $mockReadRepository;
    private GetAllTicketsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockReadRepository = Mockery::mock(TicketReadRepositoryInterface::class);
        $this->handler = new GetAllTicketsHandler($this->mockReadRepository);
    }

    /** @test */
    public function it_handles_get_all_tickets_query_and_returns_dtos(): void
    {
        // Arrange
        $orderBy = 'priority';
        $orderDirection = 'asc';
        $query = new GetAllTicketsQuery($orderBy, $orderDirection);

        $expectedDtos = [
            new TicketDTO('id1', 'Title 1', 'Desc 1', 'low', 'open'),
            new TicketDTO('id2', 'Title 2', 'Desc 2', 'medium', 'resolved'),
        ];

        // Expect findAll to be called with correct parameters
        $this->mockReadRepository
            ->shouldReceive('findAll')
            ->once()
            ->with($orderBy, $orderDirection)
            ->andReturn($expectedDtos);

        // Act
        $result = $this->handler->handle($query);

        // Assert
        $this->assertSame($expectedDtos, $result);
    }

     /** @test */
    public function it_handles_get_all_tickets_query_and_returns_empty_array_when_no_tickets(): void
    {
        // Arrange
        $orderBy = 'created_at';
        $orderDirection = 'desc';
        $query = new GetAllTicketsQuery($orderBy, $orderDirection);

        $expectedDtos = []; // Empty array

        // Expect findAll to be called
        $this->mockReadRepository
            ->shouldReceive('findAll')
            ->once()
            ->with($orderBy, $orderDirection)
            ->andReturn($expectedDtos);

        // Act
        $result = $this->handler->handle($query);

        // Assert
        $this->assertSame($expectedDtos, $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
