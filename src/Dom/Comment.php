<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class Comment extends Node
{
    public function __construct(
        public string $data,
        ?object $ownerDocument = null,
        ?int $localName = null,
    ) {
        parent::__construct(NodeType::Comment, $ownerDocument, $localName);
    }
}
