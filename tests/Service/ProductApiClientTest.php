<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProductApiClient;
use App\Service\ProductApiException;
use App\Service\ProductNotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProductApiClientTest extends TestCase
{

    private function client(MockResponse $response): ProductApiClient
    {
        return new ProductApiClient(new MockHttpClient($response), 'https://api.test');
    }

    /**
     * @return array<string, mixed>
     */
    private function productData(int $id, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => 'Description',
            'price' => 12.5,
            'thumbnail' => 'https://api.test/' . $id . '.png',
        ];
    }

    public function testFindAllReturnsProducts(): void
    {
        $body = json_encode(['products' => [$this->productData(1, 'Rouge à lèvres'), $this->productData(2, 'Mascara')]], JSON_THROW_ON_ERROR);

        $products = $this->client(new MockResponse($body))->findAll();

        self::assertCount(2, $products);
        self::assertSame('Rouge à lèvres', $products[0]->title);
    }

    public function testFindAllRejectsResponseWithoutProductsKey(): void
    {
        $this->expectException(ProductApiException::class);

        $this->client(new MockResponse('{"total": 0}'))->findAll();
    }

    public function testFindByIdReturnsProduct(): void
    {
        $body = json_encode($this->productData(7, 'Parfum'), JSON_THROW_ON_ERROR);

        $product = $this->client(new MockResponse($body))->findById(7);

        self::assertSame(7, $product->id);
        self::assertSame('Parfum', $product->title);
        self::assertSame(12.5, $product->price);
    }

    public function testFindByIdThrowsNotFoundOn404(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->client(new MockResponse('{}', ['http_code' => 404]))->findById(99999);
    }

    public function testServerErrorIsAnOutageNotANotFound(): void
    {
        try {
            $this->client(new MockResponse('', ['http_code' => 500]))->findById(1);
            self::fail('Une ProductApiException était attendue.');
        } catch (ProductApiException $e) {
            self::assertNotInstanceOf(ProductNotFoundException::class, $e);
            self::assertStringContainsString('500', $e->getMessage());
        }
    }

    public function testInvalidJsonThrowsApiException(): void
    {
        $this->expectException(ProductApiException::class);

        $this->client(new MockResponse('ceci n\'est pas du json'))->findById(1);
    }

    public function testNetworkFailureThrowsApiException(): void
    {
        $this->expectException(ProductApiException::class);

        $this->client(new MockResponse('', ['error' => 'Connexion refusée']))->findById(1);
    }
}
