<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class Element extends Node
{
    public const string NAMESPACE_HTML = 'html';
    public const string NAMESPACE_MATH = 'math';
    public const string NAMESPACE_SVG = 'svg';

    /**
     * @var array<string, Attr>
     */
    private array $attributeNodes = [];

    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $tagName,
        public array $attributes = [],
        public ?int $tagId = null,
        ?object $ownerDocument = null,
        public readonly string $namespace = self::NAMESPACE_HTML,
    ) {
        parent::__construct(NodeType::Element, $ownerDocument, $tagId);
    }

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[strtolower($name)] ?? null;
    }

    public function setAttribute(string $name, string $value): Attr
    {
        $normalized = strtolower($name);
        $this->attributes[$normalized] = $value;

        if (isset($this->attributeNodes[$normalized])) {
            $this->attributeNodes[$normalized]->value = $value;
            return $this->attributeNodes[$normalized];
        }

        return $this->attributeNodes[$normalized] = new Attr($this, $normalized, $value);
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->attributes);
    }

    public function attrByName(string $name): ?Attr
    {
        $normalized = strtolower($name);

        if (!array_key_exists($normalized, $this->attributes)) {
            return null;
        }

        if (!isset($this->attributeNodes[$normalized])) {
            $this->attributeNodes[$normalized] = new Attr($this, $normalized, $this->attributes[$normalized]);
        }

        return $this->attributeNodes[$normalized];
    }

    public function removeAttribute(string $name): void
    {
        $normalized = strtolower($name);

        if (isset($this->attributeNodes[$normalized])) {
            $this->attributeNodes[$normalized]->detach();
            unset($this->attributeNodes[$normalized]);
        }

        unset($this->attributes[$normalized]);
    }

    public function clearAttributes(): void
    {
        foreach (array_keys($this->attributes) as $name) {
            $this->removeAttribute((string) $name);
        }
    }

    public function setInnerHtml(string $html): self
    {
        while ($this->firstChild !== null) {
            $this->firstChild->remove();
        }

        $document = $this->ownerDocument;

        if (is_object($document) && method_exists($document, 'createFragmentForElement')) {
            $fragment = $document->createFragmentForElement($this, $html);

            while ($fragment->firstChild !== null) {
                $this->appendChild($fragment->firstChild);
            }

            return $this;
        }

        if ($html !== '') {
            $this->appendChild(new Text($html, $this->ownerDocument));
        }

        return $this;
    }

    public function setAttributeFromAttr(Attr $attr, string $value): void
    {
        if (($this->attributeNodes[$attr->name] ?? null) !== $attr) {
            return;
        }

        $this->attributes[$attr->name] = $value;
    }
}
