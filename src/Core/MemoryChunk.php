<?php

declare(strict_types=1);

namespace Lexbor\Core;

final class MemoryChunk
{
    private bool $hasData = false;

    /**
     * @var array<int, int>
     */
    private array $bytes = [];

    private int $length = 0;

    private int $size = 0;

    private ?self $next = null;

    private ?self $prev = null;

    public function data(): ?string
    {
        return $this->hasData ? '' : null;
    }

    public function hasData(): bool
    {
        return $this->hasData;
    }

    public function allocatedSize(): int
    {
        return $this->hasData ? $this->size : 0;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function next(): ?self
    {
        return $this->next;
    }

    public function prev(): ?self
    {
        return $this->prev;
    }

    public function setNext(?self $next): void
    {
        $this->next = $next;
    }

    public function setPrev(?self $prev): void
    {
        $this->prev = $prev;
    }

    public function reset(int $size): void
    {
        $this->hasData = true;
        $this->bytes = [];
        $this->length = 0;
        $this->size = $size;
    }

    public function destroyData(): void
    {
        $this->hasData = false;
        $this->bytes = [];
    }

    public function setLength(int $length): void
    {
        $this->length = $length;
    }

    public function advance(int $length): int
    {
        $offset = $this->length;
        $this->length += $length;

        return $offset;
    }

    public function byteAt(int $offset): ?int
    {
        if (!$this->hasData || $offset < 0 || $offset >= $this->size) {
            return null;
        }

        return $this->bytes[$offset] ?? 0;
    }

    public function writeBytes(int $offset, string $bytes): void
    {
        if (!$this->hasData || $offset < 0 || $offset >= $this->size) {
            return;
        }

        $length = strlen($bytes);

        if ($length === 0) {
            return;
        }

        $end = min($this->size, $offset + $length);

        for ($i = $offset; $i < $end; $i++) {
            $this->bytes[$i] = ord($bytes[$i - $offset]);
        }
    }

    public function clearBytes(int $offset, int $length): void
    {
        if (!$this->hasData || $offset < 0 || $length <= 0) {
            return;
        }

        $end = min($this->size, $offset + $length);

        foreach (array_keys($this->bytes) as $idx) {
            if ($idx >= $offset && $idx < $end) {
                unset($this->bytes[$idx]);
            }
        }
    }
}
