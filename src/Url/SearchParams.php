<?php

declare(strict_types=1);

namespace Lexbor\Url;

use Countable;

final class SearchParams implements Countable
{
    /**
     * @var list<array{name: string, value: string}>
     */
    private array $entries = [];

    public function __construct(?string $query = null)
    {
        if ($query === null || $query === '') {
            return;
        }

        if (str_starts_with($query, '?')) {
            $query = substr($query, 1);
        }

        if ($query === '') {
            return;
        }

        $parts = explode('&', $query);

        if ($parts[count($parts) - 1] === '') {
            array_pop($parts);
        }

        foreach ($parts as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $this->entries[] = [
                'name' => $this->decode($name),
                'value' => $this->decode($value),
            ];
        }
    }

    public function append(string $name, string $value): void
    {
        $this->entries[] = ['name' => $name, 'value' => $value];
    }

    public function delete(string $name, ?string $value = null): void
    {
        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => $entry['name'] !== $name
                || ($value !== null && $entry['value'] !== $value),
        ));
    }

    public function get(string $name): ?string
    {
        foreach ($this->entries as $entry) {
            if ($entry['name'] === $name) {
                return $entry['value'];
            }
        }

        return null;
    }

    public function has(string $name, ?string $value = null): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['name'] !== $name) {
                continue;
            }

            if ($value === null || $entry['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    public function set(string $name, string $value): void
    {
        $found = false;
        $entries = [];

        foreach ($this->entries as $entry) {
            if ($entry['name'] !== $name) {
                $entries[] = $entry;
                continue;
            }

            if (! $found) {
                $entries[] = ['name' => $name, 'value' => $value];
                $found = true;
            }
        }

        if (! $found) {
            $entries[] = ['name' => $name, 'value' => $value];
        }

        $this->entries = $entries;
    }

    public function sort(): void
    {
        $indexed = [];

        foreach ($this->entries as $index => $entry) {
            $indexed[] = ['index' => $index, 'entry' => $entry];
        }

        usort(
            $indexed,
            fn (array $left, array $right): int => $this->compareCString($left['entry']['name'], $right['entry']['name'])
                ?: $left['index'] <=> $right['index'],
        );

        $this->entries = array_map(static fn (array $item): array => $item['entry'], $indexed);
    }

    public function serialize(): string
    {
        $serialized = [];

        foreach ($this->entries as $entry) {
            $serialized[] = $this->encode($entry['name']) . '=' . $this->encode($entry['value']);
        }

        return implode('&', $serialized);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    private function encode(string $value): string
    {
        $encoded = '';
        $length = strlen($value);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($value[$offset]);

            if ($byte === 0x20) {
                $encoded .= '+';
                continue;
            }

            if ($this->isFormByteAllowed($byte)) {
                $encoded .= $value[$offset];
                continue;
            }

            $encoded .= sprintf('%%%02X', $byte);
        }

        return $encoded;
    }

    private function compareCString(string $left, string $right): int
    {
        return strcmp($this->beforeNull($left), $this->beforeNull($right));
    }

    private function decode(string $value): string
    {
        return rawurldecode(str_replace('+', ' ', $value));
    }

    private function isFormByteAllowed(int $byte): bool
    {
        return ($byte >= 0x30 && $byte <= 0x39)
            || ($byte >= 0x41 && $byte <= 0x5A)
            || ($byte >= 0x61 && $byte <= 0x7A)
            || in_array($byte, [0x2A, 0x2D, 0x2E, 0x5F], true);
    }

    private function beforeNull(string $value): string
    {
        $position = strpos($value, "\0");

        if ($position === false) {
            return $value;
        }

        return substr($value, 0, $position);
    }
}
