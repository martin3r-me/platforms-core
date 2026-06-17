<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Platform\Core\Contracts\EmbeddingProviderContract;
use Platform\Core\Contracts\EmbeddingStoreContract;
use Platform\Core\Exceptions\EmbeddingDimensionMismatchException;
use Platform\Core\Services\EmbeddingProviderRegistry;
use Platform\Core\Services\EmbeddingService;
use Platform\Core\Services\MySqlJsonEmbeddingStore;
use Platform\Core\Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    private function bootService(EmbeddingProviderContract $provider): EmbeddingService
    {
        $registry = new EmbeddingProviderRegistry();
        $registry->register($provider);

        $store = new MySqlJsonEmbeddingStore();

        return new EmbeddingService($registry, $store);
    }

    public function test_round_trip_returns_semantically_closest_match(): void
    {
        $provider = new ToyKeywordEmbeddingProvider(['pasta', 'cheese', 'wine']);
        $service = $this->bootService($provider);

        $service->embedAndStore(1, 'test_doc', 'a', 'Trüffel passt zu Pasta und Eierspeisen');
        $service->embedAndStore(1, 'test_doc', 'b', 'Rote Bete passt zu Ziegenkäse und Walnuss');
        $service->embedAndStore(1, 'test_doc', 'c', 'Wein aus dem Rheingau');

        $results = $service->search(1, 'was passt zu Pasta?', ['test_doc'], limit: 3);

        $this->assertNotEmpty($results);
        $this->assertSame('a', $results[0]['entity_id']);
        $this->assertGreaterThan(0.5, $results[0]['score']);
    }

    public function test_skip_if_unchanged_does_not_call_provider_twice(): void
    {
        $provider = new ToyKeywordEmbeddingProvider(['pasta']);
        $service = $this->bootService($provider);

        $service->embedAndStore(1, 'test_doc', 'a', 'Pasta mit Pesto');
        $callsAfterFirst = $provider->embedCallCount;

        $service->embedAndStore(1, 'test_doc', 'a', 'Pasta mit Pesto');

        $this->assertSame($callsAfterFirst, $provider->embedCallCount);
    }

    public function test_team_scoping_isolates_results(): void
    {
        $provider = new ToyKeywordEmbeddingProvider(['pasta']);
        $service = $this->bootService($provider);

        $service->embedAndStore(1, 'test_doc', 'a', 'Pasta mit Pesto');
        $service->embedAndStore(2, 'test_doc', 'b', 'Pasta mit Carbonara');

        $resultsTeam1 = $service->search(1, 'Pasta', ['test_doc']);
        $this->assertCount(1, $resultsTeam1);
        $this->assertSame('a', $resultsTeam1[0]['entity_id']);

        $resultsTeam2 = $service->search(2, 'Pasta', ['test_doc']);
        $this->assertCount(1, $resultsTeam2);
        $this->assertSame('b', $resultsTeam2[0]['entity_id']);
    }

    public function test_dimension_mismatch_throws(): void
    {
        $smallProvider = new ToyKeywordEmbeddingProvider(['pasta']);
        $service = $this->bootService($smallProvider);
        $service->embedAndStore(1, 'test_doc', 'a', 'Pasta');

        // Manually craft a search call that uses a different dimension under the same
        // provider/model identifier — simulates a stored model whose dimensions
        // no longer match the provider's current vector size.
        $store = new MySqlJsonEmbeddingStore();
        $wrongDim = array_fill(0, 5, 0.1);

        $this->expectException(EmbeddingDimensionMismatchException::class);
        $store->search(
            teamId: 1,
            queryVector: $wrongDim,
            provider: $smallProvider->getName(),
            model: $smallProvider->getModel(),
            entityTypes: ['test_doc'],
        );
    }

    public function test_delete_removes_embedding(): void
    {
        $provider = new ToyKeywordEmbeddingProvider(['pasta']);
        $service = $this->bootService($provider);

        $service->embedAndStore(1, 'test_doc', 'a', 'Pasta');
        $this->assertNotEmpty($service->search(1, 'Pasta', ['test_doc']));

        $service->delete(1, 'test_doc', 'a');
        $this->assertEmpty($service->search(1, 'Pasta', ['test_doc']));
    }
}

/**
 * Deterministischer Test-Provider: jeder Vektor-Dimension entspricht ein Keyword.
 * Wert pro Dimension = Anzahl Treffer des Keywords im Text (case-insensitive),
 * geteilt durch Text-Tokenzahl. Damit teilen Texte mit denselben Keywords einen
 * ähnlichen Vektor und Cosine bildet semantische Nähe ab — ohne API-Call.
 */
class ToyKeywordEmbeddingProvider implements EmbeddingProviderContract
{
    public int $embedCallCount = 0;

    /**
     * @param string[] $keywords
     */
    public function __construct(private readonly array $keywords) {}

    public function getName(): string
    {
        return 'toy';
    }

    public function getModel(): string
    {
        return 'toy-v1';
    }

    public function getDimensions(): int
    {
        return count($this->keywords);
    }

    public function isNormalized(): bool
    {
        return false;
    }

    public function getMaxBatchSize(): int
    {
        return 100;
    }

    public function embed(array $texts, string $type = 'document'): array
    {
        $this->embedCallCount++;
        $out = [];
        foreach ($texts as $text) {
            $lower = mb_strtolower((string) $text);
            $vec = [];
            foreach ($this->keywords as $kw) {
                $vec[] = (float) (substr_count($lower, mb_strtolower($kw)) > 0 ? 1.0 : 0.0);
            }
            // Wenn kein Keyword im Text → kleiner Bias auf erstes Element,
            // damit Norm > 0 bleibt (sonst keine Cosine-Berechnung möglich).
            if (array_sum($vec) === 0.0) {
                $vec[0] = 0.01;
            }
            $out[] = $vec;
        }
        return $out;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
