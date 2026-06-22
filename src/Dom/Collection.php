<?php

declare(strict_types=1);

namespace Lexbor\Dom;

use Lexbor\Core\Status;

final class Collection
{
    /**
     * @var list<Node>
     */
    private array $nodes = [];

    public function append(Node $node): Status
    {
        $this->nodes[] = $node;

        return Status::Ok;
    }

    public function length(): int
    {
        return count($this->nodes);
    }

    public function item(int $index): ?Node
    {
        return $this->nodes[$index] ?? null;
    }

    /**
     * @return list<Node>
     */
    public function all(): array
    {
        return $this->nodes;
    }

    public function clear(): void
    {
        $this->nodes = [];
    }
}
