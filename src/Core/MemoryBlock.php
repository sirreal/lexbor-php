<?php

declare(strict_types=1);

namespace Lexbor\Core;

final class MemoryBlock
{
    public function __construct(
        private readonly MemoryChunk $chunk,
        private readonly int $offset,
        private readonly int $length,
    ) {
    }

    public function chunk(): MemoryChunk
    {
        return $this->chunk;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function byteAt(int $index): ?int
    {
        if ($index < 0 || $index >= $this->length) {
            return null;
        }

        return $this->chunk->byteAt($this->offset + $index);
    }

    public function writeZeroes(int $length): void
    {
        $length = min($length, $this->length);

        if ($length <= 0) {
            return;
        }

        $this->chunk->clearBytes($this->offset, $length);
    }
}
