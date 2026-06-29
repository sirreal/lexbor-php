<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Comment;
use Lexbor\Dom\DocumentFragment;
use Lexbor\Dom\DocumentType;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\NodeType;
use Lexbor\Dom\Text;

final class Document extends Node
{
    private Element $html;
    private Element $head;
    private Element $body;
    private TagRegistry $tags;
    private bool $quirksMode = false;
    private bool $scripting = false;

    public function __construct()
    {
        parent::__construct(NodeType::Document, $this, Tag::DOCUMENT);

        $this->tags = new TagRegistry();
        $this->html = new Element('html', tagId: Tag::HTML, ownerDocument: $this);
        $this->head = new Element('head', tagId: Tag::HEAD, ownerDocument: $this);
        $this->body = new Element('body', tagId: Tag::BODY, ownerDocument: $this);
        $this->appendChild($this->body);
    }

    public function parse(string $html): Status
    {
        $html = self::normalizeTokenizedNewlines($html);
        $doctype = $this->parseLeadingDoctype($html);
        $this->quirksMode = self::doctypeRequiresQuirks($doctype);
        $this->clearDocumentPrologueNodes();
        $this->setDocumentTypeFromParsedDoctype($doctype);

        while ($this->body->firstChild !== null) {
            $this->body->firstChild->remove();
        }

        while ($this->head->firstChild !== null) {
            $this->head->firstChild->remove();
        }

        $this->head->clearAttributes();
        $this->body->clearAttributes();
        $this->html->clearAttributes();

        $html = $doctype === null ? $this->stripLeadingDoctype($html) : substr($html, $doctype['offset']);
        $html = $this->consumeDocumentPrologueComments($html, $doctype !== null);
        $html = $this->consumeDocumentHeadContent($html);
        $bodyFragment = $this->bodyFragment($html);
        if ($bodyFragment !== null) {
            foreach ($this->parseAttributes($bodyFragment['attributes']) as $attribute) {
                $this->body->setAttribute($attribute['name'], $attribute['value']);
            }

            $html = $bodyFragment['content'];
        }

        $this->parseFragmentInto($this->body, $html);

        return Status::Ok;
    }

    public function htmlElement(): Element
    {
        return $this->html;
    }

    public function headElement(): Element
    {
        return $this->head;
    }

    public function bodyElement(): Element
    {
        return $this->body;
    }

    public function documentType(): ?DocumentType
    {
        for ($child = $this->firstChild; $child !== null; $child = $child->next) {
            if ($child instanceof DocumentType) {
                return $child;
            }
        }

        return null;
    }

    public function createElement(string $tagName, string $namespace = Element::NAMESPACE_HTML): Element
    {
        $tagId = $this->tags->idForName($tagName);

        return new Element(TagRegistry::nameById($tagId) ?? strtolower($tagName), tagId: $tagId, ownerDocument: $this, namespace: $namespace);
    }

    public function createDocumentFragment(): DocumentFragment
    {
        return new DocumentFragment($this);
    }

    public function createDocumentType(string $name, ?string $publicId = null, ?string $systemId = null): ?DocumentType
    {
        if (!DocumentType::isValidName($name)) {
            return null;
        }

        return new DocumentType(
            strtolower($name),
            $publicId,
            $systemId,
            $this,
            Tag::EM_DOCTYPE,
        );
    }

    public function createFragmentForElement(Element $context, string $html): DocumentFragment
    {
        $html = self::normalizeTokenizedNewlines($html);
        $fragment = $this->createDocumentFragment();

        if ($html === '') {
            return $fragment;
        }

        if ($this->isTextOnlyFragmentContext($context)) {
            $fragment->appendChild($this->createTextNode(
                $this->textOnlyElementData($context, $html),
            ));
            return $fragment;
        }

        $this->parseFragmentInto($fragment, $html, $context);

        return $fragment;
    }

    public function createTextNode(string $data): Text
    {
        return new Text($data, $this, Tag::TEXT);
    }

    public function createComment(string $data): Comment
    {
        return new Comment($data, $this, Tag::EM_COMMENT);
    }

    public function createInterfaceByTagId(int $tagId): Node
    {
        return InterfaceFactory::create($this, $tagId);
    }

    public function importNode(Node $node, bool $deep = false): Node
    {
        return $node->cloneNode($deep, $this);
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

    private function parseFragmentInto(Node $root, string $html, ?Element $context = null): void
    {
        $stack = [$root];
        $formattingEndTagsPoppedByScope = [];
        $formElement = (
            $context !== null
            && $context->namespace === Element::NAMESPACE_HTML
            && $context->tagName === 'form'
        ) ? $context : null;
        $pattern = '~(?<comment_start><!--)|(?<empty_end_tag></>)|</(?<invalid_end_tag>[^A-Za-z>][^>]*)(?:>|\z)|<(?<bogus_comment>\?[^>]*)(?:>|\z)|<!(?!doctype)(?<bogus_declaration>[^>]*)(?:>|\z)|<(?<closing>/)?(?<tag>[A-Za-z][^\t\n\f\r />]*)~si';
        $offset = 0;

        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $offset) === 1) {
            $tagStart = $match[0][1];
            if ($tagStart > $offset) {
                $this->appendText($stack[count($stack) - 1], substr($html, $offset, $tagStart - $offset));
                $formattingEndTagsPoppedByScope = [];
            }

            $tagEnd = $tagStart + strlen($match[0][0]);
            $parent = $stack[count($stack) - 1];

            if (($match['comment_start'][1] ?? -1) !== -1) {
                $formattingEndTagsPoppedByScope = [];
                [$comment, $tagEnd] = self::consumeHtmlComment($html, $tagStart);
                $this->appendComment($parent, $comment);
                $offset = $tagEnd;
                continue;
            }

            if (($match['empty_end_tag'][1] ?? -1) !== -1) {
                $formattingEndTagsPoppedByScope = [];
                $offset = $tagEnd;
                continue;
            }

            if (($match['invalid_end_tag'][1] ?? -1) !== -1) {
                $formattingEndTagsPoppedByScope = [];
                $this->appendComment($parent, str_replace("\0", "\u{FFFD}", $match['invalid_end_tag'][0]));
                $offset = $tagEnd;
                continue;
            }

            if (($match['bogus_comment'][1] ?? -1) !== -1) {
                $formattingEndTagsPoppedByScope = [];
                $this->appendComment($parent, str_replace("\0", "\u{FFFD}", $match['bogus_comment'][0]));
                $offset = $tagEnd;
                continue;
            }

            if (($match['bogus_declaration'][1] ?? -1) !== -1) {
                $formattingEndTagsPoppedByScope = [];
                $declaration = $match['bogus_declaration'][0];
                if (str_starts_with($declaration, '[CDATA[') && self::isForeignContentCdataContext($parent, $root, $context)) {
                    [$cdata, $tagEnd] = self::consumeCdataSection($html, $tagStart);
                    $this->appendText($parent, $cdata, false);
                    $offset = $tagEnd;
                    continue;
                }

                $this->appendComment($parent, str_replace("\0", "\u{FFFD}", $declaration));
                $offset = $tagEnd;
                continue;
            }

            $tagName = self::normalizeTagTokenName($match['tag'][0]);
            $startTagEnd = self::consumeStartTag($html, $tagStart + strlen($match[0][0]));
            if ($startTagEnd === null) {
                return;
            }

            [$attributeSource, $tagEnd, $selfClosing] = $startTagEnd;

            if ($match['closing'][0] === '/') {
                if ($this->isDocumentShellTag($root, $context, $tagName)) {
                    $formattingEndTagsPoppedByScope = [];
                    $offset = $tagEnd;
                    continue;
                }

                if ($tagName === 'p' && ! $this->hasOpenElementInScope($stack, 'p')) {
                    $formattingEndTagsPoppedByScope = [];
                    $parent->appendChild($this->createElement('p'));
                    $offset = $tagEnd;
                    continue;
                }

                if (
                    self::isFormattingElementTag($tagName)
                    && ($formattingEndTagsPoppedByScope[$tagName] ?? 0) > 0
                ) {
                    $formattingEndTagsPoppedByScope[$tagName]--;
                    $offset = $tagEnd;
                    continue;
                }

                $formattingEndTagsPoppedByScope = [];

                $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;
                if ($tagName === 'form' && ! self::isHtmlInsertionContext($namespaceParent)) {
                    $this->closeForeignElementBeforeHtml($stack, 'form');
                    $offset = $tagEnd;
                    continue;
                }

                if ($tagName === 'form' && self::isHtmlInsertionContext($namespaceParent)) {
                    $targetFormElement = $formElement;
                    $formElement = null;
                    if ($targetFormElement !== null) {
                        $formattingEndTagsPoppedByScope = $this->closeElementInScopeByNode($stack, $targetFormElement);
                    }
                    $offset = $tagEnd;
                    continue;
                }

                if ($this->handleFormattingEndTagWithFurthestBlock($stack, $tagName)) {
                    $offset = $tagEnd;
                    continue;
                }

                if (
                    self::isBlockEndTagClosedInNormalScope($tagName)
                    && ! $this->currentNodeIsForeignWithTagInStack($stack, $tagName)
                ) {
                    $normalScopeState = $this->htmlElementNormalScopeState($stack, $tagName);
                    if ($normalScopeState === 'visible') {
                        $formattingEndTagsPoppedByScope = $this->closeElementInNormalScope($stack, $tagName);
                        $offset = $tagEnd;
                        continue;
                    }

                    if ($normalScopeState === 'blocked') {
                        $offset = $tagEnd;
                        continue;
                    }
                }

                if (self::isFormattingElementTag($tagName)) {
                    $formattingEndTagsPoppedByScope = $this->closeElementInScope($stack, $tagName);
                } else {
                    $formattingEndTagsPoppedByScope = $this->closeElementInScope($stack, $tagName);
                }
                $offset = $tagEnd;
                continue;
            }

            $formattingEndTagsPoppedByScope = [];
            $attributes = $this->parseAttributes($attributeSource);

            $formattingTailClones = [];
            $mathAnnotationXmlBreakout = false;
            $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;
            if (self::isMathAnnotationXmlHtmlBreakoutStartTag($namespaceParent, $tagName, $attributes)) {
                $this->popUntilHtmlInsertionContext($stack, $root, $context);
                $parent = $stack[count($stack) - 1];
                $mathAnnotationXmlBreakout = true;
                $namespaceParent = $parent;
            }

            if ($this->isDocumentShellTag($root, $context, $tagName)) {
                if ($parent === $root && $tagName === 'html') {
                    $this->mergeMissingAttributes($this->html, $attributes);
                } elseif ($parent === $root && $tagName === 'body') {
                    $this->mergeMissingAttributes($this->body, $attributes);
                }

                $offset = $tagEnd;
                continue;
            }

            if (
                $tagName === 'frame'
                && self::isHtmlInsertionContext($namespaceParent)
                && ! ($namespaceParent instanceof Element && $namespaceParent->tagName === 'frameset')
            ) {
                $offset = $tagEnd;
                continue;
            }

            if ($tagName === 'form' && self::isHtmlInsertionContext($namespaceParent)) {
                if ($formElement !== null) {
                    $offset = $tagEnd;
                    continue;
                }

                $inTableFormInsertionMode = $namespaceParent instanceof Element
                    && $namespaceParent->namespace === Element::NAMESPACE_HTML
                    && $namespaceParent->tagName === 'table';

                $formFormattingTailClones = [];
                if (! $inTableFormInsertionMode) {
                    $formFormattingTailClones = $this->closeOpenParagraphAndCloneFormattingTail($stack);
                    $parent = $stack[count($stack) - 1];
                    $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;
                }

                $element = $this->createElement('form', $this->namespaceForElement($namespaceParent, 'form'));
                foreach ($attributes as $attribute) {
                    $element->setAttribute($attribute['name'], $attribute['value']);
                }

                $stack[count($stack) - 1]->appendChild($element);
                $formElement = $element;
                $offset = $tagEnd;

                if ($inTableFormInsertionMode) {
                    continue;
                }

                $stack[] = $element;
                foreach ($formFormattingTailClones as $clone) {
                    $stack[count($stack) - 1]->appendChild($clone);
                    $stack[] = $clone;
                }
                continue;
            }

            if (
                $tagName === 'div'
                || (
                    self::isHtmlInsertionContext($namespaceParent)
                    && self::isParagraphClosingBlockStartTag($tagName)
                )
            ) {
                $formattingTailClones = $this->closeOpenParagraphAndCloneFormattingTail($stack);
                $parent = $stack[count($stack) - 1];
                $namespaceParent = ($parent === $root && $context !== null && ! $mathAnnotationXmlBreakout) ? $context : $parent;
            }

            if (
                $tagName === 'option'
                && self::isHtmlInsertionContext($namespaceParent)
                && $parent instanceof Element
                && $parent->namespace === Element::NAMESPACE_HTML
                && $parent->tagName === 'option'
            ) {
                array_pop($stack);
                $parent = $stack[count($stack) - 1];
                $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;
            }

            if (($tagName === 'dd' || $tagName === 'dt') && self::isHtmlInsertionContext($namespaceParent)) {
                $this->closeOpenDescriptionListItem($stack);
                $parent = $stack[count($stack) - 1];
                $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;
            }

            if ($tagName === 'p') {
                $this->closeOpenParagraphAndCloneFormattingTail($stack);
                $parent = $stack[count($stack) - 1];
            }

            if ($tagName === 'hr') {
                $this->closeOpenParagraphAndCloneFormattingTail($stack);
                $parent = $stack[count($stack) - 1];
            }

            if (self::isHeadingTag($tagName)) {
                $this->closeCurrentHeadingElement($stack);
                $parent = $stack[count($stack) - 1];
            }

            $namespaceParent = ($parent === $root && $context !== null && ! $mathAnnotationXmlBreakout) ? $context : $parent;
            $namespace = $this->namespaceForElement($namespaceParent, $tagName);

            if ($tagName === 'image' && $namespace === Element::NAMESPACE_HTML) {
                $tagName = 'img';
            }

            $element = $this->createElement($tagName, $namespace);
            foreach ($attributes as $attribute) {
                $element->setAttribute($attribute['name'], $attribute['value']);
            }

            $stack[count($stack) - 1]->appendChild($element);
            $offset = $tagEnd;

            if ($selfClosing || VoidElements::is($tagName)) {
                continue;
            }

            if ($this->isTextOnlyFragmentContext($element)) {
                $offset = $this->appendTextOnlyElementContent($element, $html, $offset);
                continue;
            }

            $stack[] = $element;
            foreach ($formattingTailClones as $clone) {
                $stack[count($stack) - 1]->appendChild($clone);
                $stack[] = $clone;
            }
        }

