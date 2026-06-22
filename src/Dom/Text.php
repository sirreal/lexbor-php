<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class Text extends Node
{
    public function __construct(
        public string $data,
        ?object $ownerDocument = null,
        ?int $localName = null,
    ) {
        parent::__construct(NodeType::Text, $ownerDocument, $localName);
    }
}
