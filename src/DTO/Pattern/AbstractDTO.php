<?php

declare(strict_types=1);

namespace App\DTO\Pattern;

abstract class AbstractDTO
{
    abstract public function toEntity(): object;

    abstract public static function fromEntity(object $entity): self;

    abstract public static function getEntityClass(): string;
}