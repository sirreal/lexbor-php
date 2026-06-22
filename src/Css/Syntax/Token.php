<?php

declare(strict_types=1);

namespace Lexbor\Css\Syntax;

final class Token
{
    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly int $length,
    ) {
    }
}
