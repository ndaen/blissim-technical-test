<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fonctionnel : vrai noyau Symfony, vraie base MySQL, mais fausse API produits (FakeProductApi).
 */
final class CommentFlowTest extends WebTestCase
{
    private const AUTHOR = 'PHPUnit';

    private function pdo(): PDO
    {
        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME') ?: 'blissim',
            ),
            getenv('DB_USER') ?: 'app',
            getenv('DB_PASSWORD') ?: 'app',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function createComment(): int
    {
        $pdo = $this->pdo();
        $pdo->prepare('INSERT INTO comments (product_id, author, content) VALUES (1, :author, :content)')
            ->execute(['author' => self::AUTHOR, 'content' => 'à supprimer']);

        return (int) $pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->pdo()->prepare('DELETE FROM comments WHERE author = :author')->execute(['author' => self::AUTHOR]);

        parent::tearDown();
    }

    public function testProductListIsDisplayed(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.card');
    }

    public function testCommentCanBePostedAndIsDisplayed(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/products/1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Produit 1');

        $form = $crawler->selectButton('Publier')->form([
            'comment[author]' => self::AUTHOR,
            'comment[content]' => 'Test automatique <b>HTML</b>',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/products/1', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('#comments', 'Test automatique');
        self::assertSelectorNotExists('#comments .comment b');
    }

    public function testInvalidCommentIsRejectedWith422(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/products/1');

        $form = $crawler->selectButton('Publier')->form([
            'comment[author]' => '',
            'comment[content]' => 'Sans auteur',
        ]);
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#comments', 'Le nom est obligatoire.');
    }

    public function testUnknownProductReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/products/99999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRequiresValidToken(): void
    {
        $client = static::createClient();
        $id = $this->createComment();

        $client->request('POST', '/comment/' . $id . '/delete', ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testDeleteByGetIsNotAllowed(): void
    {
        $client = static::createClient();
        $id = $this->createComment();

        $client->request('GET', '/comment/' . $id . '/delete');

        self::assertResponseStatusCodeSame(405);
    }
}
