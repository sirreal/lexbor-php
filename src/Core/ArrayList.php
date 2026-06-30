<?php

declare(strict_types=1);

namespace Lexbor\Core;

final class ArrayList
{
    /**
     * @var list<mixed>
     */
    private array $items = [];

    private int $size = 0;

    private int $length = 0;

    public static function create(): self
    {
        return new self();
    }

    public static function init(?self $array, int $size): Status
    {
        if ($array === null) {
            return Status::ErrorObjectIsNull;
        }

        if ($size <= 0) {
            return Status::ErrorTooSmallSize;
        }

        $array->items = [];
        $array->length = 0;
        $array->size = $size;

        return Status::Ok;
    }

    public static function destroy(?self $array, bool $selfDestroy): ?self
    {
        if ($array === null) {
            return null;
        }

        $array->items = [];
        $array->length = 0;
        $array->size = 0;

        return $selfDestroy ? null : $array;
    }

    public function clean(): void
    {
        $this->length = 0;
    }

    /**
     * @return list<mixed>|null
     */
    public function expand(int $upTo): ?array
    {
        if ($upTo < 0) {
            return null;
        }

        $this->size = $this->length + $upTo;

        return $this->items;
    }

    public function push(mixed $value): Status
    {
        if ($this->length >= $this->size && $this->expand(128) === null) {
            return Status::ErrorMemoryAllocation;
        }

        $this->items[$this->length] = $value;
        $this->length++;

        return Status::Ok;
    }

    public function pop(): mixed
    {
        if ($this->length === 0) {
            return null;
        }

        $this->length--;
        $value = $this->items[$this->length] ?? null;

        return $value;
    }

    public function insert(int $idx, mixed $value): Status
    {
        if ($idx < 0) {
            return Status::ErrorWrongArgs;
        }

        if ($idx >= $this->length) {
            $upTo = ($idx - $this->length) + 1;

            if ($idx >= $this->size && $this->expand($upTo) === null) {
                return Status::ErrorMemoryAllocation;
            }

            $this->fillTo($idx);
            $this->items[$idx] = $value;
            $this->length += $upTo;

            return Status::Ok;
        }

        if ($this->length >= $this->size && $this->expand(32) === null) {
            return Status::ErrorMemoryAllocation;
        }

        array_splice($this->items, $idx, 0, [$value]);
        $this->length++;

        return Status::Ok;
    }

    public function set(int $idx, mixed $value): Status
    {
        if ($idx < 0) {
            return Status::ErrorWrongArgs;
        }

        if ($idx >= $this->length) {
            $upTo = ($idx - $this->length) + 1;

            if ($idx >= $this->size && $this->expand($upTo) === null) {
                return Status::ErrorMemoryAllocation;
            }

            $this->fillTo($idx);
            $this->length += $upTo;
        }

        $this->items[$idx] = $value;

        return Status::Ok;
    }

    public function delete(int $begin, int $length): void
    {
        if ($begin < 0 || $length <= 0 || $begin >= $this->length) {
            return;
        }

        $endLength = $begin + $length;

        if ($endLength >= $this->length) {
            array_splice($this->items, $begin);
            $this->length = $begin;
            return;
        }

        array_splice($this->items, $begin, $length);
        $this->length -= $length;
    }

    public function get(int $idx): mixed
    {
        if ($idx < 0 || $idx >= $this->length) {
            return null;
        }

        return $this->items[$idx] ?? null;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function size(): int
    {
        return $this->size;
    }

    private function fillTo(int $idx): void
    {
        for ($i = $this->length; $i <= $idx; $i++) {
            $this->items[$i] = null;
        }
    }
}
