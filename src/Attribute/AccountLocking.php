<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class AccountLocking
{
    public function __construct(
        public bool $enabled = true
    ) {}
}