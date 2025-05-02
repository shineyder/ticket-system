<?php

namespace Tests\Unit\Infrastructure\Persistence\Cache;

use App\Application\DTOs\TicketDTO;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Infrastructure\Persistence\Cache\CachingTicketReadRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\TaggedCache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CachingTicketReadRepositoryTest extends TestCase
{
    // Integração do Mockery com PHPUnit para limpeza automática
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketReadRepositoryInterface $decoratedRepositoryMock;
    private Mockery\MockInterface|CacheManager $cacheManagerMock;
    private Mockery\MockInterface|TaggedCache $taggedCacheMock;
    private CachingTicketReadRepository $cachingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria mocks para as dependências
        $this->decoratedRepositoryMock = Mockery::mock(TicketReadRepositoryInterface::class);
        $this->cacheManagerMock = Mockery::mock(CacheManager::class);
        $this->taggedCacheMock = Mockery::mock(TaggedCache::class); // Mock para o resultado de ->tags()

        // Configura o mock do CacheManager para retornar o mock do TaggedCache quando ->tags() for chamado
        $this->cacheManagerMock
            ->shouldReceive('tags')
            ->with(CachingTicketReadRepository::CACHE_TAG)
            ->andReturn($this->taggedCacheMock);

        // Instancia a classe sob teste com os mocks
        $this->cachingRepository = new CachingTicketReadRepository(
            $this->decoratedRepositoryMock,
            $this->cacheManagerMock
        );
    }

    /** @test */
    public function save_delegates_call_to_decorated_repository(): void
    {
        $dto = new TicketDTO('id1', 'Test', '', 'low', 'open');

        // Expectativa: O método save do repositório decorado deve ser chamado uma vez com o DTO
        $this->decoratedRepositoryMock
            ->shouldReceive('save')
            ->once()
            ->with($dto);

        // Ação
        $this->cachingRepository->save($dto);

        // A asserção é feita pela expectativa do Mockery
    }

    /** @test */
    public function findById_delegates_call_to_decorated_repository(): void
    {
        $ticketId = 'test-id';
        $expectedDto = new TicketDTO($ticketId, 'Test', '', 'low', 'open');

        // Expectativa: findById do decorado é chamado e retorna o DTO esperado
        $this->decoratedRepositoryMock
            ->shouldReceive('findById')
            ->once()
            ->with($ticketId)
            ->andReturn($expectedDto);

        // Ação
        $result = $this->cachingRepository->findById($ticketId);

        // Asserção
        $this->assertSame($expectedDto, $result);
    }

    /** @test */
    public function findAll_fetches_from_repository_on_cache_miss(): void
    {
        $orderBy = 'title';
        $orderDirection = 'asc';
        $cacheKey = "tickets:all:{$orderBy}:{$orderDirection}";
        $expectedData = [new TicketDTO('id1', 'A', '', 'low', 'open')];

        // Expectativa: O cache->remember() vai executar a closure (cache miss)
        $this->taggedCacheMock
            ->shouldReceive('remember')
            ->once()
            ->with($cacheKey, Mockery::any(), Mockery::on(function ($closure) use ($expectedData, $orderBy, $orderDirection) {
                // Dentro da closure, esperamos que o repositório decorado seja chamado
                $this->decoratedRepositoryMock
                    ->shouldReceive('findAll')
                    ->once()
                    ->with($orderBy, $orderDirection)
                    ->andReturn($expectedData);
                // Executa a closure e verifica se o retorno é o esperado
                return $closure() === $expectedData;
            }))
            ->andReturn($expectedData); // O remember retorna o resultado da closure no miss

        // Ação
        $result = $this->cachingRepository->findAll($orderBy, $orderDirection);

        // Asserção
        $this->assertSame($expectedData, $result);
    }

    /** @test */
    public function findAll_returns_from_cache_on_cache_hit(): void
    {
        $orderBy = 'created_at';
        $orderDirection = 'desc';
        $cacheKey = "tickets:all:{$orderBy}:{$orderDirection}";
        $cachedData = [new TicketDTO('id-cached', 'Cached', '', 'high', 'resolved')];

        // Expectativa: O cache->remember() retorna os dados cacheados diretamente, sem executar a closure
        $this->taggedCacheMock
            ->shouldReceive('remember')
            ->once()
            ->with($cacheKey, Mockery::any(), Mockery::any()) // Closure não será executada
            ->andReturn($cachedData);

        // Expectativa: O repositório decorado NÃO deve ser chamado
        $this->decoratedRepositoryMock->shouldNotReceive('findAll');

        // Ação
        $result = $this->cachingRepository->findAll($orderBy, $orderDirection);

        // Asserção
        $this->assertSame($cachedData, $result);
    }
}
