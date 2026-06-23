<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\Status;

final readonly class BufferEncodeResult
{
    public function __construct(
        public Status $status,
        public string $bytes,
    ) {
    }
}
