<?php

declare(strict_types=1);

namespace Lexbor\Core;

final class Memory
{
    public const ALIGN_STEP = PHP_INT_SIZE;

    private const UNLIMITED_ALLOCATION_CEILING = 1024 * 1024 * 1024;

    private ?MemoryChunk $chunk = null;

    private ?MemoryChunk $chunkFirst = null;

    private int $chunkMinSize = 0;

    private int $chunkLength = 0;

    private int $logicalDataSize = 0;

    public static function create(): self
    {
        return new self();
    }

    public static function init(?self $memory, int $minChunkSize): Status
    {
        if ($memory === null) {
            return Status::ErrorObjectIsNull;
        }

        if ($minChunkSize <= 0) {
            return Status::ErrorWrongArgs;
        }

        $chunkMinSize = self::tryAlign($minChunkSize);

        if ($chunkMinSize === null || !self::canMaterialize($memory, $chunkMinSize)) {
            return Status::ErrorMemoryAllocation;
        }

        $memory->chunkMinSize = $chunkMinSize;
        $memory->chunk = self::chunkMake($memory, $memory->chunkMinSize);

        if ($memory->chunk === null) {
            return Status::ErrorMemoryAllocation;
        }

        $memory->chunkFirst = $memory->chunk;
        $memory->chunkLength = 1;

        return Status::Ok;
    }

    public static function destroy(?self $memory, bool $selfDestroy): ?self
    {
        if ($memory === null) {
            return null;
        }

        $chunk = $memory->chunk;

        while ($chunk !== null) {
            $prev = $chunk->prev();
            self::chunkDestroy($memory, $chunk, true);
            $chunk = $prev;
        }

        $memory->chunk = null;
        $memory->chunkFirst = null;
        $memory->chunkLength = 0;

        return $selfDestroy ? null : $memory;
    }

    public static function chunkInit(self $memory, MemoryChunk $chunk, int $length): ?MemoryBlock
    {
        $length = self::tryAlign($length);

        if ($length === null) {
            return null;
        }

        $chunkMinSize = $memory->chunkMinSize;

        if ($length > $chunkMinSize) {
            $size = $chunkMinSize > (PHP_INT_MAX - $length)
                ? $length
                : $length + $chunkMinSize;
        } else {
            $size = $chunkMinSize;
        }

        $previousSize = $chunk->allocatedSize();

        if (!self::canMaterialize($memory, $size, $previousSize)) {
            return null;
        }

        $chunk->reset($size);
        $memory->logicalDataSize += $size - $previousSize;

        return new MemoryBlock($chunk, 0, $size);
    }

    public static function chunkMake(self $memory, int $length): ?MemoryChunk
    {
        $chunk = new MemoryChunk();

        if (self::chunkInit($memory, $chunk, $length) === null) {
            return null;
        }

        return $chunk;
    }

    public static function chunkDestroy(?self $memory, ?MemoryChunk $chunk, bool $selfDestroy): ?MemoryChunk
    {
        if ($memory === null || $chunk === null) {
            return null;
        }

        $memory->logicalDataSize = max(0, $memory->logicalDataSize - $chunk->allocatedSize());
        $chunk->destroyData();

        return $selfDestroy ? null : $chunk;
    }

    public static function align(int $size): int
    {
        return self::tryAlign($size) ?? 0;
    }

    public static function alignFloor(int $size): int
    {
        if ($size <= 0) {
            return 0;
        }

        $remainder = $size % self::ALIGN_STEP;

        return $remainder === 0 ? $size : $size - $remainder;
    }

    public function clean(): void
    {
        if ($this->chunk === null || $this->chunkFirst === null) {
            return;
        }

        $chunk = $this->chunk;

        while ($chunk->prev() !== null) {
            $prev = $chunk->prev();
            $this->logicalDataSize = max(0, $this->logicalDataSize - $chunk->allocatedSize());
            $chunk->destroyData();
            $chunk = $prev;
        }

        $chunk->setNext(null);
        $chunk->setLength(0);

        $this->chunk = $this->chunkFirst;
        $this->chunkLength = 1;
    }

    public function alloc(int $length): ?MemoryBlock
    {
        if ($length <= 0 || $this->chunk === null) {
            return null;
        }

        $length = self::tryAlign($length);

        if ($length === null) {
            return null;
        }

        if (($this->chunk->length() + $length) > $this->chunk->size()) {
            $next = self::chunkMake($this, $length);

            if ($next === null) {
                return null;
            }

            $this->chunk->setNext($next);
            $next->setPrev($this->chunk);
            $this->chunk = $next;
            $this->chunkLength++;
        }

        $offset = $this->chunk->advance($length);

        return new MemoryBlock($this->chunk, $offset, $length);
    }

    public function calloc(int $length): ?MemoryBlock
    {
        $data = $this->alloc($length);

        if ($data !== null) {
            $data->writeZeroes($length);
        }

        return $data;
    }

    public function chunk(): ?MemoryChunk
    {
        return $this->chunk;
    }

    public function chunkFirst(): ?MemoryChunk
    {
        return $this->chunkFirst;
    }

    public function chunkMinSize(): int
    {
        return $this->chunkMinSize;
    }

    public function chunkLength(): int
    {
        return $this->chunkLength;
    }

    public function currentLength(): int
    {
        return $this->chunk?->length() ?? 0;
    }

    public function currentSize(): int
    {
        return $this->chunk?->size() ?? 0;
    }

    private static function tryAlign(int $size): ?int
    {
        if ($size <= 0) {
            return 0;
        }

        $remainder = $size % self::ALIGN_STEP;

        if ($remainder === 0) {
            return $size;
        }

        $delta = self::ALIGN_STEP - $remainder;

        if ($size > (PHP_INT_MAX - $delta)) {
            return null;
        }

        return $size + $delta;
    }

    private static function canMaterialize(self $memory, int $size, int $replacing = 0): bool
    {
        if ($size < 0) {
            return false;
        }

        $limit = self::memoryLimitBytes();

        $current = max(0, $memory->logicalDataSize - $replacing);

        if ($size > (PHP_INT_MAX - $current)) {
            return false;
        }

        $projected = $current + $size;

        if ($limit === null) {
            return $projected <= self::UNLIMITED_ALLOCATION_CEILING;
        }

        $headroom = max(1024 * 1024, intdiv($size, 8));

        return $projected <= max(0, $limit - memory_get_usage(true) - $headroom);
    }

    private static function memoryLimitBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $unit = strtolower($raw[strlen($raw) - 1]);
        $number = (int) $raw;
        $multiplier = match ($unit) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };

        if ($number <= 0) {
            return null;
        }

        if ($number > intdiv(PHP_INT_MAX, $multiplier)) {
            return PHP_INT_MAX;
        }

        return $number * $multiplier;
    }
}
