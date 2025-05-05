<?php

namespace App\Infrastructure\Persistence\MongoDB\Projections;

use App\Application\DTOs\TicketDTO;
use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class UpdateTicketsReadModelProjection implements ShouldQueue
{
    private const CACHE_TAG_TO_INVALIDATE = 'tickets-list';
    private const PROCESSED_EVENT_CACHE_PREFIX = 'processed_event:';
    private const PROCESSED_EVENT_TTL = 900; // 15 minutos

    /**
     * O número máximo de vezes que o job pode ser tentado.
     * (Inclui a primeira tentativa)
     */
    public int $tries = 5;

    /**
     * O número máximo de exceções permitidas antes de falhar.
     */
    public int $maxExceptions = 3;

    /**
     * Calcula o número de segundos de espera antes de tentar o job novamente (backoff exponencial).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60]; // Segundos de espera para as tentativas 2, 3 e 4 (a 5ª falhará se chegar lá)
    }

    public function __construct(
        private TicketReadRepositoryInterface $readRepository,
        private readonly CacheManager $cache
    ) {}

    /**
     * Manipula o evento DomainEventsPersisted para atualizar a projeção.
     *
     * @param DomainEventsPersisted $eventWrapper
     * @return void
     */
    public function handle(DomainEventsPersisted $eventWrapper): void
    {
        if ($eventWrapper->aggregateType !== 'Ticket') {
            return;
        }

        $aggregateId = $eventWrapper->aggregateId;
        $cacheNeedsInvalidation = false;

        foreach ($eventWrapper->domainEvents as $domainEvent) {
            $eventId = $domainEvent->getEventId();
            $processedEventCacheKey = self::PROCESSED_EVENT_CACHE_PREFIX . $eventId;

            // Verificar Idempotência
            if ($this->cache->has($processedEventCacheKey)) {
                Log::debug('Evento já processado, pulando (idempotência).', [
                    'eventId' => $eventId,
                    'eventType' => get_class($domainEvent)
                ]);
                continue; // Pula para o próximo evento
            }

            try {
                // Carrega o DTO atual (ou null se for novo)
                $currentDto = $this->readRepository->findById($aggregateId);

                // Aplica as mudanças do evento específico
                $updatedDto = $this->applyEventToDTO($domainEvent, $currentDto);

                // Salva o DTO atualizado (ou cria se for novo)
                if ($updatedDto) {
                    $this->readRepository->save($updatedDto);
                    $cacheNeedsInvalidation = true;

                    // Marcar como processado APÓS sucesso
                    $this->cache->put($processedEventCacheKey, true, self::PROCESSED_EVENT_TTL);
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

        if ($cacheNeedsInvalidation) {
            try {
                // Remove todas as entradas de cache associadas à tag 'tickets-list'
                $this->cache->tags(self::CACHE_TAG_TO_INVALIDATE)->flush();
                Log::debug('Cache de listagem de tickets invalidado.', ['tag' => self::CACHE_TAG_TO_INVALIDATE]);
            } catch (Throwable $e) {
                Log::error('Falha ao invalidar cache de tickets.', [
                    'tag' => self::CACHE_TAG_TO_INVALIDATE,
                    'error' => $e->getMessage(),
                ]);
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
                id: $domainEvent->getAggregateId(),
                title: $domainEvent->title,
                description: $domainEvent->description,
                priority: (new Priority($domainEvent->priority))->toString(),
                status: Status::OPEN, // Status inicial padrão
                createdAt: $domainEvent->getOccurredOn(),
                resolvedAt: null
            ),
            TicketResolved::class => $currentDto ? $currentDto->markAsResolved(
                $domainEvent->getOccurredOn() // Passa a data de resolução
            ) : null, // Não faz sentido resolver um ticket que não foi criado
            default => $currentDto, // Se o evento não for reconhecido, retorna o DTO atual sem modificação
        };
    }
}
