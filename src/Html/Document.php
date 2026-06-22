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

    public function __construct()
    {
        parent::__construct(NodeType::Document, $this);

        $this->body = new Element('body', ownerDocument: $this);
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
        return new Element(strtolower($tagName), ownerDocument: $this);
    }

    public function createDocumentFragment(): DocumentFragment
    {
        return new DocumentFragment($this);
    }

    /**
     * @return list<Element>
     */
    private function parseTopLevelElements(string $html): array
    {
        $elements = [];
        $pattern = '~<([A-Za-z][A-Za-z0-9:-]*)([^>]*)>\s*</\1>~';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $elements[] = new Element(
                    strtolower($match[1]),
                    $this->parseAttributes($match[2]),
                    $this,
                );
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
        $pattern = '#([A-Za-z_:][A-Za-z0-9_:.~-]*)\s*=\s*([\'"])(.*?)\2#';

        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $attributes[strtolower($match[1])] = $match[3];
            }
        }

        return $attributes;
    }
}
