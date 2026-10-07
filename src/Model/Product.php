<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Product
{
    public function __construct(
        public int    $id,
        public string $title,
        public string $description,
        public float  $price,
        public string $thumbnail,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['id'],
            (string)$data['title'],
            (string)$data['description'],
            (float)$data['price'],
            (string)$data['thumbnail']
        );
    }
}
