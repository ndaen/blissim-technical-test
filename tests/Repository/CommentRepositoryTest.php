<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\CommentRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration : il faut la base MySQL du docker-compose (variables DB_*).
 * Chaque test tourne dans une transaction annulée à la fin, la base reste intacte.
 */
final class CommentRepositoryTest extends TestCase
{
    private const PRODUCT_ID = 999999;

    private PDO $pdo;
    private CommentRepository $repository;

    protected function setUp(): void
    {
        try {
            $this->pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    getenv('DB_HOST') ?: '127.0.0.1',
                    getenv('DB_PORT') ?: '3306',
                    getenv('DB_NAME') ?: 'blissim',
                ),
                getenv('DB_USER') ?: 'app',
                getenv('DB_PASSWORD') ?: 'app',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 3,
                ],
            );
        } catch (PDOException $e) {
            self::markTestSkipped('Base de données indisponible : ' . $e->getMessage());
        }

        $this->pdo->beginTransaction();
        $this->repository = new CommentRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testAddThenFind(): void
    {
        $id = $this->repository->add(self::PRODUCT_ID, 'Noah', 'Très bon produit');

        $comment = $this->repository->find($id);

        self::assertNotNull($comment);
        self::assertSame($id, $comment->id);
        self::assertSame(self::PRODUCT_ID, $comment->productId);
        self::assertSame('Noah', $comment->author);
        self::assertSame('Très bon produit', $comment->content);
        self::assertNull($comment->updatedAt);
    }

    public function testFindByProductReturnsNewestFirst(): void
    {
        $this->repository->add(self::PRODUCT_ID, 'Premier', 'a');
        $this->repository->add(self::PRODUCT_ID, 'Second', 'b');

        $comments = $this->repository->findByProduct(self::PRODUCT_ID);

        self::assertCount(2, $comments);
        self::assertSame('Second', $comments[0]->author);
        self::assertSame('Premier', $comments[1]->author);
    }

    public function testFindByProductIgnoresOtherProducts(): void
    {
        $this->repository->add(self::PRODUCT_ID, 'Noah', 'a');

        self::assertSame([], $this->repository->findByProduct(self::PRODUCT_ID + 1));
    }

    public function testUpdateChangesFieldsAndSetsUpdatedAt(): void
    {
        $id = $this->repository->add(self::PRODUCT_ID, 'Noah', 'avant');

        $this->repository->update($id, 'Noah M.', 'après');

        $comment = $this->repository->find($id);
        self::assertNotNull($comment);
        self::assertSame('Noah M.', $comment->author);
        self::assertSame('après', $comment->content);
        self::assertNotNull($comment->updatedAt);
    }

    public function testDeleteRemovesComment(): void
    {
        $id = $this->repository->add(self::PRODUCT_ID, 'Noah', 'à supprimer');

        $this->repository->delete($id);

        self::assertNull($this->repository->find($id));
    }

    public function testFindUnknownIdReturnsNull(): void
    {
        self::assertNull($this->repository->find(987654321));
    }

    public function testSpecialCharactersAreStoredVerbatim(): void
    {
        $content = "l'été'; DROP TABLE comments; --";
        $id = $this->repository->add(self::PRODUCT_ID, 'O\'Brien', $content);

        $comment = $this->repository->find($id);

        self::assertNotNull($comment);
        self::assertSame('O\'Brien', $comment->author);
        self::assertSame($content, $comment->content);
    }
}
