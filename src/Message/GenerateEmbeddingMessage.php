<?php

namespace App\Message;

final class GenerateEmbeddingMessage
{
    public function __construct(
        private readonly string $entityType,
        private readonly string $entityId,
        private readonly bool $force = false
    ) {
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function isForce(): bool
    {
        return $this->force;
    }
}