<?php

namespace App\Infrastructure\Persistence\MongoDB\Projections;

use App\Application\DTOs\TicketDTO;
use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Events\TicketStatusChanged;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Domain\ValueObjects\Status;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateTicketsReadModelProjection
{
    public function __construct(
        private TicketReadRepositoryInterface $readRepository
    ) {}

    /**
     * Manipula o evento DomainEventsPersisted para atualizar a projeção.
     *
     * @param DomainEventsPersisted $eventWrapper
     * @return void
     */
    public function handle(DomainEventsPersisted $eventWrapper): void
    {
        // Processa apenas eventos do agregado 'Ticket'
        if ($eventWrapper->aggregateType !== 'Ticket') {
            return;
        }

        $aggregateId = $eventWrapper->aggregateId;

        foreach ($eventWrapper->domainEvents as $domainEvent) {
            try {
                // Carrega o DTO atual (ou null se for novo)
                $currentDto = $this->readRepository->findById($aggregateId);

                // Aplica as mudanças do evento específico
                $updatedDto = $this->applyEventToDTO($domainEvent, $currentDto);

                // Salva o DTO atualizado (ou cria se for novo)
                if ($updatedDto) {
                    $this->readRepository->save($updatedDto);
                    Log::debug('Read model atualizado', ['ticket_id' => $aggregateId, 'event' => get_class($domainEvent)]);
                }

            } catch (Throwable $e) {
                Log::error(
                    'Erro ao atualizar read model do ticket',
                    [
                        'ticket_id' => $aggregateId,
                        'event' => get_class($domainEvent),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString() // Remover para produção, muito verboso
                    ]
                );
            }
        }
    }

    /**
     * Aplica um evento de domínio a um DTO, retornando o DTO modificado.
     *
     * @param mixed $domainEvent O evento de domínio.
     * @param TicketDTO|null $currentDto O estado atual do DTO (pode ser null).
     * @return TicketDTO|null O DTO atualizado ou null se o evento não for relevante.
     */
    private function applyEventToDTO(mixed $domainEvent, ?TicketDTO $currentDto): ?TicketDTO
    {
        return match (get_class($domainEvent)) {
            TicketCreated::class => new TicketDTO(
                id: $domainEvent->aggregateId,
                title: $domainEvent->title,
                description: $domainEvent->description,
                priority: $domainEvent->priority, // Assume que o evento tem o valor string/int
                status: Status::OPEN, // Status inicial padrão
                createdAt: $domainEvent->getOccurredOn(),
                resolvedAt: null
            ),
            TicketResolved::class => $currentDto ? $currentDto->withStatus(
                Status::RESOLVED,
                $domainEvent->getOccurredOn() // Passa a data de resolução
            ) : null, // Não faz sentido resolver um ticket que não foi criado
            TicketStatusChanged::class => $currentDto ? $currentDto->withStatus(
                $domainEvent->status, // Assume que o evento tem o novo valor string do status
                $domainEvent->status === Status::RESOLVED ? $domainEvent->getOccurredOn() : $currentDto->resolvedAt
            ) : null,
            default => $currentDto, // Se o evento não for reconhecido, retorna o DTO atual sem modificação
        };
    }
}
