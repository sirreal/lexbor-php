<?php

declare(strict_types=1);

namespace Lexbor\Core;

use RuntimeException;

final class LexborException extends RuntimeException
{
    public function __construct(
        public readonly Status $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}