        if ($offset < strlen($html)) {
            $this->appendText($stack[count($stack) - 1], substr($html, $offset));
        }
    }

    /**
     * @return array{string, int, bool}|null
     */
    private static function consumeStartTag(string $html, int $offset): ?array
    {
        $attributeStart = $offset;
        $length = strlen($html);
        $state = 'before_attribute_name';
        $selfClosingStart = null;

        while ($offset < $length) {
            $character = $html[$offset];

            switch ($state) {
                case 'before_attribute_name':
                    if ($character === '>') {
                        return [substr($html, $attributeStart, $offset - $attributeStart), $offset + 1, false];
                    }

                    if ($character === '/') {
                        $selfClosingStart = $offset;
                        $state = 'self_closing_start_tag';
                        break;
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        break;
                    }

                    $state = 'attribute_name';
                    break;

                case 'attribute_name':
                    if ($character === '>') {
                        return [substr($html, $attributeStart, $offset - $attributeStart), $offset + 1, false];
                    }

                    if ($character === '/') {
                        $selfClosingStart = $offset;
                        $state = 'self_closing_start_tag';
                    } elseif (str_contains(" \t\n\r\f", $character)) {
                        $state = 'after_attribute_name';
                    } elseif ($character === '=') {
                        $state = 'before_attribute_value';
                    }
                    break;

                case 'after_attribute_name':
                    if ($character === '>') {
                        return [substr($html, $attributeStart, $offset - $attributeStart), $offset + 1, false];
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        break;
                    }

                    if ($character === '/') {
                        $selfClosingStart = $offset;
                        $state = 'self_closing_start_tag';
                    } elseif ($character === '=') {
                        $state = 'before_attribute_value';
                    } else {
                        $state = 'attribute_name';
                    }
                    break;

                case 'before_attribute_value':
                    if ($character === '>') {
                        return [substr($html, $attributeStart, $offset - $attributeStart), $offset + 1, false];
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        break;
                    }

                    if ($character === '"') {
                        $state = 'double_quoted_attribute_value';
                    } elseif ($character === "'") {
                        $state = 'single_quoted_attribute_value';
                    } else {
                        $state = 'unquoted_attribute_value';
                    }
                    break;

                case 'double_quoted_attribute_value':
                    if ($character === '"') {
                        $state = 'after_quoted_attribute_value';
                    }
                    break;

                case 'single_quoted_attribute_value':
                    if ($character === "'") {
                        $state = 'after_quoted_attribute_value';
                    }
                    break;

                case 'after_quoted_attribute_value':
                    if ($character === '>') {
                        return [substr($html, $attributeStart, $offset - $attributeStart), $offset + 1, false];
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        $state = 'before_attribute_name';
                    } elseif ($character === '/') {
                        $selfClosingStart = $offset;
                        $state = 'self_closing_start_tag';
                    } else {
                        $state = 'attribute_name';
                    }
                    break;

                case 'self_closing_start_tag':
                    if ($character === '>') {
                        $attributeSource = substr($html, $attributeStart, ($selfClosingStart ?? $offset) - $attributeStart);
                        return [self::rtrimHtmlWhitespace($attributeSource), $offset + 1, true];
                    }

                    $state = 'before_attribute_name';
                    continue 2;

                default:
                    if ($character === '>') {
                        return [substr($html, $attributeStart, $offset - $attributeStart), $offset + 1, false];
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        $state = 'before_attribute_name';
                    }
                    break;
            }

            $offset++;
        }

        return null;
    }

    private function appendComment(Node $parent, string $data): void
    {
        $parent->appendChild($this->createComment($data));
    }

    /**
     * @return array{string, int}
     */
    private static function consumeCdataSection(string $html, int $start): array
    {
        $dataStart = $start + strlen('<![CDATA[');
        $dataEnd = strpos($html, ']]>', $dataStart);

        if ($dataEnd === false) {
            return [substr($html, $dataStart), strlen($html)];
        }

        return [
            substr($html, $dataStart, $dataEnd - $dataStart),
            $dataEnd + 3,
        ];
    }

    private static function isForeignContentCdataContext(Node $parent, Node $root, ?Element $context): bool
    {
        $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;

        return $namespaceParent instanceof Element
            && $namespaceParent->namespace !== Element::NAMESPACE_HTML;
    }

    /**
     * @return array{string, int}
     */
    private static function consumeHtmlComment(string $html, int $start): array
    {
        $data = '';
        $offset = $start + 4;
        $length = strlen($html);
        $state = 'start';

        while ($offset < $length) {
            $character = $html[$offset];
            $normalized = $character === "\0" ? "\u{FFFD}" : $character;

            switch ($state) {
                case 'start':
                    if ($character === '>') {
                        return [$data, $offset + 1];
                    }

                    if ($character === '-') {
                        $state = 'start_dash';
                    } else {
                        $data .= $normalized;
                        $state = 'comment';
                    }
                    break;

                case 'start_dash':
                    if ($character === '>') {
                        return [$data, $offset + 1];
                    }

                    if ($character === '-') {
                        $state = 'end';
                    } else {
                        $data .= '-' . $normalized;
                        $state = 'comment';
                    }
                    break;

                case 'dash':
                    if ($character === '-') {
                        $state = 'end';
                    } else {
                        $data .= '-' . $normalized;
                        $state = 'comment';
                    }
                    break;

                case 'end':
                    if ($character === '>') {
                        return [$data, $offset + 1];
                    }

                    if ($character === '!') {
                        $state = 'end_bang';
                    } elseif ($character === '-') {
                        $data .= '-';
                    } else {
                        $data .= '--' . $normalized;
                        $state = 'comment';
                    }
                    break;

                case 'end_bang':
                    if ($character === '>') {
                        return [$data, $offset + 1];
                    }

                    if ($character === '-') {
                        $data .= '--!';
                        $state = 'dash';
                    } else {
                        $data .= '--!' . $normalized;
                        $state = 'comment';
                    }
                    break;

                default:
                    if ($character === '-') {
                        $state = 'dash';
                    } else {
                        $data .= $normalized;
                    }
                    break;
            }

            $offset++;
        }

        return [$data, $length];
    }

    private static function normalizeTagTokenName(string $tagName): string
    {
        return str_replace("\0", "\u{FFFD}", strtolower($tagName));
    }

    private function appendText(Node $parent, string $data, bool $decodeCharacterReferences = true): void
    {
        if ($data !== '') {
            $parent->appendChild($this->createTextNode(
                $decodeCharacterReferences ? $this->decodeCharacterReferences($data) : $data,
            ));
        }
    }

    private function appendTextOnlyElementContent(Element $element, string $html, int $offset): int
    {
        if ($element->tagName === 'plaintext') {
            $this->appendTextOnlyElementData($element, substr($html, $offset));

            return strlen($html);
        }

        if ($element->tagName === 'script') {
            $close = self::consumeScriptDataEndTag($html, $offset);
            if ($close === null) {
                $this->appendTextOnlyElementData($element, substr($html, $offset));
                return strlen($html);
            }

            [$closeStart, $closeEnd] = $close;
            $this->appendTextOnlyElementData($element, substr($html, $offset, $closeStart - $offset));

            return $closeEnd;
        }

        $pattern = sprintf('~</%s\s*>~i', preg_quote($element->tagName, '~'));

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            $incompleteClose = self::consumeIncompleteTextOnlyEndTagAtEof($html, $offset, $element->tagName);
            if ($incompleteClose !== null) {
                [$closeStart, $closeEnd] = $incompleteClose;
                $this->appendTextOnlyElementData($element, substr($html, $offset, $closeStart - $offset));

                return $closeEnd;
            }

            $this->appendTextOnlyElementData($element, substr($html, $offset));
            return strlen($html);
        }

        $closeStart = $match[0][1];
        $this->appendTextOnlyElementData($element, substr($html, $offset, $closeStart - $offset));

        return $closeStart + strlen($match[0][0]);
    }

    private function appendTextOnlyElementData(Element $element, string $data): void
    {
        if ($data !== '') {
            $element->appendChild($this->createTextNode($this->textOnlyElementData($element, $data)));
        }
    }

    private function textOnlyElementData(Element $element, string $data): string
    {
        $data = $this->shouldDecodeTextOnlyElementContent($element)
            ? $this->decodeCharacterReferences($data)
            : $data;

        return str_replace("\0", "\u{FFFD}", $data);
    }

    /**
     * @return array{int, int}|null
     */
    private static function consumeScriptDataEndTag(string $html, int $offset): ?array
    {
        $state = 'data';
        $length = strlen($html);

        while ($offset < $length) {
            $scriptEndTagNameEnd = self::consumeScriptEndTagNameAt($html, $offset);
            if ($state === 'double_escaped' && $scriptEndTagNameEnd !== null) {
                $state = 'escaped';
                $offset = $scriptEndTagNameEnd;
                continue;
            }

            $endTag = self::consumeTextOnlyEndTagAt($html, $offset, 'script');
            if ($endTag !== null) {
                return $endTag;
            }

            $incompleteEndTag = self::consumeIncompleteTextOnlyEndTagAtCurrentOffsetAtEof($html, $offset, 'script');
            if ($incompleteEndTag !== null) {
                return $incompleteEndTag;
            }

            if (($state === 'escaped' || $state === 'double_escaped') && substr($html, $offset, 3) === '-->') {
                $state = 'data';
                $offset += 3;
                continue;
            }

            if ($state === 'data' && substr($html, $offset, 4) === '<!--') {
                $state = 'escaped';
                $offset += 4;
                continue;
            }

            $scriptStartTagNameEnd = self::consumeScriptStartTagNameAt($html, $offset);
            if ($state === 'escaped' && $scriptStartTagNameEnd !== null) {
                $state = 'double_escaped';
                $offset = $scriptStartTagNameEnd;
                continue;
            }

            $offset++;
        }

        return null;
    }

    /**
     * @return array{int, int}|null
     */
    private static function consumeTextOnlyEndTagAt(string $html, int $offset, string $tagName): ?array
    {
        if (preg_match(sprintf('~^</%s\s*>~i', preg_quote($tagName, '~')), substr($html, $offset), $match) !== 1) {
            return null;
        }

        return [$offset, $offset + strlen($match[0])];
    }

    /**
     * @return array{int, int}|null
     */
    private static function consumeIncompleteTextOnlyEndTagAtEof(string $html, int $offset, string $tagName): ?array
    {
        if (preg_match(sprintf('~</%s(?:[\t\n\f\r ]*/[\t\n\f\r ]*|[\t\n\f\r ]+)\z~i', preg_quote($tagName, '~')), $html, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return null;
        }

        return [$match[0][1], strlen($html)];
    }

    /**
     * @return array{int, int}|null
     */
    private static function consumeIncompleteTextOnlyEndTagAtCurrentOffsetAtEof(string $html, int $offset, string $tagName): ?array
    {
        if (preg_match(sprintf('~^</%s(?:[\t\n\f\r ]*/[\t\n\f\r ]*|[\t\n\f\r ]+)\z~i', preg_quote($tagName, '~')), substr($html, $offset)) !== 1) {
            return null;
        }

        return [$offset, strlen($html)];
    }

    private static function consumeScriptStartTagNameAt(string $html, int $offset): ?int
    {
        if (preg_match('~^<script(?=[\t\n\f\r />])~i', substr($html, $offset), $match) !== 1) {
            return null;
        }

        return $offset + strlen($match[0]);
    }

    private static function consumeScriptEndTagNameAt(string $html, int $offset): ?int
    {
        if (preg_match('~^</script(?=[\t\n\f\r />])~i', substr($html, $offset), $match) !== 1) {
            return null;
        }

        return $offset + strlen($match[0]);
    }

    /**
     * @param list<Node> $stack
     * @return array<string, int>
     */
    private function closeElementInScope(array &$stack, string $tagName): array
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];

            if (! $node instanceof Element) {
                continue;
            }

            if ($node->tagName === $tagName) {
                $formattingTags = [];
                for ($poppedIndex = $index + 1; $poppedIndex < count($stack); $poppedIndex++) {
                    $poppedNode = $stack[$poppedIndex];
                    if (! $poppedNode instanceof Element || ! self::isHtmlFormattingElement($poppedNode)) {
                        continue;
                    }

                    $formattingTags[$poppedNode->tagName] = ($formattingTags[$poppedNode->tagName] ?? 0) + 1;
                }

                array_splice($stack, $index);
                return $formattingTags;
            }

            if (self::isHtmlScopeBoundaryElement($node)) {
                return [];
            }
        }

        return [];
    }

    /**
     * @param list<Node> $stack
     * @return array<string, int>
     */
    private function closeElementInScopeByNode(array &$stack, Element $target): array
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];

            if (! $node instanceof Element) {
                continue;
            }

            if ($node === $target) {
                array_splice($stack, $index, 1);
                return [];
            }

            if (self::isNormalScopeBoundary($node)) {
                return [];
            }
        }

        return [];
    }

    /**
     * @param list<Node> $stack
     */
    private function closeForeignElementBeforeHtml(array &$stack, string $tagName): void
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];

            if (! $node instanceof Element) {
                continue;
            }

            if ($node->namespace === Element::NAMESPACE_HTML) {
                return;
            }

            if ($node->tagName === $tagName) {
                array_splice($stack, $index);
                return;
            }
        }
    }

    /**
     * @param list<Node> $stack
     */
    private function popUntilHtmlInsertionContext(array &$stack, Node $root, ?Element $context): void
    {
        while (count($stack) > 1) {
            $parent = $stack[count($stack) - 1];
            $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;

            if (self::isHtmlInsertionContext($namespaceParent) || self::isMathTextIntegrationPoint($namespaceParent)) {
                return;
            }

            array_pop($stack);
        }
    }

    /**
     * @param list<Node> $stack
     * @return array<string, int>
     */
    private function closeElementInNormalScope(array &$stack, string $tagName): array
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];

            if (! $node instanceof Element) {
                continue;
            }

            if ($node->namespace === Element::NAMESPACE_HTML && $node->tagName === $tagName) {
                $formattingTags = [];
                for ($poppedIndex = $index + 1; $poppedIndex < count($stack); $poppedIndex++) {
                    $poppedNode = $stack[$poppedIndex];
                    if (! $poppedNode instanceof Element || ! self::isHtmlFormattingElement($poppedNode)) {
                        continue;
                    }

                    $formattingTags[$poppedNode->tagName] = ($formattingTags[$poppedNode->tagName] ?? 0) + 1;
                }

                array_splice($stack, $index);
                return $formattingTags;
            }

            if (self::isNormalScopeBoundary($node)) {
                return [];
            }
        }

        return [];
    }

    /**
     * @param list<Node> $stack
     */
    private function htmlElementNormalScopeState(array $stack, string $tagName): string
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];
            if (! $node instanceof Element) {
                continue;
            }

            if ($node->namespace === Element::NAMESPACE_HTML && $node->tagName === $tagName) {
                return 'visible';
            }

            if (self::isNormalScopeBoundary($node)) {
                return 'blocked';
            }
        }

        return 'absent';
    }

    /**
     * @param list<Node> $stack
     */
    private function currentNodeIsForeignWithTagInStack(array $stack, string $tagName): bool
    {
        $currentNode = $stack[count($stack) - 1] ?? null;
        if (! $currentNode instanceof Element || $currentNode->namespace === Element::NAMESPACE_HTML) {
            return false;
        }

        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];

            if ($node instanceof Element && $node->namespace !== Element::NAMESPACE_HTML && $node->tagName === $tagName) {
                return true;
            }

            if ($node instanceof Element && $node->namespace === Element::NAMESPACE_HTML) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param list<Node> $stack
     * @return list<Element>
     */
    private function closeOpenParagraphAndCloneFormattingTail(array &$stack): array
    {
        $paragraphIndex = null;
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];
            if (! $node instanceof Element) {
                continue;
            }

            if ($node->tagName === 'p') {
                $paragraphIndex = $index;
                break;
            }

            if (self::isButtonScopeBoundaryElement($node)) {
                return [];
            }
        }

        if ($paragraphIndex === null) {
            return [];
        }

        $tailClones = [];
        for ($index = $paragraphIndex + 1; $index < count($stack); $index++) {
            $node = $stack[$index];
            if (! $node instanceof Element || ! self::isHtmlFormattingElement($node)) {
                continue;
            }

            $tailClones[] = $this->cloneElementShallow($node);
        }

        array_splice($stack, $paragraphIndex);

        return $tailClones;
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
     * @param list<Node> $stack
     */
    private function handleFormattingEndTagWithFurthestBlock(array &$stack, string $tagName): bool
    {
        if ($tagName !== 'b' && $tagName !== 'font' && $tagName !== 'i') {
            return false;
        }

        $formattingIndex = $this->openElementIndex($stack, $tagName);
        if ($formattingIndex === null) {
            return true;
        }

        $furthestBlockIndex = null;
        for ($index = $formattingIndex + 1; $index < count($stack); $index++) {
            $node = $stack[$index];
            if ($node instanceof Element && self::isFormattingFurthestBlock($node->tagName)) {
                $furthestBlockIndex = $index;
                break;
            }

            if ($node instanceof Element && self::isHtmlScopeBoundaryElement($node)) {
                return false;
            }
        }

        if ($furthestBlockIndex === null) {
            return false;
        }

        $formattingElement = $stack[$formattingIndex];
        $furthestBlock = $stack[$furthestBlockIndex];
        if (! $formattingElement instanceof Element || ! $furthestBlock instanceof Element) {
            return false;
        }

        $tailClones = [];
        for ($index = $furthestBlockIndex + 1; $index < count($stack); $index++) {
            $node = $stack[$index];
            if (! $node instanceof Element || ! self::isHtmlFormattingElement($node)) {
                continue;
            }

            $tailClones[] = $this->cloneElementShallow($node);
        }

        $intermediateClones = [];
        for ($index = $formattingIndex + 1; $index < $furthestBlockIndex; $index++) {
            $node = $stack[$index];
            if (! $node instanceof Element || ! self::isFormattingElementTag($node->tagName)) {
                continue;
            }

            $clone = $this->cloneElementShallow($node);
            if ($intermediateClones === []) {
                $formattingElement->insertAfter($clone);
            } else {
                $intermediateClones[count($intermediateClones) - 1]->appendChild($clone);
            }

            $intermediateClones[] = $clone;
        }

        $furthestBlock->remove();
        if ($intermediateClones === []) {
            $formattingElement->insertAfter($furthestBlock);
        } else {
            $intermediateClones[count($intermediateClones) - 1]->appendChild($furthestBlock);
        }

        $formattingClone = $this->cloneElementShallow($formattingElement);

        while ($furthestBlock->firstChild !== null) {
            $formattingClone->appendChild($furthestBlock->firstChild);
        }

        $furthestBlock->appendChild($formattingClone);

        $stack = array_slice($stack, 0, $formattingIndex);
        foreach ($intermediateClones as $clone) {
            $stack[] = $clone;
        }

        $stack[] = $furthestBlock;
        foreach ($tailClones as $clone) {
            $stack[count($stack) - 1]->appendChild($clone);
            $stack[] = $clone;
        }

        return true;
    }

    private function cloneElementShallow(Element $element): Element
    {
        $clone = $this->createElement($element->tagName, $element->namespace);
        foreach ($element->attributes as $name => $value) {
            $clone->setAttribute((string) $name, $value);
        }

        return $clone;
    }

    /**
     * @param list<Node> $stack
     */
    private function openElementIndex(array $stack, string $tagName): ?int
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];
            if ($node instanceof Element && $node->tagName === $tagName) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<Node> $stack
     */
    private function hasOpenElement(array $stack, string $tagName): bool
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];
            if ($node instanceof Element && $node->tagName === $tagName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Node> $stack
     */
    private function hasOpenElementInScope(array $stack, string $tagName): bool
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];
            if (! $node instanceof Element) {
                continue;
            }

            if ($node->tagName === $tagName) {
                return true;
            }

            if (self::isHtmlScopeBoundaryElement($node)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param list<Node> $stack
     */
    private function closeOpenDescriptionListItem(array &$stack): void
    {
        for ($index = count($stack) - 1; $index > 0; $index--) {
            $node = $stack[$index];
            if (! $node instanceof Element) {
                continue;
            }

            if ($node->namespace === Element::NAMESPACE_HTML && ($node->tagName === 'dd' || $node->tagName === 'dt')) {
                array_splice($stack, $index);
                return;
            }

            if (self::isDescriptionListItemSpecialBoundaryElement($node)) {
                return;
            }
        }
    }

    private static function isDescriptionListItemSpecialBoundaryElement(Element $element): bool
    {
        if ($element->namespace === Element::NAMESPACE_HTML) {
            if (in_array($element->tagName, ['address', 'div', 'p'], true)) {
                return false;
            }

            return in_array($element->tagName, [
                'applet',
                'area',
                'article',
                'aside',
                'base',
                'basefont',
                'bgsound',
                'blockquote',
                'body',
                'br',
                'button',
                'caption',
                'center',
                'col',
                'colgroup',
                'dd',
                'details',
                'dir',
                'dl',
                'dt',
                'embed',
                'fieldset',
                'figcaption',
                'figure',
                'footer',
                'form',
                'frame',
                'frameset',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'head',
                'header',
                'hgroup',
                'hr',
                'html',
                'iframe',
                'image',
                'img',
                'input',
                'keygen',
                'li',
                'link',
                'listing',
                'main',
                'marquee',
                'menu',
                'meta',
                'nav',
                'noembed',
                'noframes',
                'noscript',
                'object',
                'ol',
                'param',
                'plaintext',
                'pre',
                'script',
                'search',
                'section',
                'select',
                'source',
                'style',
                'summary',
                'table',
                'tbody',
                'td',
                'template',
                'textarea',
                'tfoot',
                'th',
                'thead',
                'title',
                'tr',
                'track',
                'ul',
                'wbr',
                'xmp',
            ], true);
        }

        if ($element->namespace === Element::NAMESPACE_MATH) {
            return $element->tagName === 'annotation-xml'
                || self::isMathTextIntegrationPoint($element);
        }

        if ($element->namespace === Element::NAMESPACE_SVG) {
            return $element->tagName === 'desc'
                || $element->tagName === 'foreignobject'
                || $element->tagName === 'title';
        }

        return false;
    }

    /**
     * @param list<Node> $stack
     */
    private function closeCurrentHeadingElement(array &$stack): void
    {
        $node = $stack[count($stack) - 1];
        if (! $node instanceof Element || ! self::isHeadingTag($node->tagName)) {
            return;
        }

        array_pop($stack);
    }

    private function isDocumentShellTag(Node $root, ?Element $context, string $tagName): bool
    {
        return $root === $this->body
            && $context === null
            && ($tagName === 'html' || $tagName === 'head' || $tagName === 'body');
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     */
    private function mergeMissingAttributes(Element $element, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            if (! $element->hasAttribute($attribute['name'])) {
                $element->setAttribute($attribute['name'], $attribute['value']);
            }
        }
    }

    private function consumeDocumentHeadContent(string $html): string
    {
        $offset = 0;
        $headStarted = false;
        $headClosed = false;
        $ignoredDoctype = false;

        $length = strlen($html);
        while ($offset < $length) {
            $whitespace = strspn($html, " \t\n\f\r", $offset);
            $tokenOffset = $offset + $whitespace;

            if ($headStarted && $whitespace > 0) {
                if (self::consumeNamedStartTagAt($html, $tokenOffset, 'body') !== null) {
                    if ($headClosed) {
                        $this->appendText($this->body, substr($html, $offset, $whitespace));
                        return substr($html, $tokenOffset);
                    }

                    return substr($html, $offset);
                }

                if ($headClosed) {
                    if (! self::isRecoverableHeadStartTagAt($html, $tokenOffset)) {
                        return substr($html, $offset);
                    }

                    $this->appendText($this->body, substr($html, $offset, $whitespace));
                } else {
                    $this->appendText($this->head, substr($html, $offset, $whitespace));
                }

                $offset = $tokenOffset;
            } else {
                $offset = $tokenOffset;
            }

            $doctypeEnd = $this->consumeDoctypeAt($html, $offset);
            if ($doctypeEnd !== null) {
                $offset = $doctypeEnd;
                $ignoredDoctype = true;
                continue;
            }

            $htmlTag = self::consumeNamedStartTagAt($html, $offset, 'html');
            if ($htmlTag !== null) {
                [$attributes, $offset] = $htmlTag;
                $this->mergeMissingAttributes($this->html, $this->parseAttributes($attributes));
                continue;
            }

            $headTag = self::consumeNamedStartTagAt($html, $offset, 'head');
            if ($headTag !== null && ! $headClosed) {
                [$attributes, $offset] = $headTag;
                foreach ($this->parseAttributes($attributes) as $attribute) {
                    $this->head->setAttribute($attribute['name'], $attribute['value']);
                }

                $headStarted = true;
                $headClosed = false;
                continue;
            }

            $headClose = self::consumeNamedEndTagAt($html, $offset, 'head');
            if ($headClose !== null) {
                $offset = $headClose;
                $headStarted = true;
                $headClosed = true;
                continue;
            }

            if ($headStarted && ! $headClosed) {
                $ignoredEndTag = self::consumeIgnorableHeadEndTagAt($html, $offset);
                if ($ignoredEndTag !== null) {
                    $offset = $ignoredEndTag;
                    continue;
                }
            }

            foreach (['title', 'style', 'script'] as $tagName) {
                $startTag = self::consumeNamedStartTagAt($html, $offset, $tagName);
                if ($startTag === null) {
                    continue;
                }

                [$attributes, $startTagEnd, $selfClosing] = $startTag;
                $element = $this->createElement($tagName);
                foreach ($this->parseAttributes($attributes) as $attribute) {
                    $element->setAttribute($attribute['name'], $attribute['value']);
                }

                $this->head->appendChild($element);
                $offset = $selfClosing ? $startTagEnd : $this->appendTextOnlyElementContent($element, $html, $startTagEnd);
                $headStarted = true;
                continue 2;
            }

            foreach (['base', 'basefont', 'bgsound', 'link', 'meta'] as $tagName) {
                $startTag = self::consumeNamedStartTagAt($html, $offset, $tagName);
                if ($startTag === null) {
                    continue;
                }

                [$attributes, $offset] = $startTag;
                $element = $this->createElement($tagName);
                foreach ($this->parseAttributes($attributes) as $attribute) {
                    $element->setAttribute($attribute['name'], $attribute['value']);
                }

                $this->head->appendChild($element);
                $headStarted = true;
                continue 2;
            }

            if ($headStarted) {
                return substr($html, $offset);
            }

            return $ignoredDoctype ? substr($html, $offset) : $html;
        }

        return '';
    }

    private function consumeDoctypeAt(string $html, int $offset): ?int
    {
        if (strncasecmp(substr($html, $offset, strlen('<!doctype')), '<!doctype', strlen('<!doctype')) !== 0) {
            return null;
        }

        $doctype = $this->parseLeadingDoctype(substr($html, $offset));
        if ($doctype === null) {
            return null;
        }

        return $offset + $doctype['offset'];
    }

    /**
     * @return array{string, int, bool}|null
     */
    private static function consumeNamedStartTagAt(string $html, int $offset, string $tagName): ?array
    {
        if (preg_match(sprintf('~^<%s(?=[\t\n\f\r />])~i', preg_quote($tagName, '~')), substr($html, $offset), $match) !== 1) {
            return null;
        }

        return self::consumeStartTag($html, $offset + strlen($match[0]));
    }

    private static function consumeNamedEndTagAt(string $html, int $offset, string $tagName): ?int
    {
        if (preg_match(sprintf('~^</%s\s*>~i', preg_quote($tagName, '~')), substr($html, $offset), $match) !== 1) {
            return null;
        }

        return $offset + strlen($match[0]);
    }

    private static function consumeIgnorableHeadEndTagAt(string $html, int $offset): ?int
    {
        foreach (['div', 'p'] as $tagName) {
            $endTag = self::consumeNamedEndTagAt($html, $offset, $tagName);
            if ($endTag !== null) {
                return $endTag;
            }
        }

        return null;
    }

    private static function isRecoverableHeadStartTagAt(string $html, int $offset): bool
    {
        foreach (['base', 'basefont', 'bgsound', 'link', 'meta', 'script', 'style', 'title'] as $tagName) {
            if (self::consumeNamedStartTagAt($html, $offset, $tagName) !== null) {
                return true;
            }
        }

        return false;
    }

    private function namespaceForElement(Node $parent, string $tagName): string
    {
        if ($tagName === 'svg') {
            return Element::NAMESPACE_SVG;
        }

        if ($tagName === 'math') {
            return Element::NAMESPACE_MATH;
        }

        if (! $parent instanceof Element) {
            return Element::NAMESPACE_HTML;
        }

        if (
            self::isMathTextIntegrationPoint($parent)
            && $tagName !== 'mglyph'
            && $tagName !== 'malignmark'
        ) {
            return Element::NAMESPACE_HTML;
        }

        if (self::isHtmlInsertionContext($parent)) {
            return Element::NAMESPACE_HTML;
        }

        if ($parent->namespace === Element::NAMESPACE_SVG && $parent->tagName !== 'foreignobject') {
            return Element::NAMESPACE_SVG;
        }

        if ($parent->namespace === Element::NAMESPACE_MATH) {
            return Element::NAMESPACE_MATH;
        }

        return Element::NAMESPACE_HTML;
    }

    private static function isHtmlInsertionContext(Node $parent): bool
    {
        if (! $parent instanceof Element) {
            return true;
        }

        if ($parent->namespace === Element::NAMESPACE_HTML) {
            return true;
        }

        if (self::isMathAnnotationXmlHtmlIntegrationPoint($parent)) {
            return true;
        }

        return $parent->namespace === Element::NAMESPACE_SVG
            && $parent->tagName === 'foreignobject';
    }

    private static function isMathTextIntegrationPoint(Node $parent): bool
    {
        return $parent instanceof Element
            && $parent->namespace === Element::NAMESPACE_MATH
            && (
                $parent->tagName === 'mi'
                || $parent->tagName === 'mn'
                || $parent->tagName === 'mo'
                || $parent->tagName === 'ms'
                || $parent->tagName === 'mtext'
            );
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     */
    private static function isMathAnnotationXmlHtmlBreakoutStartTag(Node $parent, string $tagName, array $attributes): bool
    {
        return $parent instanceof Element
            && $parent->namespace === Element::NAMESPACE_MATH
            && $parent->tagName === 'annotation-xml'
            && ! self::isMathAnnotationXmlHtmlIntegrationPoint($parent)
            && self::isMathAnnotationXmlHtmlBreakoutTagName($tagName, $attributes);
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     */
    private static function isMathAnnotationXmlHtmlBreakoutTagName(string $tagName, array $attributes): bool
    {
        return in_array($tagName, [
            'b',
            'big',
            'blockquote',
            'body',
            'br',
            'center',
            'code',
            'dd',
            'div',
            'dl',
            'dt',
            'em',
            'embed',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'head',
            'hr',
            'i',
            'img',
            'li',
            'listing',
            'menu',
            'meta',
            'nobr',
            'ol',
            'p',
            'pre',
            'ruby',
            's',
            'small',
            'span',
            'strong',
            'strike',
            'sub',
            'sup',
            'table',
            'tt',
            'u',
            'ul',
            'var',
        ], true)
            || ($tagName === 'font' && self::hasAnyAttribute($attributes, ['color', 'face', 'size']));
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     * @param list<string> $names
     */
    private static function hasAnyAttribute(array $attributes, array $names): bool
    {
        foreach ($attributes as $attribute) {
            if (in_array($attribute['name'], $names, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     */
    private static function attributeValue(array $attributes, string $name): ?string
    {
        foreach ($attributes as $attribute) {
            if ($attribute['name'] === $name) {
                return $attribute['value'];
            }
        }

        return null;
    }

    private static function isMathAnnotationXmlHtmlIntegrationPoint(Element $element): bool
    {
        if ($element->namespace !== Element::NAMESPACE_MATH || $element->tagName !== 'annotation-xml') {
            return false;
        }

        $encoding = $element->getAttribute('encoding');
        if ($encoding === null) {
            return false;
        }

        return strcasecmp($encoding, 'text/html') === 0
            || strcasecmp($encoding, 'application/xhtml+xml') === 0;
    }

    private static function isHeadingTag(string $tagName): bool
    {
        return $tagName === 'h1'
            || $tagName === 'h2'
            || $tagName === 'h3'
            || $tagName === 'h4'
            || $tagName === 'h5'
            || $tagName === 'h6';
    }

    private static function isFormattingFurthestBlock(string $tagName): bool
    {
        return $tagName === 'button'
            || $tagName === 'div'
            || $tagName === 'p';
    }

    private static function isBlockEndTagClosedInNormalScope(string $tagName): bool
    {
        return in_array($tagName, [
            'address',
            'article',
            'aside',
            'blockquote',
            'button',
            'center',
            'details',
            'dialog',
            'dir',
            'div',
            'dl',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'header',
            'hgroup',
            'listing',
            'main',
            'menu',
            'nav',
            'ol',
            'pre',
            'search',
            'section',
            'select',
            'summary',
            'ul',
        ], true);
    }

    private static function isParagraphClosingBlockStartTag(string $tagName): bool
    {
        return in_array($tagName, [
            'address',
            'article',
            'aside',
            'blockquote',
            'center',
            'dd',
            'details',
            'dialog',
            'dir',
            'div',
            'dl',
            'dt',
            'fieldset',
            'figcaption',
            'figure',
            'footer',
            'header',
            'hgroup',
            'li',
            'main',
            'menu',
            'nav',
            'ol',
            'search',
            'section',
            'summary',
            'ul',
        ], true);
    }

    private static function isFormattingElementTag(string $tagName): bool
    {
        return in_array($tagName, [
            'a',
            'b',
            'big',
            'code',
            'em',
            'font',
            'i',
            'nobr',
            's',
            'small',
            'strike',
            'strong',
            'tt',
            'u',
        ], true);
    }

    private static function isHtmlFormattingElement(Element $element): bool
    {
        return $element->namespace === Element::NAMESPACE_HTML
            && self::isFormattingElementTag($element->tagName);
    }

    private static function isHtmlScopeBoundary(string $tagName): bool
    {
        return $tagName === 'button'
            || $tagName === 'div'
            || $tagName === 'marquee'
            || $tagName === 'p'
            || $tagName === 'table';
    }

    private static function isHtmlScopeBoundaryElement(Element $element): bool
    {
        return $element->namespace === Element::NAMESPACE_HTML
            && self::isHtmlScopeBoundary($element->tagName);
    }

    private static function isButtonScopeBoundaryElement(Element $element): bool
    {
        if ($element->namespace === Element::NAMESPACE_HTML) {
            return $element->tagName === 'applet'
                || $element->tagName === 'button'
                || $element->tagName === 'caption'
                || $element->tagName === 'html'
                || $element->tagName === 'marquee'
                || $element->tagName === 'object'
                || $element->tagName === 'select'
                || $element->tagName === 'table'
                || $element->tagName === 'td'
                || $element->tagName === 'template'
                || $element->tagName === 'th';
        }

        if ($element->namespace === Element::NAMESPACE_MATH) {
            return $element->tagName === 'annotation-xml'
                || self::isMathTextIntegrationPoint($element);
        }

        if ($element->namespace === Element::NAMESPACE_SVG) {
            return $element->tagName === 'desc'
                || $element->tagName === 'foreignobject'
                || $element->tagName === 'title';
        }

        return false;
    }

    private static function isNormalScopeBoundary(Element $element): bool
    {
        if ($element->namespace === Element::NAMESPACE_HTML) {
            return $element->tagName === 'applet'
                || $element->tagName === 'caption'
                || $element->tagName === 'html'
                || $element->tagName === 'marquee'
                || $element->tagName === 'object'
                || $element->tagName === 'select'
                || $element->tagName === 'table'
                || $element->tagName === 'td'
                || $element->tagName === 'template'
                || $element->tagName === 'th';
        }

        if ($element->namespace === Element::NAMESPACE_MATH) {
            return $element->tagName === 'annotation-xml'
                || self::isMathTextIntegrationPoint($element);
        }

        if ($element->namespace === Element::NAMESPACE_SVG) {
            return $element->tagName === 'desc'
                || $element->tagName === 'foreignobject'
                || $element->tagName === 'title';
        }

        return false;
    }

    /**
     * @return array{attributes: string, content: string}|null
     */
    private function bodyFragment(string $html): ?array
    {
        $pattern = '~<(?<closing>/)?(?<tag>[A-Za-z][^\t\n\f\r />]*)~si';
        $offset = 0;
        $stack = [];

        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $offset) === 1) {
            $tagStart = $match[0][1];
            $ignoredMarkupEnd = self::bodyFragmentScannerIgnoredMarkupEnd(
                $html,
                $offset,
                $tagStart,
                self::bodyFragmentScannerHasOpenForeignElement($stack),
            );
            if ($ignoredMarkupEnd !== null) {
                $offset = $ignoredMarkupEnd;
                continue;
            }

            $tagName = self::normalizeTagTokenName($match['tag'][0]);
            $tagEnd = $tagStart + strlen($match[0][0]);

            if ($match['closing'][0] === '/') {
                self::popBodyFragmentScannerStack($stack, $tagName);
                $offset = self::consumeBodyFragmentScannerEndTag($html, $tagEnd);
                continue;
            }

            $bodyTag = self::consumeStartTag($html, $tagEnd);
            if ($bodyTag === null) {
                return null;
            }

            [$attributes, $start, $selfClosing] = $bodyTag;
            $parsedAttributes = $this->parseAttributes($attributes);
            $namespaceParent = $stack[count($stack) - 1] ?? null;
            $mathAnnotationXmlBreakout = false;
            if (self::bodyFragmentScannerIsMathAnnotationXmlHtmlBreakoutStartTag($namespaceParent, $tagName, $parsedAttributes)) {
                self::popBodyFragmentScannerUntilHtmlInsertionContext($stack);
                $mathAnnotationXmlBreakout = true;
            }

            if ($tagName === 'body') {
                if (! $mathAnnotationXmlBreakout && ! self::bodyFragmentScannerHasOpenForeignElement($stack)) {
                    $closePattern = '~</body\s*>~i';

                    if (preg_match($closePattern, $html, $close, PREG_OFFSET_CAPTURE, $start) !== 1) {
                        return [
                            'attributes' => $attributes,
                            'content' => substr($html, $start),
                        ];
                    }

                    return [
                        'attributes' => $attributes,
                        'content' => substr($html, $start, $close[0][1] - $start),
                    ];
                }

                $offset = $start;
                continue;
            }

            if (! $selfClosing && self::isTextOnlyTagName($tagName, $this->isScriptingEnabled())) {
                $offset = self::skipTextOnlyElementContent($tagName, $html, $start);
                continue;
            }

            if (! $selfClosing && ! VoidElements::is($tagName)) {
                $namespace = self::bodyFragmentScannerNamespaceForElement($stack, $tagName);
                $stack[] = [
                    'tagName' => $tagName,
                    'namespace' => $namespace,
                    'htmlIntegration' => self::bodyFragmentScannerIsMathAnnotationXmlHtmlIntegrationPoint($namespace, $tagName, $parsedAttributes),
                ];
            }

            $offset = $start;
        }

        return null;
    }

    /**
     * @param list<array{tagName: string, namespace: string, htmlIntegration: bool}> $stack
     */
    private static function bodyFragmentScannerNamespaceForElement(array $stack, string $tagName): string
    {
        if ($tagName === 'svg') {
            return Element::NAMESPACE_SVG;
        }

        if ($tagName === 'math') {
            return Element::NAMESPACE_MATH;
        }

        $parent = $stack[count($stack) - 1] ?? null;
        if ($parent === null) {
            return Element::NAMESPACE_HTML;
        }

        if (
            self::bodyFragmentScannerIsMathTextIntegrationPoint($parent)
            && $tagName !== 'mglyph'
            && $tagName !== 'malignmark'
        ) {
            return Element::NAMESPACE_HTML;
        }

        if (self::bodyFragmentScannerIsHtmlInsertionContext($parent)) {
            return Element::NAMESPACE_HTML;
        }

        if ($parent['namespace'] === Element::NAMESPACE_SVG && $parent['tagName'] !== 'foreignobject') {
            return Element::NAMESPACE_SVG;
        }

        if ($parent['namespace'] === Element::NAMESPACE_MATH) {
            return Element::NAMESPACE_MATH;
        }

        return Element::NAMESPACE_HTML;
    }

    /**
     * @param list<array{tagName: string, namespace: string, htmlIntegration: bool}> $stack
     */
    private static function bodyFragmentScannerHasOpenForeignElement(array $stack): bool
    {
        foreach ($stack as $element) {
            if ($element['namespace'] !== Element::NAMESPACE_HTML) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{tagName: string, namespace: string, htmlIntegration: bool}> $stack
     */
    private static function popBodyFragmentScannerStack(array &$stack, string $tagName): void
    {
        for ($index = count($stack) - 1; $index >= 0; $index--) {
            if ($stack[$index]['tagName'] === $tagName) {
                array_splice($stack, $index);
                return;
            }
        }
    }

    private static function bodyFragmentScannerIgnoredMarkupEnd(string $html, int $offset, int $tagStart, bool $inForeignContent): ?int
    {
        $cursor = $offset;
        while (($markupStart = strpos($html, '<', $cursor)) !== false && $markupStart < $tagStart) {
            if (substr($html, $markupStart, 4) === '<!--') {
                [, $end] = self::consumeHtmlComment($html, $markupStart);
                return $end;
            }

            if ($inForeignContent && substr($html, $markupStart, 9) === '<![CDATA[') {
                [, $end] = self::consumeCdataSection($html, $markupStart);
                return $end;
            }

            $comment = self::consumeDocumentPrologueComment($html, $markupStart, false);
            if ($comment !== null) {
                return $comment[1];
            }

            $cursor = $markupStart + 1;
        }

        return null;
    }

    private static function consumeBodyFragmentScannerEndTag(string $html, int $offset): int
    {
        $length = strlen($html);
        $quote = null;

        while ($offset < $length) {
            $character = $html[$offset];

            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }
                $offset++;
                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $offset++;
                continue;
            }

            if ($character === '>') {
                return $offset + 1;
            }

            $offset++;
        }

        return $length;
    }

    private static function skipTextOnlyElementContent(string $tagName, string $html, int $offset): int
    {
        if ($tagName === 'plaintext') {
            return strlen($html);
        }

        if ($tagName === 'script') {
            $close = self::consumeScriptDataEndTag($html, $offset);
            return $close === null ? strlen($html) : $close[1];
        }

        if (preg_match(sprintf('~</%s\s*>~i', preg_quote($tagName, '~')), $html, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            return strlen($html);
        }

        return $match[0][1] + strlen($match[0][0]);
    }

    /**
     * @param array{tagName: string, namespace: string, htmlIntegration: bool}|null $parent
     * @param list<array{name: string, value: string}> $attributes
     */
    private static function bodyFragmentScannerIsMathAnnotationXmlHtmlBreakoutStartTag(?array $parent, string $tagName, array $attributes): bool
    {
        return $parent !== null
            && $parent['namespace'] === Element::NAMESPACE_MATH
            && $parent['tagName'] === 'annotation-xml'
            && ! $parent['htmlIntegration']
            && self::isMathAnnotationXmlHtmlBreakoutTagName($tagName, $attributes);
    }

    /**
     * @param list<array{tagName: string, namespace: string, htmlIntegration: bool}> $stack
     */
    private static function popBodyFragmentScannerUntilHtmlInsertionContext(array &$stack): void
    {
        while ($stack !== []) {
            $parent = $stack[count($stack) - 1];
            if (self::bodyFragmentScannerIsHtmlInsertionContext($parent) || self::bodyFragmentScannerIsMathTextIntegrationPoint($parent)) {
                return;
            }

            array_pop($stack);
        }
    }

    /**
     * @param array{tagName: string, namespace: string, htmlIntegration: bool} $element
     */
    private static function bodyFragmentScannerIsHtmlInsertionContext(array $element): bool
    {
        return $element['namespace'] === Element::NAMESPACE_HTML
            || ($element['namespace'] === Element::NAMESPACE_SVG && $element['tagName'] === 'foreignobject')
            || $element['htmlIntegration'];
    }

    /**
     * @param array{tagName: string, namespace: string, htmlIntegration: bool} $element
     */
    private static function bodyFragmentScannerIsMathTextIntegrationPoint(array $element): bool
    {
        return $element['namespace'] === Element::NAMESPACE_MATH
            && (
                $element['tagName'] === 'mi'
                || $element['tagName'] === 'mn'
                || $element['tagName'] === 'mo'
                || $element['tagName'] === 'ms'
                || $element['tagName'] === 'mtext'
            );
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     */
    private static function bodyFragmentScannerIsMathAnnotationXmlHtmlIntegrationPoint(string $namespace, string $tagName, array $attributes): bool
    {
        if ($namespace !== Element::NAMESPACE_MATH || $tagName !== 'annotation-xml') {
            return false;
        }

        $encoding = self::attributeValue($attributes, 'encoding');
        if ($encoding === null) {
            return false;
        }

        return strcasecmp($encoding, 'text/html') === 0
            || strcasecmp($encoding, 'application/xhtml+xml') === 0;
    }

    private function stripLeadingDoctype(string $html): string
    {
        return preg_replace('~^[ \t\n\f\r]*<!doctype(?=[ \t\n\f\r>])[^>]*>~i', '', $html, 1) ?? $html;
    }

    private function consumeDocumentPrologueComments(string $html, bool $consumeHtmlComments): string
    {
        $offset = 0;
        $consumedComment = false;

        while (true) {
            $commentOffset = self::skipHtmlWhitespace($html, $offset);
            $comment = self::consumeDocumentPrologueComment($html, $commentOffset, $consumeHtmlComments);
            if ($comment === null) {
                return $consumedComment ? substr($html, $commentOffset) : $html;
            }

            [$data, $offset] = $comment;
            $this->insertBeforeSpec($this->createComment($data), $this->body);
            $consumedComment = true;
        }
    }

    /**
     * @return array{string, int}|null
     */
    private static function consumeDocumentPrologueComment(string $html, int $offset, bool $consumeHtmlComments): ?array
    {
        $tail = substr($html, $offset);

        if ($consumeHtmlComments && str_starts_with($tail, '<!--')) {
            return self::consumeHtmlComment($html, $offset);
        }

        if (preg_match('~^</(?<invalid>[^A-Za-z>][^>]*)(?:>|\z)|^<(?<bogus>\?[^>]*)(?:>|\z)|^<!(?!doctype)(?!--)(?<declaration>[^>]*)(?:>|\z)~si', $tail, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        $data = $match['invalid'] ?? $match['bogus'] ?? $match['declaration'] ?? '';

        return [
            str_replace("\0", "\u{FFFD}", $data),
            $offset + strlen($match[0]),
        ];
    }

    private static function skipHtmlWhitespace(string $html, int $offset): int
    {
        return $offset + strspn($html, " \t\n\f\r", $offset);
    }

    /**
     * @param array{name: string, publicId: string|null, systemId: string|null, offset: int}|null $doctype
     */
    private function setDocumentTypeFromParsedDoctype(?array $doctype): void
    {
        $this->clearDocumentTypes();

        if ($doctype === null) {
            return;
        }

        $documentType = new DocumentType(
            $doctype['name'],
            $doctype['publicId'],
            $doctype['systemId'],
            $this,
            Tag::EM_DOCTYPE,
        );

        $this->insertBeforeSpec($documentType, $this->body);
    }

    private function clearDocumentTypes(): void
    {
        for ($child = $this->firstChild; $child !== null;) {
            $next = $child->next;

            if ($child instanceof DocumentType) {
                $child->remove();
            }

            $child = $next;
        }
    }

    private function clearDocumentPrologueNodes(): void
    {
        for ($child = $this->firstChild; $child !== null;) {
            $next = $child->next;

            if ($child !== $this->body && ! $child instanceof DocumentType) {
                $child->remove();
            }

            $child = $next;
        }
    }

    /**
     * @return array{name: string, publicId: string|null, systemId: string|null, offset: int, forceQuirks?: bool}|null
     */
    private function parseLeadingDoctype(string $html): ?array
    {
        $missingNamePattern = '~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]*(?:>|$)~i';
        if (preg_match($missingNamePattern, $html, $missingNameMatch, PREG_OFFSET_CAPTURE) === 1) {
            return [
                'name' => '',
                'publicId' => null,
                'systemId' => null,
                'offset' => strlen($missingNameMatch[0][0]),
                'forceQuirks' => true,
            ];
        }

        $verticalTabAfterDoctypeKeywordPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+(?:PUBLIC|SYSTEM)[ \t\n\f\r]*\x0B[^>]*(?:>|$)~is
REGEX;

        if (preg_match($verticalTabAfterDoctypeKeywordPattern, $html, $verticalTabAfterDoctypeKeywordMatch, PREG_OFFSET_CAPTURE) === 1) {
            return [
                'name' => self::normalizeDoctypeToken($verticalTabAfterDoctypeKeywordMatch['name'][0]),
                'publicId' => null,
                'systemId' => null,
                'offset' => strlen($verticalTabAfterDoctypeKeywordMatch[0][0]),
            ];
        }

        $verticalTabAfterPublicIdentifierPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*\x0B[^>]*(?:>|$)~is
REGEX;

        if (preg_match($verticalTabAfterPublicIdentifierPattern, $html, $verticalTabAfterPublicIdentifierMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $publicId = $verticalTabAfterPublicIdentifierMatch['publicIdDouble'][0] ?? $verticalTabAfterPublicIdentifierMatch['publicIdSingle'][0] ?? null;

            return [
                'name' => self::normalizeDoctypeToken($verticalTabAfterPublicIdentifierMatch['name'][0]),
                'publicId' => self::normalizeDoctypeIdentifier($publicId),
                'systemId' => null,
                'offset' => strlen($verticalTabAfterPublicIdentifierMatch[0][0]),
            ];
        }

        $abruptPublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)>|'(?<systemIdSingle>[^'>]*)>)~is
REGEX;

        if (preg_match($abruptPublicSystemPattern, $html, $abruptPublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $publicId = $abruptPublicSystemMatch['publicIdDouble'][0] ?? $abruptPublicSystemMatch['publicIdSingle'][0] ?? null;
            $systemId = $abruptPublicSystemMatch['systemIdDouble'][0] ?? $abruptPublicSystemMatch['systemIdSingle'][0] ?? null;

            return [
                'name' => self::normalizeDoctypeToken($abruptPublicSystemMatch['name'][0]),
                'publicId' => self::normalizeDoctypeIdentifier($publicId),
                'systemId' => self::normalizeDoctypeIdentifier($systemId),
                'offset' => strlen($abruptPublicSystemMatch[0][0]),
            ];
        }

        $abruptPublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)>|'(?<publicIdSingle>[^'>]*)>)~is
REGEX;

        if (preg_match($abruptPublicPattern, $html, $abruptPublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $publicId = $abruptPublicMatch['publicIdDouble'][0] ?? $abruptPublicMatch['publicIdSingle'][0] ?? null;

            return [
                'name' => self::normalizeDoctypeToken($abruptPublicMatch['name'][0]),
                'publicId' => self::normalizeDoctypeIdentifier($publicId),
                'systemId' => null,
                'offset' => strlen($abruptPublicMatch[0][0]),
            ];
        }

        $abruptSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)>|'(?<systemIdSingle>[^'>]*)>)~is
REGEX;

        if (preg_match($abruptSystemPattern, $html, $abruptSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $systemId = $abruptSystemMatch['systemIdDouble'][0] ?? $abruptSystemMatch['systemIdSingle'][0] ?? null;

            return [
                'name' => self::normalizeDoctypeToken($abruptSystemMatch['name'][0]),
                'publicId' => null,
                'systemId' => self::normalizeDoctypeIdentifier($systemId),
                'offset' => strlen($abruptSystemMatch[0][0]),
            ];
        }

        $verticalTabPublicSystemGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*\x0B[^>]*(?:>|$)~is
REGEX;

        if (preg_match($verticalTabPublicSystemGarbagePattern, $html, $verticalTabPublicSystemGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $publicId = $verticalTabPublicSystemGarbageMatch['publicIdDouble'][0] ?? $verticalTabPublicSystemGarbageMatch['publicIdSingle'][0] ?? null;
            $systemId = $verticalTabPublicSystemGarbageMatch['systemIdDouble'][0] ?? $verticalTabPublicSystemGarbageMatch['systemIdSingle'][0] ?? null;

            return [
                'name' => self::normalizeDoctypeToken($verticalTabPublicSystemGarbageMatch['name'][0]),
                'publicId' => self::normalizeDoctypeIdentifier($publicId),
                'systemId' => self::normalizeDoctypeIdentifier($systemId),
                'offset' => strlen($verticalTabPublicSystemGarbageMatch[0][0]),
                'forceQuirks' => true,
            ];
        }

        $verticalTabSystemGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*\x0B[^>]*(?:>|$)~is
REGEX;

        if (preg_match($verticalTabSystemGarbagePattern, $html, $verticalTabSystemGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $systemId = $verticalTabSystemGarbageMatch['systemIdDouble'][0] ?? $verticalTabSystemGarbageMatch['systemIdSingle'][0] ?? null;

            return [
                'name' => self::normalizeDoctypeToken($verticalTabSystemGarbageMatch['name'][0]),
                'publicId' => null,
                'systemId' => self::normalizeDoctypeIdentifier($systemId),
                'offset' => strlen($verticalTabSystemGarbageMatch[0][0]),
                'forceQuirks' => true,
            ];
        }

        $emptyIdentifierNoWhitespacePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)(?:
    [ \t\n\f\r]+(?<keyword>PUBLIC|SYSTEM)(?:""|'')
)>~isx
REGEX;

        if (preg_match($emptyIdentifierNoWhitespacePattern, $html, $emptyIdentifierNoWhitespaceMatch, PREG_OFFSET_CAPTURE) === 1) {
            $keyword = strtoupper($emptyIdentifierNoWhitespaceMatch['keyword'][0]);

            return [
                'name' => self::normalizeDoctypeToken($emptyIdentifierNoWhitespaceMatch['name'][0]),
                'publicId' => $keyword === 'PUBLIC' ? '' : null,
                'systemId' => $keyword === 'SYSTEM' ? '' : null,
                'offset' => strlen($emptyIdentifierNoWhitespaceMatch[0][0]),
                'forceQuirks' => true,
            ];
        }

        $pattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)(?:
    [ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)"|'(?<publicIdSingle>[^']*)')(?:[ \t\n\f\r]+(?:"(?<publicSystemIdDouble>[^"]*)"|'(?<publicSystemIdSingle>[^']*)'))?
  | [ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)"|'(?<systemIdSingle>[^']*)')
)?[ \t\n\f\r]*>~isx
REGEX;

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) !== 1) {
            $eofClosedPublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)"|'(?<publicIdSingle>[^']*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)"|'(?<systemIdSingle>[^']*)')[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofClosedPublicSystemPattern, $html, $eofClosedPublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofClosedPublicSystemMatch['publicIdDouble'][0] ?? $eofClosedPublicSystemMatch['publicIdSingle'][0] ?? null;
                $systemId = $eofClosedPublicSystemMatch['systemIdDouble'][0] ?? $eofClosedPublicSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofClosedPublicSystemMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofClosedPublicSystemMatch[0][0]),
                ];
            }

            $eofPublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)"|'(?<publicIdSingle>[^']*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)|'(?<systemIdSingle>[^']*))[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofPublicSystemPattern, $html, $eofPublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofPublicSystemMatch['publicIdDouble'][0] ?? $eofPublicSystemMatch['publicIdSingle'][0] ?? null;
                $systemId = $eofPublicSystemMatch['systemIdDouble'][0] ?? $eofPublicSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofPublicSystemMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofPublicSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $trailingPublicSystemGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)"|'(?<publicIdSingle>[^']*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)"|'(?<systemIdSingle>[^']*)')[ \t\n\f\r]*[^> \t\n\f\r][^>]*(?:>|$)~is
