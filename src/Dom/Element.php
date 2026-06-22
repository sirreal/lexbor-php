<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class Element extends Node
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $tagName,
        public array $attributes = [],
        public ?int $tagId = null,
        ?object $ownerDocument = null,
    ) {
        parent::__construct(NodeType::Element, $ownerDocument, $tagId);
    }

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function setAttribute(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }
}
