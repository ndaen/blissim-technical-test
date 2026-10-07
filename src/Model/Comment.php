<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final readonly class Comment
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $author,
        public string $content,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['product_id'],
            (string) $row['author'],
            (string) $row['content'],
            new DateTimeImmutable((string) $row['created_at']),
            $row['updated_at'] === null ? null : new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
