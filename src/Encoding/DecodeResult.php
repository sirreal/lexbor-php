<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\Status;

final readonly class DecodeResult
{
    /**
     * @param list<int> $codePoints
     */
    public function __construct(
        public Status $status,
        public array $codePoints,
        public int $offset,
        public ?int $pendingLeadByte = null,
        public ?int $pendingSurrogate = null,
    ) {
    }
}
