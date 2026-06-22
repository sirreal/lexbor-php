<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\DocumentFragment;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\NodeType;

final class Document extends Node
{
    private Element $body;
    private TagRegistry $tags;

    public function __construct()
    {
        parent::__construct(NodeType::Document, $this, Tag::DOCUMENT);

        $this->tags = new TagRegistry();
        $this->body = new Element('body', tagId: Tag::BODY, ownerDocument: $this);
        $this->appendChild($this->body);
    }

    public function parse(string $html): Status
    {
        while ($this->body->firstChild !== null) {
            $this->body->firstChild->remove();
        }

        foreach ($this->parseTopLevelElements($html) as $element) {
            $this->body->appendChild($element);
        }

        return Status::Ok;
    }

    public function bodyElement(): Element
    {
        return $this->body;
    }

    public function createElement(string $tagName): Element
    {
        $tagId = $this->tags->idForName($tagName);

        return new Element(TagRegistry::nameById($tagId) ?? strtolower($tagName), tagId: $tagId, ownerDocument: $this);
    }

    public function createDocumentFragment(): DocumentFragment
    {
        return new DocumentFragment($this);
    }

    public function createInterfaceByTagId(int $tagId): Node
    {
        return InterfaceFactory::create($this, $tagId);
    }

    public function tags(): TagRegistry
    {
        return $this->tags;
    }

    /**
     * @return list<Element>
     */
    private function parseTopLevelElements(string $html): array
    {
        $elements = [];
        $pattern = '~<([A-Za-z][A-Za-z0-9:-]*)((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)>.*?</\1>~si';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $element = $this->createElement($match[1]);
                foreach ($this->parseAttributes($match[2]) as $name => $value) {
                    $element->setAttribute($name, $value);
                }

                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $source): array
    {
        $attributes = [];
        $pattern = '#([A-Za-z_:][A-Za-z0-9_:.~-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?#';

        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL) !== false) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);

                if (!array_key_exists($name, $attributes)) {
                    $attributes[$name] = $match[2] ?? $match[3] ?? $match[4] ?? '';
                }
            }
        }

        return $attributes;
    }
}