REGEX;

            if (preg_match($trailingPublicSystemGarbagePattern, $html, $trailingPublicSystemGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $trailingPublicSystemGarbageMatch['publicIdDouble'][0] ?? $trailingPublicSystemGarbageMatch['publicIdSingle'][0] ?? null;
                $systemId = $trailingPublicSystemGarbageMatch['systemIdDouble'][0] ?? $trailingPublicSystemGarbageMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($trailingPublicSystemGarbageMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($trailingPublicSystemGarbageMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $missingSystemQuoteAfterPublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)"|'(?<publicIdSingle>[^']*)')[ \t\n\f\r]*[^"' \t\n\f\r>][^>]*(?:>|$)~is
REGEX;

            if (preg_match($missingSystemQuoteAfterPublicPattern, $html, $missingSystemQuoteAfterPublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $missingSystemQuoteAfterPublicMatch['publicIdDouble'][0] ?? $missingSystemQuoteAfterPublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($missingSystemQuoteAfterPublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($missingSystemQuoteAfterPublicMatch[0][0]),
                ];
            }

            $eofClosedPublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)"|'(?<publicIdSingle>[^']*)')[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofClosedPublicPattern, $html, $eofClosedPublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofClosedPublicMatch['publicIdDouble'][0] ?? $eofClosedPublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofClosedPublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($eofClosedPublicMatch[0][0]),
                ];
            }

            $eofPublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^"]*)|'(?<publicIdSingle>[^']*))[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofPublicPattern, $html, $eofPublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofPublicMatch['publicIdDouble'][0] ?? $eofPublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofPublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($eofPublicMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofClosedSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)"|'(?<systemIdSingle>[^']*)')[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofClosedSystemPattern, $html, $eofClosedSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $eofClosedSystemMatch['systemIdDouble'][0] ?? $eofClosedSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofClosedSystemMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofClosedSystemMatch[0][0]),
                ];
            }

            $trailingSystemGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)"|'(?<systemIdSingle>[^']*)')[ \t\n\f\r]*[^> \t\n\f\r][^>]*(?:>|$)~is
