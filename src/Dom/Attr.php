<?php

declare(strict_types=1);

namespace Lexbor\Dom;

use Lexbor\Core\Status;

final class Attr extends Node
{
    public function __construct(
        private ?Element $ownerElement,
        public readonly string $name,
        public string $value,
    ) {
        parent::__construct(NodeType::Attribute, $ownerElement?->ownerDocument);
    }

    public function setValue(string $value): Status
    {
        $this->value = $value;

        if ($this->ownerElement !== null) {
            $this->ownerElement->setAttributeFromAttr($this, $value);
        }

        return Status::Ok;
    }

    public function detach(): void
    {
        $this->ownerElement = null;
    }
}
