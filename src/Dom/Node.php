<?php

declare(strict_types=1);

namespace Lexbor\Dom;

use Lexbor\Core\Status;

class Node
{
    public ?Node $next = null;
    public ?Node $prev = null;
    public ?Node $parent = null;
    public ?Node $firstChild = null;
    public ?Node $lastChild = null;

    public function __construct(
        public readonly NodeType $type,
        public ?object $ownerDocument = null,
        public ?int $localName = null,
    ) {
    }

    public function appendChild(Node $node): ExceptionCode
    {
        return $this->preInsert($node, null);
    }

    public function insertBeforeSpec(Node $node, Node $child): ExceptionCode
    {
        return $this->preInsert($node, $child);
    }

    public function insertAfter(Node $node): ExceptionCode
    {
        if ($this->parent === null) {
            return ExceptionCode::NotFoundError;
        }

        return $this->parent->preInsert($node, $this->next);
    }

    public function removeChild(Node $child): ExceptionCode
    {
        if ($child->parent !== $this) {
            return ExceptionCode::NotFoundError;
        }

        $child->remove();

        return ExceptionCode::Ok;
    }

    public function replaceChild(Node $node, Node $child): ExceptionCode
    {
        $code = $this->preInsertValidity($node, $child);
        if ($code !== ExceptionCode::Ok) {
            return $code;
        }

        $reference = $child->next;
        if ($reference === $node) {
            $reference = $node->next;
        }

        $child->remove();

        if ($node->type !== NodeType::DocumentFragment) {
            return $this->insert($node, $reference);
        }

        $current = $node->firstChild;
        while ($current !== null) {
            $next = $current->next;
            $code = $this->insert($current, $reference);
            if ($code !== ExceptionCode::Ok) {
                return $code;
            }

            $current = $next;
        }

        return ExceptionCode::Ok;
    }

    public function cloneNode(bool $deep = false, ?object $ownerDocument = null): Node
    {
        $clone = $this->cloneSingle($ownerDocument ?? $this->ownerDocument);

        if (!$deep) {
            return $clone;
        }

        for ($child = $this->firstChild; $child !== null; $child = $child->next) {
            $clone->appendChild($child->cloneNode(true, $ownerDocument ?? $this->ownerDocument));
        }

        return $clone;
    }

    public function remove(): void
    {
        if ($this->parent !== null) {
            if ($this->parent->firstChild === $this) {
                $this->parent->firstChild = $this->next;
            }

            if ($this->parent->lastChild === $this) {
                $this->parent->lastChild = $this->prev;
            }
        }

        if ($this->next !== null) {
            $this->next->prev = $this->prev;
        }

        if ($this->prev !== null) {
            $this->prev->next = $this->next;
        }

        $this->parent = null;
        $this->next = null;
        $this->prev = null;
    }

