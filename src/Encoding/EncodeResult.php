<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

final readonly class EncodeResult
{
    public function __construct(
        public int $status,
        public string $bytes,
    ) {
    }
}