REGEX;

            if (preg_match($trailingSystemGarbagePattern, $html, $trailingSystemGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $trailingSystemGarbageMatch['systemIdDouble'][0] ?? $trailingSystemGarbageMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($trailingSystemGarbageMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($trailingSystemGarbageMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^"]*)|'(?<systemIdSingle>[^']*))[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofSystemPattern, $html, $eofSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $eofSystemMatch['systemIdDouble'][0] ?? $eofSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofSystemMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $missingPublicQuotePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*[^"' \t\n\f\r>][^>]*(?:>|$)~is
REGEX;

            if (preg_match($missingPublicQuotePattern, $html, $missingPublicQuoteMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($missingPublicQuoteMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($missingPublicQuoteMatch[0][0]),
                ];
            }

            $missingSystemQuotePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*[^"' \t\n\f\r>][^>]*(?:>|$)~is
REGEX;

            if (preg_match($missingSystemQuotePattern, $html, $missingSystemQuoteMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($missingSystemQuoteMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($missingSystemQuoteMatch[0][0]),
                ];
            }

            $eofPublicKeywordPattern = '~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"\']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*$~i';
            if (preg_match($eofPublicKeywordPattern, $html, $eofPublicKeywordMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($eofPublicKeywordMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($eofPublicKeywordMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofSystemKeywordPattern = '~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"\']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*$~i';
            if (preg_match($eofSystemKeywordPattern, $html, $eofSystemKeywordMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($eofSystemKeywordMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($eofSystemKeywordMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $noWhitespaceNamePattern = '~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"\']+)[ \t\n\f\r]*>~i';
            if (preg_match($noWhitespaceNamePattern, $html, $noWhitespaceNameMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($noWhitespaceNameMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($noWhitespaceNameMatch[0][0]),
                ];
            }

            $eofNoWhitespaceNamePattern = '~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>]+)[ \t\n\f\r]*$~i';
            if (preg_match($eofNoWhitespaceNamePattern, $html, $eofNoWhitespaceNameMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($eofNoWhitespaceNameMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($eofNoWhitespaceNameMatch[0][0]),
                ];
            }

            $closedNoWhitespacePublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*>~is
REGEX;

            if (preg_match($closedNoWhitespacePublicSystemPattern, $html, $closedNoWhitespacePublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $closedNoWhitespacePublicSystemMatch['publicIdDouble'][0] ?? $closedNoWhitespacePublicSystemMatch['publicIdSingle'][0] ?? null;
                $systemId = $closedNoWhitespacePublicSystemMatch['systemIdDouble'][0] ?? $closedNoWhitespacePublicSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($closedNoWhitespacePublicSystemMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($closedNoWhitespacePublicSystemMatch[0][0]),
                ];
            }

            $abruptNoWhitespacePublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)>|'(?<systemIdSingle>[^'>]*)>)~is
REGEX;

            if (preg_match($abruptNoWhitespacePublicSystemPattern, $html, $abruptNoWhitespacePublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $abruptNoWhitespacePublicSystemMatch['publicIdDouble'][0] ?? $abruptNoWhitespacePublicSystemMatch['publicIdSingle'][0] ?? null;
                $systemId = $abruptNoWhitespacePublicSystemMatch['systemIdDouble'][0] ?? $abruptNoWhitespacePublicSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($abruptNoWhitespacePublicSystemMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($abruptNoWhitespacePublicSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofClosedNoWhitespacePublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofClosedNoWhitespacePublicSystemPattern, $html, $eofClosedNoWhitespacePublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofClosedNoWhitespacePublicSystemMatch['publicIdDouble'][0] ?? $eofClosedNoWhitespacePublicSystemMatch['publicIdSingle'][0] ?? null;
                $systemId = $eofClosedNoWhitespacePublicSystemMatch['systemIdDouble'][0] ?? $eofClosedNoWhitespacePublicSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofClosedNoWhitespacePublicSystemMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofClosedNoWhitespacePublicSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofNoWhitespacePublicSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)|'(?<systemIdSingle>[^'>]*))[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofNoWhitespacePublicSystemPattern, $html, $eofNoWhitespacePublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofNoWhitespacePublicSystemMatch['publicIdDouble'][0] ?? $eofNoWhitespacePublicSystemMatch['publicIdSingle'][0] ?? null;
                $systemId = $eofNoWhitespacePublicSystemMatch['systemIdDouble'][0] ?? $eofNoWhitespacePublicSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofNoWhitespacePublicSystemMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofNoWhitespacePublicSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $trailingClosedNoWhitespacePublicSystemGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*[^> \t\n\f\r][^>]*(?:>|$)~is
REGEX;

            if (preg_match($trailingClosedNoWhitespacePublicSystemGarbagePattern, $html, $trailingClosedNoWhitespacePublicSystemGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $trailingClosedNoWhitespacePublicSystemGarbageMatch['publicIdDouble'][0] ?? $trailingClosedNoWhitespacePublicSystemGarbageMatch['publicIdSingle'][0] ?? null;
                $systemId = $trailingClosedNoWhitespacePublicSystemGarbageMatch['systemIdDouble'][0] ?? $trailingClosedNoWhitespacePublicSystemGarbageMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($trailingClosedNoWhitespacePublicSystemGarbageMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($trailingClosedNoWhitespacePublicSystemGarbageMatch[0][0]),
                ];
            }

            $closedNoWhitespacePublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*>~is
REGEX;

            if (preg_match($closedNoWhitespacePublicPattern, $html, $closedNoWhitespacePublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $closedNoWhitespacePublicMatch['publicIdDouble'][0] ?? $closedNoWhitespacePublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($closedNoWhitespacePublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($closedNoWhitespacePublicMatch[0][0]),
                ];
            }

            $closedNoWhitespaceSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*>~is
REGEX;

            if (preg_match($closedNoWhitespaceSystemPattern, $html, $closedNoWhitespaceSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $closedNoWhitespaceSystemMatch['systemIdDouble'][0] ?? $closedNoWhitespaceSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($closedNoWhitespaceSystemMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($closedNoWhitespaceSystemMatch[0][0]),
                ];
            }

            $eofClosedNoWhitespacePublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofClosedNoWhitespacePublicPattern, $html, $eofClosedNoWhitespacePublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofClosedNoWhitespacePublicMatch['publicIdDouble'][0] ?? $eofClosedNoWhitespacePublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofClosedNoWhitespacePublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($eofClosedNoWhitespacePublicMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $trailingClosedNoWhitespacePublicGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')[ \t\n\f\r]*[^"' \t\n\f\r>][^>]*(?:>|$)~is
REGEX;

            if (preg_match($trailingClosedNoWhitespacePublicGarbagePattern, $html, $trailingClosedNoWhitespacePublicGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $trailingClosedNoWhitespacePublicGarbageMatch['publicIdDouble'][0] ?? $trailingClosedNoWhitespacePublicGarbageMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($trailingClosedNoWhitespacePublicGarbageMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($trailingClosedNoWhitespacePublicGarbageMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofClosedNoWhitespaceSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofClosedNoWhitespaceSystemPattern, $html, $eofClosedNoWhitespaceSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $eofClosedNoWhitespaceSystemMatch['systemIdDouble'][0] ?? $eofClosedNoWhitespaceSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofClosedNoWhitespaceSystemMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofClosedNoWhitespaceSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $trailingClosedNoWhitespaceSystemGarbagePattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)"|'(?<systemIdSingle>[^'>]*)')[ \t\n\f\r]*[^> \t\n\f\r][^>]*(?:>|$)~is
REGEX;

            if (preg_match($trailingClosedNoWhitespaceSystemGarbagePattern, $html, $trailingClosedNoWhitespaceSystemGarbageMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $trailingClosedNoWhitespaceSystemGarbageMatch['systemIdDouble'][0] ?? $trailingClosedNoWhitespaceSystemGarbageMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($trailingClosedNoWhitespaceSystemGarbageMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($trailingClosedNoWhitespaceSystemGarbageMatch[0][0]),
                ];
            }

            $eofNoWhitespacePublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)|'(?<publicIdSingle>[^'>]*))[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofNoWhitespacePublicPattern, $html, $eofNoWhitespacePublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $eofNoWhitespacePublicMatch['publicIdDouble'][0] ?? $eofNoWhitespacePublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofNoWhitespacePublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($eofNoWhitespacePublicMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofNoWhitespaceSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)|'(?<systemIdSingle>[^'>]*))[ \t\n\f\r]*$~is
REGEX;

            if (preg_match($eofNoWhitespaceSystemPattern, $html, $eofNoWhitespaceSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $eofNoWhitespaceSystemMatch['systemIdDouble'][0] ?? $eofNoWhitespaceSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($eofNoWhitespaceSystemMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($eofNoWhitespaceSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $abruptNoWhitespacePublicPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:"(?<publicIdDouble>[^">]*)>|'(?<publicIdSingle>[^'>]*)>)~is
REGEX;

            if (preg_match($abruptNoWhitespacePublicPattern, $html, $abruptNoWhitespacePublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $publicId = $abruptNoWhitespacePublicMatch['publicIdDouble'][0] ?? $abruptNoWhitespacePublicMatch['publicIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($abruptNoWhitespacePublicMatch['name'][0]),
                    'publicId' => self::normalizeDoctypeIdentifier($publicId),
                    'systemId' => null,
                    'offset' => strlen($abruptNoWhitespacePublicMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $abruptNoWhitespaceSystemPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:"(?<systemIdDouble>[^">]*)>|'(?<systemIdSingle>[^'>]*)>)~is
REGEX;

            if (preg_match($abruptNoWhitespaceSystemPattern, $html, $abruptNoWhitespaceSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
                $systemId = $abruptNoWhitespaceSystemMatch['systemIdDouble'][0] ?? $abruptNoWhitespaceSystemMatch['systemIdSingle'][0] ?? null;

                return [
                    'name' => self::normalizeDoctypeToken($abruptNoWhitespaceSystemMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => self::normalizeDoctypeIdentifier($systemId),
                    'offset' => strlen($abruptNoWhitespaceSystemMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $noWhitespaceMissingPublicIdentifierPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+PUBLIC[ \t\n\f\r]*(?:>|$|[^"' \t\n\f\r>][^>]*(?:>|$))~is
REGEX;

            if (preg_match($noWhitespaceMissingPublicIdentifierPattern, $html, $noWhitespaceMissingPublicIdentifierMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($noWhitespaceMissingPublicIdentifierMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($noWhitespaceMissingPublicIdentifierMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $noWhitespaceMissingSystemIdentifierPattern = <<<'REGEX'
~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"']+)[ \t\n\f\r]+SYSTEM[ \t\n\f\r]*(?:>|$|[^"' \t\n\f\r>][^>]*(?:>|$))~is
REGEX;

            if (preg_match($noWhitespaceMissingSystemIdentifierPattern, $html, $noWhitespaceMissingSystemIdentifierMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($noWhitespaceMissingSystemIdentifierMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($noWhitespaceMissingSystemIdentifierMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $eofNamePattern = '~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>]+)[ \t\n\f\r]*$~i';
            if (preg_match($eofNamePattern, $html, $eofMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($eofMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($eofMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            $noWhitespaceInvalidAfterNamePattern = '~^[ \t\n\f\r]*<!doctype(?<name>[^ \t\n\f\r>"\']+)[^>]*(?:>|$)~i';
            if (preg_match($noWhitespaceInvalidAfterNamePattern, $html, $noWhitespaceInvalidAfterNameMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($noWhitespaceInvalidAfterNameMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($noWhitespaceInvalidAfterNameMatch[0][0]),
                ];
            }

            $invalidAfterNamePattern = '~^[ \t\n\f\r]*<!doctype[ \t\n\f\r]+(?<name>[^ \t\n\f\r>"\']+)[^>]*(?:>|$)~i';
            if (preg_match($invalidAfterNamePattern, $html, $invalidAfterNameMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => self::normalizeDoctypeToken($invalidAfterNameMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($invalidAfterNameMatch[0][0]),
                    'forceQuirks' => true,
                ];
            }

            return null;
        }

        $publicId = $match['publicIdDouble'][0] ?? $match['publicIdSingle'][0] ?? null;
        $systemId = $match['publicSystemIdDouble'][0] ?? $match['publicSystemIdSingle'][0] ?? $match['systemIdDouble'][0] ?? $match['systemIdSingle'][0] ?? null;

        return [
            'name' => self::normalizeDoctypeToken($match['name'][0]),
            'publicId' => self::normalizeDoctypeIdentifier($publicId),
            'systemId' => self::normalizeDoctypeIdentifier($systemId),
            'offset' => strlen($match[0][0]),
        ];
    }

    private static function normalizeDoctypeToken(string $token): string
    {
        return str_replace("\0", "\u{FFFD}", strtolower($token));
    }

    private static function normalizeDoctypeIdentifier(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        return str_replace("\0", "\u{FFFD}", $identifier);
    }

    /**
     * @param array{name: string, publicId: string|null, systemId: string|null, offset: int, forceQuirks?: bool}|null $doctype
     */
    private static function doctypeRequiresQuirks(?array $doctype): bool
    {
        if ($doctype === null) {
            return true;
        }

        if ($doctype['forceQuirks'] ?? false) {
            return true;
        }

        if ($doctype['name'] !== 'html') {
            return true;
        }

        $publicId = $doctype['publicId'] ?? '';
        $systemId = $doctype['systemId'] ?? '';

        if ($publicId !== '') {
            foreach (self::quirksPublicIdentifierEquals() as $candidate) {
                if (strcasecmp($publicId, $candidate) === 0) {
                    return true;
                }
            }

            foreach (self::quirksPublicIdentifierPrefixes() as $prefix) {
                if (strncasecmp($publicId, $prefix, strlen($prefix)) === 0) {
                    return true;
                }
            }

            if ($systemId === '') {
                foreach (self::quirksPublicIdentifierPrefixesWhenSystemMissing() as $prefix) {
                    if (strncasecmp($publicId, $prefix, strlen($prefix)) === 0) {
                        return true;
                    }
                }
            }
        }

        if ($systemId !== '') {
            foreach (self::quirksSystemIdentifierEquals() as $candidate) {
                if (strcasecmp($systemId, $candidate) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function quirksPublicIdentifierEquals(): array
    {
        return [
            '-//W3O//DTD W3 HTML Strict 3.0//EN//',
            '-/W3C/DTD HTML 4.0 Transitional/EN',
            'HTML',
        ];
    }

    /**
     * @return list<string>
     */
    private static function quirksSystemIdentifierEquals(): array
    {
        return [
            'http://www.ibm.com/data/dtd/v11/ibmxhtml1-transitional.dtd',
        ];
    }

    /**
     * @return list<string>
     */
    private static function quirksPublicIdentifierPrefixes(): array
    {
        return [
            '+//Silmaril//dtd html Pro v0r11 19970101//',
            '-//AS//DTD HTML 3.0 asWedit + extensions//',
            '-//AdvaSoft Ltd//DTD HTML 3.0 asWedit + extensions//',
            '-//IETF//DTD HTML 2.0 Level 1//',
            '-//IETF//DTD HTML 2.0 Level 2//',
            '-//IETF//DTD HTML 2.0 Strict Level 1//',
            '-//IETF//DTD HTML 2.0 Strict Level 2//',
            '-//IETF//DTD HTML 2.0 Strict//',
            '-//IETF//DTD HTML 2.0//',
            '-//IETF//DTD HTML 2.1E//',
            '-//IETF//DTD HTML 3.0//',
            '-//IETF//DTD HTML 3.2 Final//',
            '-//IETF//DTD HTML 3.2//',
            '-//IETF//DTD HTML 3//',
            '-//IETF//DTD HTML Level 0//',
            '-//IETF//DTD HTML Level 1//',
            '-//IETF//DTD HTML Level 2//',
            '-//IETF//DTD HTML Level 3//',
            '-//IETF//DTD HTML Strict Level 0//',
            '-//IETF//DTD HTML Strict Level 1//',
            '-//IETF//DTD HTML Strict Level 2//',
            '-//IETF//DTD HTML Strict Level 3//',
            '-//IETF//DTD HTML Strict//',
            '-//IETF//DTD HTML//',
            '-//Metrius//DTD Metrius Presentational//',
            '-//Microsoft//DTD Internet Explorer 2.0 HTML Strict//',
            '-//Microsoft//DTD Internet Explorer 2.0 HTML//',
            '-//Microsoft//DTD Internet Explorer 2.0 Tables//',
            '-//Microsoft//DTD Internet Explorer 3.0 HTML Strict//',
            '-//Microsoft//DTD Internet Explorer 3.0 HTML//',
            '-//Microsoft//DTD Internet Explorer 3.0 Tables//',
            '-//Netscape Comm. Corp.//DTD HTML//',
            '-//Netscape Comm. Corp.//DTD Strict HTML//',
            '-//O\'Reilly and Associates//DTD HTML 2.0//',
            '-//O\'Reilly and Associates//DTD HTML Extended 1.0//',
            '-//O\'Reilly and Associates//DTD HTML Extended Relaxed 1.0//',
            '-//SQ//DTD HTML 2.0 HoTMetaL + extensions//',
            '-//SoftQuad Software//DTD HoTMetaL PRO 6.0::19990601::extensions to HTML 4.0//',
            '-//SoftQuad//DTD HoTMetaL PRO 4.0::19971010::extensions to HTML 4.0//',
            '-//Spyglass//DTD HTML 2.0 Extended//',
            '-//Sun Microsystems Corp.//DTD HotJava HTML//',
            '-//Sun Microsystems Corp.//DTD HotJava Strict HTML//',
            '-//W3C//DTD HTML 3 1995-03-24//',
            '-//W3C//DTD HTML 3.2 Draft//',
            '-//W3C//DTD HTML 3.2 Final//',
            '-//W3C//DTD HTML 3.2//',
            '-//W3C//DTD HTML 3.2S Draft//',
            '-//W3C//DTD HTML 4.0 Frameset//',
            '-//W3C//DTD HTML 4.0 Transitional//',
            '-//W3C//DTD HTML Experimental 19960712//',
            '-//W3C//DTD HTML Experimental 970421//',
            '-//W3C//DTD W3 HTML//',
            '-//W3O//DTD W3 HTML 3.0//',
            '-//WebTechs//DTD Mozilla HTML 2.0//',
            '-//WebTechs//DTD Mozilla HTML//',
        ];
    }

    /**
     * @return list<string>
     */
    private static function quirksPublicIdentifierPrefixesWhenSystemMissing(): array
    {
        return [
            '-//W3C//DTD HTML 4.01 Frameset//',
            '-//W3C//DTD HTML 4.01 Transitional//',
        ];
    }

    private function isTextOnlyFragmentContext(Element $context): bool
    {
        return self::isTextOnlyTagName($context->tagName, $this->isScriptingEnabled());
    }

    private static function isTextOnlyTagName(string $tagName, bool $scripting = false): bool
    {
        return in_array($tagName, [
            'textarea',
            'title',
            'style',
            'script',
            'xmp',
            'iframe',
            'noembed',
            'noframes',
            'plaintext',
        ], true)
            || ($tagName === 'noscript' && $scripting);
    }

    private function shouldDecodeTextOnlyElementContent(Element $element): bool
    {
        return in_array($element->tagName, ['textarea', 'title'], true);
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function parseAttributes(string $source): array
    {
        $attributes = [];
        $seen = [];
        $length = strlen($source);
        $offset = 0;
        $name = '';
        $value = '';
        $state = 'before_attribute_name';

        $commit = function () use (&$attributes, &$seen, &$name, &$value): void {
            if ($name === '') {
                return;
            }

            $normalizedName = self::normalizeAttributeTokenName($name);
            $seenKey = "\0" . $normalizedName;
            if (!array_key_exists($seenKey, $seen)) {
                $seen[$seenKey] = true;
                $attributes[] = [
                    'name' => $normalizedName,
                    'value' => str_replace("\0", "\u{FFFD}", $this->decodeCharacterReferences($value, true)),
                ];
            }

            $name = '';
            $value = '';
        };

        while ($offset <= $length) {
            $character = $offset < $length ? $source[$offset] : null;

            switch ($state) {
                case 'before_attribute_name':
                    if ($character === null) {
                        break 2;
                    }

                    if (str_contains(" \t\n\r\f", $character) || $character === '/') {
                        break;
                    }

                    $name = $character;
                    $value = '';
                    $state = 'attribute_name';
                    break;

                case 'attribute_name':
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if (str_contains(" \t\n\r\f", $character) || $character === '/') {
                        $state = 'after_attribute_name';
                    } elseif ($character === '=') {
                        $state = 'before_attribute_value';
                    } else {
                        $name .= $character;
                    }
                    break;

                case 'after_attribute_name':
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        break;
                    }

                    if ($character === '=') {
                        $state = 'before_attribute_value';
                    } else {
                        $commit();
                        if ($character !== '/') {
                            $name = $character;
                            $value = '';
                            $state = 'attribute_name';
                        } else {
                            $state = 'before_attribute_name';
                        }
                    }
                    break;

                case 'before_attribute_value':
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        break;
                    }

                    if ($character === '"') {
                        $state = 'double_quoted_attribute_value';
                    } elseif ($character === "'") {
                        $state = 'single_quoted_attribute_value';
                    } else {
                        $value .= $character;
                        $state = 'unquoted_attribute_value';
                    }
                    break;

                case 'double_quoted_attribute_value':
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if ($character === '"') {
                        $state = 'after_quoted_attribute_value';
                    } else {
                        $value .= $character;
                    }
                    break;

                case 'single_quoted_attribute_value':
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if ($character === "'") {
                        $state = 'after_quoted_attribute_value';
                    } else {
                        $value .= $character;
                    }
                    break;

                case 'after_quoted_attribute_value':
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        $commit();
                        $state = 'before_attribute_name';
                    } else {
                        $commit();
                        if ($character !== '/') {
                            $name = $character;
                            $value = '';
                            $state = 'attribute_name';
                        } else {
                            $state = 'before_attribute_name';
                        }
                    }
                    break;

                default:
                    if ($character === null) {
                        $commit();
                        break 2;
                    }

                    if (str_contains(" \t\n\r\f", $character)) {
                        $commit();
                        $state = 'before_attribute_name';
                    } else {
                        $value .= $character;
                    }
                    break;
            }

            $offset++;
        }

        return $attributes;
    }

    private static function normalizeAttributeTokenName(string $name): string
    {
        return str_replace("\0", "\u{FFFD}", strtolower($name));
    }

    private static function rtrimHtmlWhitespace(string $data): string
    {
        return rtrim($data, " \t\n\r\f");
    }

    private function decodeCharacterReferences(string $data, bool $attribute = false): string
    {
        $decoded = '';
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $referenceStart = strpos($data, '&', $offset);
            if ($referenceStart === false) {
                $decoded .= substr($data, $offset);
                break;
            }

            $decoded .= substr($data, $offset, $referenceStart - $offset);

            $reference = ($data[$referenceStart + 1] ?? '') === '#'
                ? $this->consumeNumericCharacterReference($data, $referenceStart)
                : $this->consumeNamedCharacterReference($data, $referenceStart, $attribute);

            if ($reference === null) {
                $decoded .= '&';
                $offset = $referenceStart + 1;
                continue;
            }

            $decoded .= $reference['data'];
            $offset = $reference['offset'];
        }

        return $decoded;
    }

    private static function normalizeTokenizedNewlines(string $data): string
    {
        return str_replace(["\r\n", "\r"], "\n", $data);
    }

    /**
     * @return array{data: string, offset: int}|null
     */
    private function consumeNamedCharacterReference(string $data, int $referenceStart, bool $attribute): ?array
    {
        $nameStart = $referenceStart + 1;
        $offset = $nameStart;
        $length = strlen($data);

        while ($offset < $length && preg_match('/[A-Za-z0-9]/', $data[$offset]) === 1) {
            $offset++;
        }

        if ($offset === $nameStart) {
            return null;
        }

        $name = substr($data, $nameStart, $offset - $nameStart);
        $hasSemicolon = ($data[$offset] ?? '') === ';';

        if ($hasSemicolon) {
            $reference = self::decodeNamedCharacterReferenceName($name);
            if ($reference !== null) {
                return ['data' => $reference, 'offset' => $offset + 1];
            }
        }

        $legacyReferences = self::legacyNamedCharacterReferences();
        for ($nameLength = strlen($name); $nameLength > 0; $nameLength--) {
            $candidate = substr($name, 0, $nameLength);

            if (!isset($legacyReferences[$candidate])) {
                continue;
            }

            $nextOffset = $nameStart + $nameLength;
            $next = $data[$nextOffset] ?? '';
            if ($attribute && ($next === '=' || preg_match('/[A-Za-z0-9]/', $next) === 1)) {
                return null;
            }

            return ['data' => $legacyReferences[$candidate], 'offset' => $nextOffset];
        }

        return null;
    }

    /**
     * @return array{data: string, offset: int}|null
     */
    private function consumeNumericCharacterReference(string $data, int $referenceStart): ?array
    {
        $offset = $referenceStart + 2;
        $length = strlen($data);
        $hexadecimal = false;

        if (($data[$offset] ?? '') === 'x' || ($data[$offset] ?? '') === 'X') {
            $hexadecimal = true;
            $offset++;
        }

        $digitsStart = $offset;
        while ($offset < $length && ($hexadecimal ? ctype_xdigit($data[$offset]) : ctype_digit($data[$offset]))) {
            $offset++;
        }

        if ($offset === $digitsStart) {
            return null;
        }

        $digits = substr($data, $digitsStart, $offset - $digitsStart);
        $codePoint = intval($digits, $hexadecimal ? 16 : 10);

        if (($data[$offset] ?? '') === ';') {
            $offset++;
        }

        return [
            'data' => self::codePointToUtf8(self::normalizeCharacterReferenceCodePoint($codePoint)),
            'offset' => $offset,
        ];
    }

    private static function decodeNamedCharacterReferenceName(string $name): ?string
    {
        $reference = sprintf('&%s;', $name);
        $decoded = html_entity_decode($reference, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');

        return $decoded === $reference ? null : $decoded;
    }

    /**
     * @return array<string, string>
     */
    private static function legacyNamedCharacterReferences(): array
    {
        static $references = null;
        if ($references !== null) {
            return $references;
        }

        return $references = [
            'AElig' => 'Æ',
            'AMP' => '&',
            'Aacute' => 'Á',
            'Acirc' => 'Â',
            'Agrave' => 'À',
            'Aring' => 'Å',
            'Atilde' => 'Ã',
            'Auml' => 'Ä',
            'COPY' => '©',
            'Ccedil' => 'Ç',
            'ETH' => 'Ð',
            'Eacute' => 'É',
            'Ecirc' => 'Ê',
            'Egrave' => 'È',
            'Euml' => 'Ë',
            'GT' => '>',
            'Iacute' => 'Í',
            'Icirc' => 'Î',
            'Igrave' => 'Ì',
            'Iuml' => 'Ï',
            'LT' => '<',
            'Ntilde' => 'Ñ',
            'Oacute' => 'Ó',
            'Ocirc' => 'Ô',
            'Ograve' => 'Ò',
            'Oslash' => 'Ø',
            'Otilde' => 'Õ',
            'Ouml' => 'Ö',
            'QUOT' => '"',
            'REG' => '®',
            'THORN' => 'Þ',
            'Uacute' => 'Ú',
            'Ucirc' => 'Û',
            'Ugrave' => 'Ù',
            'Uuml' => 'Ü',
            'Yacute' => 'Ý',
            'aacute' => 'á',
            'acirc' => 'â',
            'acute' => '´',
            'aelig' => 'æ',
            'agrave' => 'à',
            'amp' => '&',
            'aring' => 'å',
            'atilde' => 'ã',
            'auml' => 'ä',
            'brvbar' => '¦',
            'ccedil' => 'ç',
            'cedil' => '¸',
            'cent' => '¢',
            'copy' => '©',
            'curren' => '¤',
            'deg' => '°',
            'divide' => '÷',
            'eacute' => 'é',
            'ecirc' => 'ê',
            'egrave' => 'è',
            'eth' => 'ð',
            'euml' => 'ë',
            'frac12' => '½',
            'frac14' => '¼',
            'frac34' => '¾',
            'gt' => '>',
            'iacute' => 'í',
            'icirc' => 'î',
            'iexcl' => '¡',
            'igrave' => 'ì',
            'iquest' => '¿',
            'iuml' => 'ï',
            'laquo' => '«',
            'lt' => '<',
            'macr' => '¯',
            'micro' => 'µ',
            'middot' => '·',
            'nbsp' => ' ',
            'not' => '¬',
            'ntilde' => 'ñ',
            'oacute' => 'ó',
            'ocirc' => 'ô',
            'ograve' => 'ò',
            'ordf' => 'ª',
            'ordm' => 'º',
            'oslash' => 'ø',
            'otilde' => 'õ',
            'ouml' => 'ö',
            'para' => '¶',
            'plusmn' => '±',
            'pound' => '£',
            'quot' => '"',
            'raquo' => '»',
            'reg' => '®',
            'sect' => '§',
            'shy' => '­',
            'sup1' => '¹',
            'sup2' => '²',
            'sup3' => '³',
            'szlig' => 'ß',
            'thorn' => 'þ',
            'times' => '×',
            'uacute' => 'ú',
            'ucirc' => 'û',
            'ugrave' => 'ù',
            'uml' => '¨',
            'uuml' => 'ü',
            'yacute' => 'ý',
            'yen' => '¥',
            'yuml' => 'ÿ',
        ];
    }

    private static function normalizeCharacterReferenceCodePoint(int $codePoint): int
    {
        if ($codePoint === 0 || $codePoint > 0x10FFFF || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
            return 0xFFFD;
        }

        return [
            0x80 => 0x20AC,
            0x82 => 0x201A,
            0x83 => 0x0192,
            0x84 => 0x201E,
            0x85 => 0x2026,
            0x86 => 0x2020,
            0x87 => 0x2021,
            0x88 => 0x02C6,
            0x89 => 0x2030,
            0x8A => 0x0160,
            0x8B => 0x2039,
            0x8C => 0x0152,
            0x8E => 0x017D,
            0x91 => 0x2018,
            0x92 => 0x2019,
            0x93 => 0x201C,
            0x94 => 0x201D,
            0x95 => 0x2022,
            0x96 => 0x2013,
            0x97 => 0x2014,
            0x98 => 0x02DC,
            0x99 => 0x2122,
            0x9A => 0x0161,
            0x9B => 0x203A,
            0x9C => 0x0153,
            0x9E => 0x017E,
            0x9F => 0x0178,
        ][$codePoint] ?? $codePoint;
    }

    private static function codePointToUtf8(int $codePoint): string
    {
        if ($codePoint <= 0x7F) {
            return chr($codePoint);
        }

        if ($codePoint <= 0x7FF) {
            return chr(0xC0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        if ($codePoint <= 0xFFFF) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xF0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }
}
