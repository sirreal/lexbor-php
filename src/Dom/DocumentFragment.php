<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class DocumentFragment extends Node
{
    public function __construct(?object $ownerDocument = null)
    {
        parent::__construct(NodeType::DocumentFragment, $ownerDocument);
    }
}

