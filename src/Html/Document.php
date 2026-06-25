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
        $html = self::normalizeTokenizedNewlines($html);
        $doctype = $this->parseLeadingDoctype($html);
        $this->quirksMode = self::doctypeRequiresQuirks($doctype);
        $this->setDocumentTypeFromParsedDoctype($doctype);

        while ($this->body->firstChild !== null) {
            $this->body->firstChild->remove();
        }

        $this->body->clearAttributes();

        $bodyFragment = $this->bodyFragment($html);
        if ($bodyFragment !== null) {
            foreach ($this->parseAttributes($bodyFragment['attributes']) as $attribute) {
                $this->body->setAttribute($attribute['name'], $attribute['value']);
            }

            $html = $bodyFragment['content'];
        } else {
            $html = $doctype === null ? $this->stripLeadingDoctype($html) : substr($html, $doctype['offset']);
        }

        $this->parseFragmentInto($this->body, $html);

        return Status::Ok;
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
            $publicId === '' ? null : $publicId,
            $systemId === '' ? null : $systemId,
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
                $this->shouldDecodeTextOnlyElementContent($context)
                    ? $this->decodeCharacterReferences($html)
                    : $html,
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
        $pattern = '~(?<comment_start><!--)|(?<empty_end_tag></>)|</(?<invalid_end_tag>[^A-Za-z>][^>]*)(?:>|\z)|<(?<bogus_comment>\?[^>]*)(?:>|\z)|<!(?!doctype)(?<bogus_declaration>[^>]*)(?:>|\z)|<\s*(?<closing>/)?\s*(?<tag>[A-Za-z][^\t\n\f\r />]*)~si';
        $offset = 0;

        while (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $offset) === 1) {
            $tagStart = $match[0][1];
            if ($tagStart > $offset) {
                $this->appendText($stack[count($stack) - 1], substr($html, $offset, $tagStart - $offset));
            }

            $tagEnd = $tagStart + strlen($match[0][0]);
            $parent = $stack[count($stack) - 1];

            if (($match['comment_start'][1] ?? -1) !== -1) {
                [$comment, $tagEnd] = self::consumeHtmlComment($html, $tagStart);
                $this->appendComment($parent, $comment);
                $offset = $tagEnd;
                continue;
            }

            if (($match['empty_end_tag'][1] ?? -1) !== -1) {
                $offset = $tagEnd;
                continue;
            }

            if (($match['invalid_end_tag'][1] ?? -1) !== -1) {
                $this->appendComment($parent, str_replace("\0", "\u{FFFD}", $match['invalid_end_tag'][0]));
                $offset = $tagEnd;
                continue;
            }

            if (($match['bogus_comment'][1] ?? -1) !== -1) {
                $this->appendComment($parent, $match['bogus_comment'][0]);
                $offset = $tagEnd;
                continue;
            }

            if (($match['bogus_declaration'][1] ?? -1) !== -1) {
                $declaration = $match['bogus_declaration'][0];
                if (str_starts_with($declaration, '[CDATA[') && self::isForeignContentCdataContext($parent, $root, $context)) {
                    [$cdata, $tagEnd] = self::consumeCdataSection($html, $tagStart);
                    $this->appendText($parent, $cdata, false);
                    $offset = $tagEnd;
                    continue;
                }

                $this->appendComment($parent, $declaration);
                $offset = $tagEnd;
                continue;
            }

            $tagName = self::normalizeTagTokenName($match['tag'][0]);
            $startTagEnd = self::consumeStartTag($html, $tagStart + strlen($match[0][0]));
            if ($startTagEnd === null) {
                $this->appendText($parent, substr($html, $tagStart));
                return;
            }

            [$attributeSource, $tagEnd, $selfClosing] = $startTagEnd;

            if ($match['closing'][0] === '/') {
                $this->closeElement($stack, $tagName);
                $offset = $tagEnd;
                continue;
            }

            $namespaceParent = ($parent === $root && $context !== null) ? $context : $parent;
            $element = $this->createElement($tagName, $this->namespaceForElement($namespaceParent, $tagName));
            foreach ($this->parseAttributes($attributeSource) as $attribute) {
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
            return [str_replace("\0", "\u{FFFD}", substr($html, $dataStart)), strlen($html)];
        }

        return [
            str_replace("\0", "\u{FFFD}", substr($html, $dataStart, $dataEnd - $dataStart)),
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
            $this->appendText($element, substr($html, $offset), false);

            return strlen($html);
        }

        $pattern = sprintf('~<\s*/\s*%s\s*>~i', preg_quote($element->tagName, '~'));

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE, $offset) !== 1) {
            $this->appendText($element, substr($html, $offset), $this->shouldDecodeTextOnlyElementContent($element));
            return strlen($html);
        }

        $closeStart = $match[0][1];
        $this->appendText($element, substr($html, $offset, $closeStart - $offset), $this->shouldDecodeTextOnlyElementContent($element));

        return $closeStart + strlen($match[0][0]);
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

        if ($parent->namespace === Element::NAMESPACE_SVG && $parent->tagName !== 'foreignobject') {
            return Element::NAMESPACE_SVG;
        }

        if ($parent->namespace === Element::NAMESPACE_MATH) {
            return Element::NAMESPACE_MATH;
        }

        return Element::NAMESPACE_HTML;
    }

    /**
     * @return array{attributes: string, content: string}|null
     */
    private function bodyFragment(string $html): ?array
    {
        $pattern = '~<\s*body(?=[\t\n\f\r />])~si';

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $bodyTag = self::consumeStartTag($html, $match[0][1] + strlen($match[0][0]));
        if ($bodyTag === null) {
            return null;
        }

        [$attributes, $start] = $bodyTag;
        $closePattern = '~<\s*/\s*body\s*>~i';

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

    private function stripLeadingDoctype(string $html): string
    {
        return preg_replace('~^\s*<!doctype\b[^>]*>~i', '', $html, 1) ?? $html;
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

    /**
     * @return array{name: string, publicId: string|null, systemId: string|null, offset: int}|null
     */
    private function parseLeadingDoctype(string $html): ?array
    {
        $abruptPublicSystemPattern = <<<'REGEX'
~^\s*<!doctype\s+(?<name>[^\x00\s>"']+)\s+PUBLIC\s+(?:"(?<publicIdDouble>[^">]*)"|'(?<publicIdSingle>[^'>]*)')\s*(?:"(?<systemIdDouble>[^">]*)>|'(?<systemIdSingle>[^'>]*)>)~is
REGEX;

        if (preg_match($abruptPublicSystemPattern, $html, $abruptPublicSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $publicId = $abruptPublicSystemMatch['publicIdDouble'][0] ?? $abruptPublicSystemMatch['publicIdSingle'][0] ?? null;
            $systemId = $abruptPublicSystemMatch['systemIdDouble'][0] ?? $abruptPublicSystemMatch['systemIdSingle'][0] ?? null;

            return [
                'name' => strtolower($abruptPublicSystemMatch['name'][0]),
                'publicId' => $publicId === '' ? null : $publicId,
                'systemId' => $systemId === '' ? null : $systemId,
                'offset' => strlen($abruptPublicSystemMatch[0][0]),
            ];
        }

        $abruptPublicPattern = <<<'REGEX'
~^\s*<!doctype\s+(?<name>[^\x00\s>"']+)\s+PUBLIC\s+(?:"(?<publicIdDouble>[^">]*)>|'(?<publicIdSingle>[^'>]*)>)~is
REGEX;

        if (preg_match($abruptPublicPattern, $html, $abruptPublicMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $publicId = $abruptPublicMatch['publicIdDouble'][0] ?? $abruptPublicMatch['publicIdSingle'][0] ?? null;

            return [
                'name' => strtolower($abruptPublicMatch['name'][0]),
                'publicId' => $publicId === '' ? null : $publicId,
                'systemId' => null,
                'offset' => strlen($abruptPublicMatch[0][0]),
            ];
        }

        $abruptSystemPattern = <<<'REGEX'
~^\s*<!doctype\s+(?<name>[^\x00\s>"']+)\s+SYSTEM\s+(?:"(?<systemIdDouble>[^">]*)>|'(?<systemIdSingle>[^'>]*)>)~is
REGEX;

        if (preg_match($abruptSystemPattern, $html, $abruptSystemMatch, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === 1) {
            $systemId = $abruptSystemMatch['systemIdDouble'][0] ?? $abruptSystemMatch['systemIdSingle'][0] ?? null;

            return [
                'name' => strtolower($abruptSystemMatch['name'][0]),
                'publicId' => null,
                'systemId' => $systemId === '' ? null : $systemId,
                'offset' => strlen($abruptSystemMatch[0][0]),
            ];
        }

        $pattern = <<<'REGEX'
~^\s*<!doctype\s+(?<name>[^\x00\s>"']+)(?:
    \s+PUBLIC\s+(?<publicQuote>["'])(?<publicId>.*?)\k<publicQuote>(?:\s+(?<publicSystemQuote>["'])(?<publicSystemId>.*?)\k<publicSystemQuote>)?
  | \s+SYSTEM\s+(?<systemQuote>["'])(?<systemId>.*?)\k<systemQuote>
)?\s*>~isx
REGEX;

        if (preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) !== 1) {
            $eofPublicSystemPattern = <<<'REGEX'
~^\s*<!doctype\s+(?<name>[^\x00\s>"']+)\s+PUBLIC\s+(?<publicQuote>["'])(?<publicId>.*?)\k<publicQuote>\s*(?<systemQuote>["'])(?<systemId>[^"']*)\s*$~is
REGEX;

            if (preg_match($eofPublicSystemPattern, $html, $eofPublicSystemMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => strtolower($eofPublicSystemMatch['name'][0]),
                    'publicId' => $eofPublicSystemMatch['publicId'][0] === '' ? null : $eofPublicSystemMatch['publicId'][0],
                    'systemId' => $eofPublicSystemMatch['systemId'][0] === '' ? null : $eofPublicSystemMatch['systemId'][0],
                    'offset' => strlen($eofPublicSystemMatch[0][0]),
                ];
            }

            $eofPublicPattern = <<<'REGEX'
~^\s*<!doctype\s+(?<name>[^\x00\s>"']+)\s+PUBLIC\s+(?<publicQuote>["'])(?<publicId>[^"']*)\s*$~is
REGEX;

            if (preg_match($eofPublicPattern, $html, $eofPublicMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => strtolower($eofPublicMatch['name'][0]),
                    'publicId' => $eofPublicMatch['publicId'][0] === '' ? null : $eofPublicMatch['publicId'][0],
                    'systemId' => null,
                    'offset' => strlen($eofPublicMatch[0][0]),
                ];
            }

            $eofPublicKeywordPattern = '~^\s*<!doctype\s+(?<name>[^\x00\s>"\']+)\s+PUBLIC\s*$~i';
            if (preg_match($eofPublicKeywordPattern, $html, $eofPublicKeywordMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => strtolower($eofPublicKeywordMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($eofPublicKeywordMatch[0][0]),
                ];
            }

            $noWhitespaceNamePattern = '~^\s*<!doctype(?<name>[^\x00\s>"\']+)\s*>~i';
            if (preg_match($noWhitespaceNamePattern, $html, $noWhitespaceNameMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => strtolower($noWhitespaceNameMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($noWhitespaceNameMatch[0][0]),
                ];
            }

            $invalidAfterNamePattern = '~^\s*<!doctype\s+(?<name>[^\x00\s>"\']+)[^>]*>~i';
            if (preg_match($invalidAfterNamePattern, $html, $invalidAfterNameMatch, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'name' => strtolower($invalidAfterNameMatch['name'][0]),
                    'publicId' => null,
                    'systemId' => null,
                    'offset' => strlen($invalidAfterNameMatch[0][0]),
                ];
            }

            $eofNamePattern = '~^\s*<!doctype\s+(?<name>[^\x00\s>"\']+)\s*$~i';
            if (preg_match($eofNamePattern, $html, $eofMatch, PREG_OFFSET_CAPTURE) !== 1) {
                return null;
            }

            return [
                'name' => strtolower($eofMatch['name'][0]),
                'publicId' => null,
                'systemId' => null,
                'offset' => strlen($eofMatch[0][0]),
            ];
        }

        $publicId = $match['publicId'][0] ?? null;
        $systemId = $match['publicSystemId'][0] ?? $match['systemId'][0] ?? null;

        return [
            'name' => strtolower($match['name'][0]),
            'publicId' => $publicId === '' ? null : $publicId,
            'systemId' => $systemId === '' ? null : $systemId,
            'offset' => strlen($match[0][0]),
        ];
    }

    /**
     * @param array{name: string, publicId: string|null, systemId: string|null, offset: int}|null $doctype
     */
    private static function doctypeRequiresQuirks(?array $doctype): bool
    {
        if ($doctype === null || $doctype['name'] !== 'html') {
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
        return in_array($context->tagName, [
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
            || ($context->tagName === 'noscript' && $this->isScriptingEnabled());
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
