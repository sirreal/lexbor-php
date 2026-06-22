<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\DocumentFragment;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\NodeType;
use Lexbor\Dom\Text;

final class Document extends Node
{
    private Element $body;
    private TagRegistry $tags;
    private bool $quirksMode = false;
    private bool $scripting = false;

    public function __construct()
    {
        parent::__construct(NodeType::Document, $this, Tag::DOCUMENT);

        $this->tags = new TagRegistry();
        $this->body = new Element('body', tagId: Tag::BODY, ownerDocument: $this);
        $this->appendChild($this->body);
    }

    public function parse(string $html): Status
    {
        $this->quirksMode = !$this->startsWithHtmlDoctype($html);

        while ($this->body->firstChild !== null) {
            $this->body->firstChild->remove();
        }

        $this->body->clearAttributes();

        $bodyFragment = $this->bodyFragment($html);
        if ($bodyFragment !== null) {
            foreach ($this->parseAttributes($bodyFragment['attributes']) as $name => $value) {
                $this->body->setAttribute($name, $value);
            }

            $html = $bodyFragment['content'];
        }

        $this->parseFragmentInto($this->body, $html);

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

    public function createTextNode(string $data): Text
    {
        return new Text($data, $this, Tag::TEXT);
    }

    public function createInterfaceByTagId(int $tagId): Node
    {
        return InterfaceFactory::create($this, $tagId);
    }

    public function tags(): TagRegistry
    {
        return $this->tags;
    }

    public function isQuirksMode(): bool
    {
        return $this->quirksMode;
    }

    public function isScriptingEnabled(): bool
    {
        return $this->scripting;
    }

    public function setScriptingEnabled(bool $scripting): void
    {
        $this->scripting = $scripting;
    }

    private function parseFragmentInto(Node $root, string $html): void
    {
        $stack = [$root];
        $pattern = '~<\s*(/)?\s*([A-Za-z][A-Za-z0-9:-]*)((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)>~s';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $tagName = strtolower($match[2]);

                if ($match[1] === '/') {
                    $this->closeElement($stack, $tagName);
                    continue;
                }

                $attributeSource = rtrim($match[3]);
                $selfClosing = str_ends_with($attributeSource, '/');
                if ($selfClosing) {
                    $attributeSource = rtrim(substr($attributeSource, 0, -1));
                }

                $element = $this->createElement($tagName);
                foreach ($this->parseAttributes($attributeSource) as $name => $value) {
                    $element->setAttribute($name, $value);
                }

                $stack[count($stack) - 1]->appendChild($element);

                if (!$selfClosing && !$this->isVoidElement($tagName)) {
                    $stack[] = $element;
                }
            }
        }
    }

    /**
     * @param list<Node> $stack
     */
    private function closeElement(array &$stack, string $tagName): void
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];

            if ($node instanceof Element && $node->tagName === $tagName) {
                array_splice($stack, $index);
                return;
            }
        }
    }

    /**
     * @return array{attributes: string, content: string}|null
     */
    private function bodyFragment(string $html): ?array
    {
        $pattern = '~<\s*body\b((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)>~si';

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $closePattern = '~<\s*/\s*body\s*>~i';

        if (preg_match($closePattern, $html, $close, PREG_OFFSET_CAPTURE, $start) !== 1) {
            return [
                'attributes' => $match[1][0],
                'content' => substr($html, $start),
            ];
        }

        return [
            'attributes' => $match[1][0],
            'content' => substr($html, $start, $close[0][1] - $start),
        ];
    }

    private function startsWithHtmlDoctype(string $html): bool
    {
        return preg_match('~^\s*<!doctype\s+html\s*>~i', $html) === 1;
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

    private function isVoidElement(string $tagName): bool
    {
        return in_array($tagName, [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'source',
            'track',
            'wbr',
        ], true);
    }
}
