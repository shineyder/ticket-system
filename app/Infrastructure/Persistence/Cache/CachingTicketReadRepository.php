<?php

namespace App\Infrastructure\Persistence\Cache;

use App\Application\DTOs\TicketDTO;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use Illuminate\Cache\CacheManager; // Cache Manager

class CachingTicketReadRepository implements TicketReadRepositoryInterface
{
    public const CACHE_TAG = 'tickets-list'; // Tag para invalidar o cache de listagem
    private const CACHE_TTL = 600; // Tempo de vida do cache em segundos (10 minutos)

    public function __construct(
        // Injeta a implementação "real" do repositório
        private readonly TicketReadRepositoryInterface $decoratedRepository,
        // Injeta o repositório de cache do Laravel
        private readonly CacheManager $cache
    ) {}

    /**
     * Salva ou atualiza um DTO.
     * A operação de escrita NÃO é cacheada, apenas delega.
     * A invalidação ocorrerá no listener que chama este save.
     */
    public function save(TicketDTO $ticketDto): void
    {
        $this->decoratedRepository->save($ticketDto);
        // A invalidação será feita pelo listener após esta chamada ter sucesso.
    }

    /**
     * Encontra por ID.
     * Geralmente não cacheamos buscas por ID individualmente, pois são rápidas
     * e a invalidação seria mais complexa. Apenas delega.
     */
    public function findById(string $ticketId): ?TicketDTO
    {
        return $this->decoratedRepository->findById($ticketId);
    }

    /**
     * Recupera todos os DTOs, aplicando cache com tags.
     *
     * @param string $orderBy
     * @param string $orderDirection
     * @return TicketDTO[]
     */
    public function findAll(string $orderBy = 'created_at', string $orderDirection = 'desc'): array
    {
        // Cria uma chave de cache única baseada nos parâmetros de ordenação
        $cacheKey = "tickets:all:{$orderBy}:{$orderDirection}";

        // Usa CacheManager::tags() para associar a tag 'tickets-list' a esta entrada
        // Usa CacheManager::remember() para buscar do cache ou executar a closure se não encontrar
        return $this->cache->tags(self::CACHE_TAG)->remember(
            $cacheKey,
            self::CACHE_TTL,
            // Closure executada apenas se o item não estiver no cache
            function () use ($orderBy, $orderDirection) {
                // Chama o método real do repositório decorado
                return $this->decoratedRepository->findAll($orderBy, $orderDirection);
            }
        );
    }
}
