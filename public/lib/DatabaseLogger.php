<?php

declare(strict_types=1);

class DatabaseLogger
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function log(string $type, array $meta): void
    {
        $sql = 'INSERT INTO qr_requests (type, meta, created_at) VALUES (:type, :meta, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':type' => $type,
            ':meta' => json_encode($meta, JSON_THROW_ON_ERROR),
        ]);
    }
}
