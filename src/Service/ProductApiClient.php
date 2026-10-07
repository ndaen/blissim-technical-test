<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Product;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProductApiClient
{
    public function __construct(
        private readonly HttpClientInterface                        $httpClient,
        #[Autowire(env: 'PRODUCT_API_URL')] private readonly string $baseUrl
    )
    {
    }

    /**
     * @return list<Product>
     *
     * @throws ProductApiException
     */
    public function findAll(): array
    {
        $data = $this->request('/products?limit=30');
        if (!isset($data['products']) || !is_array($data['products'])) {
            throw new ProductApiException('Réponse invalide : liste de produits absente');
        }

        return array_map(Product::fromArray(...), $data['products']);
    }

    /**
     * @throws ProductNotFoundException
     * @throws ProductApiException
     */
    public function findById(int $id): Product
    {
        return Product::fromArray($this->request('/products/' . $id));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ProductNotFoundException
     * @throws ProductApiException
     */
    private function request(string $path): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . $path, [
            'timeout' => 3,
            'max_duration' => 10,
            'max_redirects' => 0
        ]);

        try {
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new ProductApiException('API injoignable : ' . $e->getMessage(), 0, $e);
        }
        if ($status === Response::HTTP_NOT_FOUND) {
            throw new ProductNotFoundException('Produit introuvable');
        }
        if ($status !== Response::HTTP_OK) {
            throw new ProductApiException(sprintf('Réponse HTTP inattendue : %d', $status));
        }

        try {
            return $response->toArray();
        } catch (TransportExceptionInterface | DecodingExceptionInterface $e) {
            throw new ProductApiException('Réponse illisible : ' . $e->getMessage(), 0, $e);
        }
    }
}
