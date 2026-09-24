<?php

namespace Tests\Api;

use Database\Factories\LegacyUserFactory;
use Database\Factories\PlaceFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LegacyNeighborhoodSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            LegacyUserFactory::new()->admin()->create()
        );
    }

    public function test_searches_neighborhood_by_partial_text(): void
    {
        PlaceFactory::new()->create([
            'neighborhood' => 'Vila Nova',
        ]);

        PlaceFactory::new()->create([
            'neighborhood' => 'Centro',
        ]);

        $result = $this->searchNeighborhood('Vila');

        $this->assertArrayHasKey('Vila Nova', $result);
        $this->assertArrayNotHasKey('Centro', $result);
    }

    public function test_search_ignores_accents_and_case(): void
    {
        PlaceFactory::new()->create([
            'neighborhood' => 'Vila São José',
        ]);

        $result = $this->searchNeighborhood('VILA SAO JOSE');

        $this->assertArrayHasKey('Vila São José', $result);
        $this->assertSame('Vila São José', $result['Vila São José']);
    }

    public function test_does_not_return_duplicate_neighborhoods(): void
    {
        PlaceFactory::new()->create([
            'neighborhood' => 'Vila',
            'address' => 'Rua Um',
        ]);

        PlaceFactory::new()->create([
            'neighborhood' => 'Vila',
            'address' => 'Rua Dois',
        ]);

        $result = $this->searchNeighborhood('Vila');

        $this->assertSame([
            'Vila' => 'Vila',
        ], $result);
    }

    public function test_returns_neighborhoods_in_alphabetical_order(): void
    {
        PlaceFactory::new()->create([
            'neighborhood' => 'Vila Zeta',
        ]);

        PlaceFactory::new()->create([
            'neighborhood' => 'Vila Alfa',
        ]);

        PlaceFactory::new()->create([
            'neighborhood' => 'Vila Meio',
        ]);

        $result = $this->searchNeighborhood('Vila');

        $this->assertSame([
            'Vila Alfa',
            'Vila Meio',
            'Vila Zeta',
        ], array_keys($result));
    }

    public function test_limits_search_to_fifteen_neighborhoods(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            PlaceFactory::new()->create([
                'neighborhood' => sprintf('Bairro Limite %02d', $i),
            ]);
        }

        $result = $this->searchNeighborhood('Bairro Limite');

        $this->assertCount(15, $result);
    }

    public function test_persisted_neighborhood_is_available_in_future_searches(): void
    {
        $neighborhood = 'Bairro Teste Issue 1202';

        PlaceFactory::new()->create([
            'neighborhood' => $neighborhood,
        ]);

        $this->assertDatabaseHas('places', [
            'neighborhood' => $neighborhood,
        ]);

        $result = $this->searchNeighborhood('Issue 1202');

        $this->assertArrayHasKey($neighborhood, $result);
        $this->assertSame($neighborhood, $result[$neighborhood]);
    }

    private function searchNeighborhood(string $query): array
    {
        $response = $this->get('/module/Api/Bairro?' . http_build_query([
            'oper' => 'get',
            'resource' => 'bairro-search',
            'query' => $query,
        ]));

        $response->assertOk();

        return $response->json('result');
    }
}
