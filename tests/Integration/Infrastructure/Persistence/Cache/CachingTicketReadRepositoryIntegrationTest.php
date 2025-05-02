<?php

namespace Tests\Integration\Infrastructure\Persistence\Cache;

use App\Application\DTOs\TicketDTO;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Infrastructure\Persistence\Cache\CachingTicketReadRepository;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CachingTicketReadRepositoryIntegrationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketReadRepositoryInterface $decoratedRepositoryMock;
    private CacheManager $cacheManager;
    private CachingTicketReadRepository $cachingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Configura para usar Redis e limpa antes de cada teste
        config(['cache.default' => 'redis']);
        $this->app['cache']->store('redis')->flush();

        // Mocka apenas o repositório que seria acessado (Mongo)
        $this->decoratedRepositoryMock = Mockery::mock(TicketReadRepositoryInterface::class);

        // Pega a instância real do CacheManager
        $this->cacheManager = $this->app->make(CacheManager::class);

        // Instancia o Caching Repo com o mock do decorado e o CacheManager real
        $this->cachingRepository = new CachingTicketReadRepository(
            $this->decoratedRepositoryMock,
            $this->cacheManager
        );
    }

    /** @test */
    public function findAll_stores_result_in_cache_on_first_call_and_retrieves_from_cache_on_second(): void
    {
        $orderBy = 'created_at';
        $orderDirection = 'desc';
        $cacheKey = "tickets:all:{$orderBy}:{$orderDirection}";
        $repoData = [new TicketDTO('id1', 'First Call', '', 'low', 'open')];

        // Expectativa para a primeira chamada (cache miss)
        $this->decoratedRepositoryMock
            ->shouldReceive('findAll')
            ->once() // Deve ser chamado apenas uma vez
            ->with($orderBy, $orderDirection)
            ->andReturn($repoData);

        // 1ª Ação: Chama findAll - deve buscar no repositório mockado e guardar no cache
        $result1 = $this->cachingRepository->findAll($orderBy, $orderDirection);

        // Asserção 1: Verifica se o resultado está correto e se está no cache real
        $this->assertEquals($repoData, $result1);
        $this->assertTrue(Cache::tags(CachingTicketReadRepository::CACHE_TAG)->has($cacheKey));
        $this->assertEquals($repoData, Cache::tags(CachingTicketReadRepository::CACHE_TAG)->get($cacheKey));

        // 2ª Ação: Chama findAll novamente com os mesmos parâmetros
        $result2 = $this->cachingRepository->findAll($orderBy, $orderDirection);

        // Asserção 2: Verifica se o resultado veio do cache (mock não deve ser chamado de novo)
        $this->assertEquals($repoData, $result2);
        // A expectativa do Mockery (->once()) já garante que findAll do mock não foi chamado de novo.
    }

    /** @test */
    public function findAll_fetches_again_after_cache_flush_by_tag(): void
    {
        $orderBy = 'priority';
        $orderDirection = 'asc';
        $repoData1 = [new TicketDTO('id1', 'Data 1', '', 'low', 'open')];
        $repoData2 = [new TicketDTO('id2', 'Data 2 After Flush', '', 'high', 'resolved')]; // Dados diferentes para a segunda busca

        // Expectativa para a primeira chamada (cache miss)
        $this->decoratedRepositoryMock
            ->shouldReceive('findAll')
            ->once() // Chamado na primeira vez
            ->with($orderBy, $orderDirection)
            ->andReturn($repoData1);

        // 1ª Ação: Popula o cache
        $this->cachingRepository->findAll($orderBy, $orderDirection);

        // Ação Intermediária: Limpa o cache pela tag
        Cache::tags(CachingTicketReadRepository::CACHE_TAG)->flush();
        $this->assertFalse(Cache::tags(CachingTicketReadRepository::CACHE_TAG)->has("tickets:all:{$orderBy}:{$orderDirection}"));

        // Expectativa para a segunda chamada (após flush)
        $this->decoratedRepositoryMock
            ->shouldReceive('findAll')
            ->once() // Chamado novamente após o flush
            ->with($orderBy, $orderDirection)
            ->andReturn($repoData2);

        // 2ª Ação: Chama findAll novamente
        $result = $this->cachingRepository->findAll($orderBy, $orderDirection);

        // Asserção: Verifica se buscou os novos dados do repositório mockado
        $this->assertEquals($repoData2, $result);
    }
}
