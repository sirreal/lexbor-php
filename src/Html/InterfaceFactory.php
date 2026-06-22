<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\NodeType;
use Lexbor\Dom\Text;

final class InterfaceFactory
{
    public static function create(Document $document, int $tagId): Node
    {
        return match ($tagId) {
            Tag::EM_COMMENT => new Node(NodeType::Comment, $document, $tagId),
            Tag::DOCUMENT => new Node(NodeType::Document, $document, $tagId),
            Tag::EM_DOCTYPE => new Node(NodeType::DocumentType, $document, $tagId),
            Tag::TEXT => new Text('', $document, $tagId),
            default => new Element(TagRegistry::nameById($tagId) ?? '#undef', tagId: $tagId, ownerDocument: $document),
        };
    }
}
