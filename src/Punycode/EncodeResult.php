<?php

declare(strict_types=1);

namespace Lexbor\Punycode;

final readonly class EncodeResult
{
    public function __construct(
        public string $data,
        public bool $unchanged,
    ) {
    }
}

