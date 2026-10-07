<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fausse API dummyjson pour les tests fonctionnels : aucun appel réseau.
 * Branchée comme client HTTP par défaut en environnement de test (framework.yaml).
 */
final class FakeProductApi
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($path === '/products') {
            return $this->json(['products' => [$this->product(1), $this->product(2)]]);
        }
        if (preg_match('#^/products/(\d+)$#', $path, $m) === 1 && (int) $m[1] <= 9) {
            return $this->json($this->product((int) $m[1]));
        }

        return $this->json(['message' => 'not found'], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function product(int $id): array
    {
        return [
            'id' => $id,
            'title' => 'Produit ' . $id,
            'description' => 'Description du produit ' . $id,
            'price' => 9.99,
            'thumbnail' => 'https://example.test/' . $id . '.png',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type: application/json'],
        ]);
    }
}
