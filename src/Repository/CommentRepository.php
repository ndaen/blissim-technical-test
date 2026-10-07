<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Comment;

final class CommentRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return list<Comment>
     */
    public function findByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, product_id, author, content, created_at, updated_at
             FROM comments
             WHERE product_id = :product_id
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['product_id' => $productId]);

        return array_map(Comment::fromRow(...), $stmt->fetchAll());
    }

    public function find(int $id): ?Comment
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, product_id, author, content, created_at, updated_at FROM comments WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Comment::fromRow($row) : null;
    }

    public function add(int $productId, string $author, string $content): int
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare(
            'INSERT INTO comments (product_id, author, content) VALUES (:product_id, :author, :content)'
        );
        $stmt->execute(['product_id' => $productId, 'author' => $author, 'content' => $content]);

        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, string $author, string $content): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE comments SET author = :author, content = :content WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'author' => $author, 'content' => $content]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
