<?php

declare(strict_types=1);

namespace Lexbor\Core;

use stdClass;

final class ObjectArray
{
    /**
     * @var list<stdClass>
     */
    private array $items = [];

    private int $size = 0;

    private int $length = 0;

    private int $structSize = 0;

    public static function create(): self
    {
        return new self();
    }

    public static function init(?self $array, int $size, int $structSize): Status
    {
        if ($array === null) {
            return Status::ErrorObjectIsNull;
        }

        if ($size <= 0 || $structSize <= 0) {
            return Status::ErrorTooSmallSize;
        }

        $array->items = [];
        $array->length = 0;
        $array->size = $size;
        $array->structSize = $structSize;

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

    public function erase(): void
    {
        $this->items = [];
        $this->length = 0;
        $this->size = 0;
        $this->structSize = 0;
    }

    /**
     * @return list<stdClass>|null
     */
    public function expand(int $upTo): ?array
    {
        if ($upTo < 0) {
            return null;
        }

        $this->size = $this->length + $upTo;

        return $this->items;
    }

    public function push(): ?stdClass
    {
        if ($this->length >= $this->size && $this->expand(128) === null) {
            return null;
        }

        $entry = $this->slotAt($this->length);
        $this->clearSlot($entry);

        $this->length++;

        return $entry;
    }

    public function pushWithoutClear(): ?stdClass
    {
        if ($this->length >= $this->size && $this->expand(128) === null) {
            return null;
        }

        $entry = $this->slotAt($this->length);
        $this->length++;

        return $entry;
    }

    public function pushN(int $count): ?stdClass
    {
        if ($count < 0) {
            return null;
        }

        if (($this->length + $count) > $this->size && $this->expand($count + 128) === null) {
            return null;
        }

        $first = $this->slotAt($this->length);

        if ($count === 0) {
            return $first;
        }

        for ($i = 0; $i < $count; $i++) {
            $this->slotAt($this->length + $i);
        }

        $this->length += $count;

        return $first;
    }

    public function pop(): ?stdClass
    {
        if ($this->length === 0) {
            return null;
        }

        $this->length--;

        return $this->slotAt($this->length);
    }

    public function delete(int $begin, int $length): void
    {
        if ($begin < 0 || $length <= 0 || $begin >= $this->length) {
            return;
        }

        $endLength = $begin + $length;

        if ($endLength >= $this->length) {
            $this->length = $begin;
            return;
        }

        $moveLength = $this->length - $endLength;

        for ($i = 0; $i < $moveLength; $i++) {
            $this->copySlot($this->slotAt($begin + $i), $this->slotAt($endLength + $i));
        }

        $this->length -= $length;
    }

    public function get(int $idx): ?stdClass
    {
        if ($idx < 0 || $idx >= $this->length) {
            return null;
        }

        return $this->items[$idx] ?? null;
    }

    public function last(): ?stdClass
    {
        if ($this->length === 0) {
            return null;
        }

        return $this->items[$this->length - 1] ?? null;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function structSize(): int
    {
        return $this->structSize;
    }

    private function slotAt(int $idx): stdClass
    {
        return $this->items[$idx] ??= new stdClass();
    }

    private function clearSlot(stdClass $slot): void
    {
        foreach (array_keys(get_object_vars($slot)) as $name) {
            unset($slot->{$name});
        }
    }

    private function copySlot(stdClass $destination, stdClass $source): void
    {
        $this->clearSlot($destination);

        foreach (get_object_vars($source) as $name => $value) {
            $destination->{$name} = $value;
        }
    }
}
