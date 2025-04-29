<?php

namespace Tests\Unit\Application\UseCases\Queries\GetTicketById;

use App\Application\DTOs\TicketDTO;
use App\Application\UseCases\Queries\GetTicketById\GetTicketByIdHandler;
use App\Application\UseCases\Queries\GetTicketById\GetTicketByIdQuery;
use App\Domain\Exceptions\TicketNotFoundException;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class GetTicketByIdHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketReadRepositoryInterface $mockReadRepository;
    private GetTicketByIdHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockReadRepository = Mockery::mock(TicketReadRepositoryInterface::class);
        $this->handler = new GetTicketByIdHandler($this->mockReadRepository);
    }

    /** @test */
    public function it_handles_get_ticket_by_id_query_and_returns_dto_when_found(): void
    {
        // Arrange
        $ticketId = 'find-me-123';
        $query = new GetTicketByIdQuery($ticketId);

        $expectedDto = new TicketDTO($ticketId, 'Found Ticket', 'Desc', 'high', 'open');

        // Expect findById to be called
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($ticketId)
            ->andReturn($expectedDto);

        // Act
        $result = $this->handler->handle($query);

        // Assert
        $this->assertSame($expectedDto, $result);
    }

    /** @test */
    public function it_throws_ticket_not_found_exception_when_ticket_is_not_found(): void
    {
        // Arrange
        $ticketId = 'find-me-404';
        $query = new GetTicketByIdQuery($ticketId);

        // Expect findById to be called and return null
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($ticketId)
            ->andReturnNull();

        // Assert
        $this->expectException(TicketNotFoundException::class);
        $this->expectExceptionMessage("Ticket com ID {$ticketId} não encontrado.");

        // Act
        $this->handler->handle($query);
    }
}