    public function byId(string $id): ?Element
    {
        for ($node = $this->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element && $node->getAttribute('id') === $id) {
                return $node;
            }

            $found = $node->byId($id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<Element>
     */
    public function elementsByTagName(string $tagName): array
    {
        $matches = [];
        $normalized = strtolower($tagName);

        for ($node = $this->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element && ($normalized === '*' || $node->tagName === $normalized)) {
                $matches[] = $node;
            }

            array_push($matches, ...$node->elementsByTagName($normalized));
        }

        return $matches;
    }

    public function collectElementsByClassName(Collection $collection, ?string $className): Status
    {
        if ($className === null || $className === '') {
            return Status::Ok;
        }

        $caseInsensitive = $this->ownerDocumentIsQuirksMode();

        $this->walkDescendantElements(static function (Element $element) use ($collection, $className, $caseInsensitive): void {
            $class = $element->getAttribute('class');

            if ($class !== null && self::classAttributeContains($class, $className, $caseInsensitive)) {
                $collection->append($element);
            }
        });

        return Status::Ok;
    }

    public function collectElementsByAttr(
        Collection $collection,
        string $qualifiedName,
        ?string $value,
        bool $caseInsensitive = false,
    ): Status {
        $name = strtolower($qualifiedName);
        $expected = $value ?? '';

        $this->walkDescendantElements(
            static function (Element $element) use ($collection, $name, $expected, $caseInsensitive): void {
                $actual = $element->getAttribute($name);

                if ($actual === null) {
                    return;
                }

                if ($expected === '' && $actual === '') {
                    $collection->append($element);
                    return;
                }

                $matches = $caseInsensitive
                    ? strcasecmp($actual, $expected) === 0
                    : $actual === $expected;

                if ($matches) {
                    $collection->append($element);
                }
            },
        );

        return Status::Ok;
    }

    /**
     * @return list<Node>
     */
    public function childNodes(): array
    {
        $nodes = [];

        for ($node = $this->firstChild; $node !== null; $node = $node->next) {
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param callable(Element): void $callback
     */
    private function walkDescendantElements(callable $callback): void
    {
        for ($node = $this->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                $callback($node);
            }

            $node->walkDescendantElements($callback);
        }
    }

    private static function classAttributeContains(string $classAttribute, string $className, bool $caseInsensitive): bool
    {
        foreach (preg_split('/[\t\n\f\r ]+/', $classAttribute, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if ($caseInsensitive ? strcasecmp($token, $className) === 0 : $token === $className) {
                return true;
            }
        }

        return false;
    }

    private function ownerDocumentIsQuirksMode(): bool
    {
        $document = $this->ownerDocument;

        return is_object($document) && method_exists($document, 'isQuirksMode') && $document->isQuirksMode();
    }

    private function preInsert(Node $node, ?Node $child): ExceptionCode
    {
        $code = $this->preInsertValidity($node, $child);
        if ($code !== ExceptionCode::Ok) {
            return $code;
        }

        if ($child === $node) {
            $child = $node->next;
        }

        return $this->insert($node, $child);
    }

    private function insert(Node $node, ?Node $child): ExceptionCode
    {
        if ($node->type === NodeType::DocumentFragment && $node->firstChild === null) {
            return ExceptionCode::Ok;
        }

        if ($node->type !== NodeType::DocumentFragment) {
            $this->insertSingleNode($node, $child);
            return ExceptionCode::Ok;
        }

        $current = $node->firstChild;
        while ($current !== null) {
            $next = $current->next;
            $this->insertSingleNode($current, $child);
            $current = $next;
        }

        return ExceptionCode::Ok;
    }

    private function insertSingleNode(Node $node, ?Node $child): void
    {
        $node->remove();

        if ($child === null) {
            $this->insertChildWithoutEvents($node);
            return;
        }

        $this->insertBeforeWithoutEvents($node, $child);
    }

    private function insertChildWithoutEvents(Node $node): void
    {
        if ($this->lastChild !== null) {
            $this->lastChild->next = $node;
        } else {
            $this->firstChild = $node;
        }

        $node->parent = $this;
        $node->next = null;
        $node->prev = $this->lastChild;

        $this->lastChild = $node;
    }

    private function insertBeforeWithoutEvents(Node $node, Node $child): void
    {
        if ($child->parent !== $this) {
            return;
        }

        if ($child->prev !== null) {
            $child->prev->next = $node;
        } else {
            $this->firstChild = $node;
        }

        $node->parent = $this;
        $node->next = $child;
        $node->prev = $child->prev;

        $child->prev = $node;
    }

    private function preInsertValidity(Node $node, ?Node $child): ExceptionCode
    {
        if (!in_array($this->type, [NodeType::Element, NodeType::Document, NodeType::DocumentFragment], true)) {
            return ExceptionCode::HierarchyRequestError;
        }

        if ($this->hasInclusiveAncestor($node)) {
            return ExceptionCode::HierarchyRequestError;
        }

        if ($child !== null && $child->parent !== $this) {
            return ExceptionCode::NotFoundError;
        }

        if (!in_array($node->type, [
            NodeType::DocumentFragment,
            NodeType::DocumentType,
            NodeType::Element,
            NodeType::CharacterData,
            NodeType::Text,
            NodeType::CDataSection,
            NodeType::ProcessingInstruction,
            NodeType::Comment,
        ], true)) {
            return ExceptionCode::HierarchyRequestError;
        }

        if (
            ($node->type === NodeType::Text && $this->type === NodeType::Document)
            || ($node->type === NodeType::DocumentType && $this->type !== NodeType::Document)
        ) {
            return ExceptionCode::HierarchyRequestError;
        }

        if ($this->type !== NodeType::Document) {
            return ExceptionCode::Ok;
        }

        return $this->validateDocumentInsertion($node, $child);
    }

    private function validateDocumentInsertion(Node $node, ?Node $child): ExceptionCode
    {
        if ($node->type === NodeType::DocumentFragment) {
            $elementCount = 0;

            for ($current = $node->firstChild; $current !== null; $current = $current->next) {
                if ($current->type === NodeType::Text) {
                    return ExceptionCode::HierarchyRequestError;
                }

                if ($current->type === NodeType::Element && ++$elementCount > 1) {
                    return ExceptionCode::HierarchyRequestError;
                }
            }

            if ($elementCount !== 1) {
                return ExceptionCode::Ok;
            }
        }

        if ($node->type === NodeType::Element || $node->type === NodeType::DocumentFragment) {
            for ($current = $this->firstChild; $current !== null; $current = $current->next) {
                if ($current->type === NodeType::Element && $current !== $child) {
                    return ExceptionCode::HierarchyRequestError;
                }
            }

            if ($child !== null && $child->type === NodeType::DocumentType) {
                return ExceptionCode::HierarchyRequestError;
            }

            for ($current = $child?->next; $current !== null; $current = $current->next) {
                if ($current->type === NodeType::DocumentType) {
                    return ExceptionCode::HierarchyRequestError;
                }
            }
        }

        if ($node->type === NodeType::DocumentType) {
            for ($current = $this->firstChild; $current !== null; $current = $current->next) {
                if ($current->type === NodeType::DocumentType && $current !== $child) {
                    return ExceptionCode::HierarchyRequestError;
                }

                if ($current->type === NodeType::Element && $child === null) {
                    return ExceptionCode::HierarchyRequestError;
                }
            }

            for ($current = $child?->prev; $current !== null; $current = $current->prev) {
                if ($current->type === NodeType::Element) {
                    return ExceptionCode::HierarchyRequestError;
                }
            }
        }

        return ExceptionCode::Ok;
    }

    private function hasInclusiveAncestor(Node $ancestor): bool
    {
        for ($current = $this; $current !== null; $current = $current->parent) {
            if ($current === $ancestor) {
                return true;
            }
        }

        return false;
    }

    private function cloneSingle(?object $ownerDocument): Node
    {
        if ($this instanceof Element) {
            $clone = new Element($this->tagName, tagId: $this->tagId, ownerDocument: $ownerDocument);

            foreach ($this->attributes as $name => $value) {
                $clone->setAttribute($name, $value);
            }

            return $clone;
        }

        if ($this instanceof Text) {
            return new Text($this->data, $ownerDocument, $this->localName);
        }

        if ($this instanceof Comment) {
            return new Comment($this->data, $ownerDocument, $this->localName);
        }

        if ($this instanceof DocumentType) {
            return new DocumentType($this->name(), $this->publicId(), $this->systemId(), $ownerDocument, $this->localName);
        }

        if ($this instanceof DocumentFragment) {
            return new DocumentFragment($ownerDocument);
        }

        return new Node($this->type, $ownerDocument, $this->localName);
    }
}
