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
        public ?int $pendingUtf8CodePoint = null,
        public int $pendingUtf8Need = 0,
        public int $pendingUtf8Lower = 0,
        public int $pendingUtf8Upper = 0,
        public ?int $pendingFirstCodePoint = null,
        public ?int $pendingSecondCodePoint = null,
        public bool $pendingJis0212 = false,
        public ?int $pendingGb18030Second = null,
        public ?int $pendingGb18030Third = null,
        public bool $pendingGb18030Prepend = false,
        public int $pendingIso2022JpState = 0,
        public int $pendingIso2022JpOutState = 0,
        public bool $pendingIso2022JpOutFlag = false,
        public ?int $pendingIso2022JpLead = null,
        public ?int $pendingIso2022JpPrepend = null,
    ) {
    }
}
