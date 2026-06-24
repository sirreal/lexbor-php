<?php

declare(strict_types=1);

namespace Lexbor\Css\Selectors;

use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Html\Document;

final class Matcher
{
    private const array HTML_CASE_INSENSITIVE_ATTRIBUTES = [
        'accept' => true,
        'accept-charset' => true,
        'align' => true,
        'alink' => true,
        'axis' => true,
        'bgcolor' => true,
        'charset' => true,
        'checked' => true,
        'clear' => true,
        'codetype' => true,
        'color' => true,
        'compact' => true,
        'declare' => true,
        'defer' => true,
        'dir' => true,
        'direction' => true,
        'disabled' => true,
        'enctype' => true,
        'face' => true,
        'frame' => true,
        'hreflang' => true,
        'http-equiv' => true,
        'lang' => true,
        'language' => true,
        'link' => true,
        'media' => true,
        'method' => true,
        'multiple' => true,
        'nohref' => true,
        'noresize' => true,
        'noshade' => true,
        'nowrap' => true,
        'readonly' => true,
        'rel' => true,
        'rev' => true,
        'rules' => true,
        'scope' => true,
        'scrolling' => true,
        'selected' => true,
        'shape' => true,
        'target' => true,
        'text' => true,
        'type' => true,
        'valign' => true,
        'valuetype' => true,
        'vlink' => true,
    ];

    public function __construct(
        private readonly Parser $parser = new Parser(),
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @return list<Element>
     */
    public function find(Node $root, string $selector): array
    {
        $selectors = $this->parseSelectorList($selector);
        if ($selectors === null) {
            return [];
        }

        $matches = [];
        $this->walkDescendantElements(
            $root,
            function (Element $element) use ($selectors, &$matches): void {
                foreach ($selectors as $compound) {
                    if ($this->matchesCompound($element, $compound)) {
                        $matches[] = $element;
                        return;
                    }
                }
            },
        );

        return $matches;
    }

    public function matches(Element $element, string $selector): bool
    {
        $selectors = $this->parseSelectorList($selector);
        if ($selectors === null) {
            return false;
        }

        foreach ($selectors as $compound) {
            if ($this->matchesCompound($element, $compound)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{tag: string|null, universal: bool, ids: list<string>, classes: list<string>, attributes: list<array{name: string, matcher: string, value: string|null, modifier: string|null}>}>|null
     */
    private function parseSelectorList(string $selector): ?array
    {
        if ($this->parser->parse($selector)['errors'] !== []) {
            return null;
        }

        $parts = $this->splitSelectorList($this->stripWhitespaceTokens($this->tokenizer->tokenize($selector)));
        if ($parts === []) {
            return null;
        }

        $selectors = [];
        foreach ($parts as $part) {
            $compound = $this->parseCompoundSelector($part);
            if ($compound === null) {
                return null;
            }

            $selectors[] = $compound;
        }

        return $selectors;
    }

    /**
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    private function splitSelectorList(array $tokens): array
    {
        $parts = [];
        $part = [];
        $bracketDepth = 0;

        foreach ($tokens as $token) {
            if ($token->type === 'left-square-bracket') {
                $bracketDepth++;
            } elseif ($token->type === 'right-square-bracket' && $bracketDepth > 0) {
                $bracketDepth--;
            }

            if ($token->type === 'comma' && $bracketDepth === 0) {
                $part = $this->stripWhitespaceTokens($part);
                if ($part === []) {
                    return [];
                }

                $parts[] = $part;
                $part = [];
                continue;
            }

            $part[] = $token;
        }

        $part = $this->stripWhitespaceTokens($part);
        if ($part === []) {
            return [];
        }

        $parts[] = $part;

        return $parts;
    }

    /**
     * @param list<Token> $tokens
     * @return array{tag: string|null, universal: bool, ids: list<string>, classes: list<string>, attributes: list<array{name: string, matcher: string, value: string|null, modifier: string|null}>}|null
     */
    private function parseCompoundSelector(array $tokens): ?array
    {
        $offset = 0;
        $compound = [
            'tag' => null,
            'universal' => false,
            'ids' => [],
            'classes' => [],
            'attributes' => [],
        ];

        $token = $tokens[$offset] ?? null;
        if ($token?->type === 'ident') {
            $compound['tag'] = strtolower($token->value);
            $offset++;
        } elseif ($token?->type === 'delim' && $token->value === '*') {
            $compound['universal'] = true;
            $offset++;
        }

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($token->type === 'whitespace') {
                return null;
            }

            if ($token->type === 'hash') {
                $compound['ids'][] = substr($token->value, 1);
                $offset++;
                continue;
            }

            if ($token->type === 'delim' && $token->value === '.') {
                $class = $tokens[$offset + 1] ?? null;
                if ($class?->type !== 'ident') {
                    return null;
                }

                $compound['classes'][] = $class->value;
                $offset += 2;
                continue;
            }

            if ($token->type === 'left-square-bracket') {
                $attribute = $this->parseAttributeSelector($tokens, $offset);
                if ($attribute === null) {
                    return null;
                }

                $compound['attributes'][] = $attribute;
                continue;
            }

            return null;
        }

        return $compound;
    }

    /**
     * @param list<Token> $tokens
     * @return array{name: string, matcher: string, value: string|null, modifier: string|null}|null
     */
    private function parseAttributeSelector(array $tokens, int &$offset): ?array
    {
        $offset++;
        self::skipWhitespace($tokens, $offset);

        $name = $tokens[$offset] ?? null;
        if ($name?->type !== 'ident') {
            return null;
        }

        $offset++;
        self::skipWhitespace($tokens, $offset);

        $token = $tokens[$offset] ?? null;
        if ($token?->type === 'right-square-bracket') {
            $offset++;

            return [
                'name' => strtolower($name->value),
                'matcher' => 'presence',
                'value' => null,
                'modifier' => null,
            ];
        }

        if ($token?->type !== 'delim') {
            return null;
        }

        $matcher = $token->value;
        if ($matcher === '=') {
            $offset++;
        } elseif (in_array($matcher, ['~', '|', '^', '$', '*'], true)) {
            if (($tokens[$offset + 1] ?? null)?->type !== 'delim' || $tokens[$offset + 1]->value !== '=') {
                return null;
            }

            $offset += 2;
        } else {
            return null;
        }

        self::skipWhitespace($tokens, $offset);

        $value = self::attributeValue($tokens[$offset] ?? null);
        if ($value === null) {
            return null;
        }

        $offset++;
        self::skipWhitespace($tokens, $offset);

        $modifier = null;
        if (($tokens[$offset] ?? null)?->type === 'ident') {
            $modifier = strtolower($tokens[$offset]->value);
            if ($modifier !== 'i' && $modifier !== 's') {
                return null;
            }

            $offset++;
            self::skipWhitespace($tokens, $offset);
        }

        if (($tokens[$offset] ?? null)?->type !== 'right-square-bracket') {
            return null;
        }

        $offset++;

        return [
            'name' => strtolower($name->value),
            'matcher' => $matcher,
            'value' => $value,
            'modifier' => $modifier,
        ];
    }

    /**
     * @param array{tag: string|null, universal: bool, ids: list<string>, classes: list<string>, attributes: list<array{name: string, matcher: string, value: string|null, modifier: string|null}>} $compound
     */
    private function matchesCompound(Element $element, array $compound): bool
    {
        if ($compound['tag'] !== null && $element->tagName !== $compound['tag']) {
            return false;
        }

        foreach ($compound['ids'] as $idSelector) {
            $id = $element->getAttribute('id');
            if ($id === null || ! self::stringsEqual($id, $idSelector, self::isQuirksMode($element))) {
                return false;
            }
        }

        foreach ($compound['classes'] as $className) {
            $class = $element->getAttribute('class');
            if ($class === null || ! self::classAttributeContains($class, $className, self::isQuirksMode($element))) {
                return false;
            }
        }

        foreach ($compound['attributes'] as $attribute) {
            if (! self::matchesAttribute($element, $attribute)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{name: string, matcher: string, value: string|null, modifier: string|null} $attribute
     */
    private static function matchesAttribute(Element $element, array $attribute): bool
    {
        $actual = $element->getAttribute($attribute['name']);
        if ($actual === null) {
            return false;
        }

        if ($attribute['matcher'] === 'presence') {
            return true;
        }

        $expected = $attribute['value'] ?? '';
        $caseInsensitive = match ($attribute['modifier']) {
            'i' => true,
            's' => false,
            default => self::isHtmlCaseInsensitiveAttribute($element, $attribute['name']),
        };

        return match ($attribute['matcher']) {
            '=' => self::stringsEqual($actual, $expected, $caseInsensitive),
            '~' => self::classAttributeContains($actual, $expected, $caseInsensitive),
            '|' => self::stringsEqual($actual, $expected, $caseInsensitive)
                || (
                    strlen($actual) > strlen($expected)
                    && self::startsWith($actual, $expected, $caseInsensitive)
                    && $actual[strlen($expected)] === '-'
                ),
            '^' => $expected !== '' && self::startsWith($actual, $expected, $caseInsensitive),
            '$' => $expected !== '' && self::endsWith($actual, $expected, $caseInsensitive),
            '*' => $expected !== '' && self::contains($actual, $expected, $caseInsensitive),
            default => false,
        };
    }

    private static function attributeValue(?Token $token): ?string
    {
        if ($token?->type === 'ident') {
            return $token->value;
        }

        if ($token?->type !== 'string') {
            return null;
        }

        $value = substr($token->value, 1, -1);

        return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function skipWhitespace(array $tokens, int &$offset): void
    {
        while (($tokens[$offset] ?? null)?->type === 'whitespace') {
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function stripWhitespaceTokens(array $tokens): array
    {
        while ($tokens !== [] && $tokens[0]->type === 'whitespace') {
            array_shift($tokens);
        }

        while ($tokens !== [] && $tokens[array_key_last($tokens)]->type === 'whitespace') {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * @param callable(Element): void $callback
     */
    private function walkDescendantElements(Node $root, callable $callback): void
    {
        for ($node = $root->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                $callback($node);
            }

            $this->walkDescendantElements($node, $callback);
        }
    }

    private static function classAttributeContains(string $classAttribute, string $className, bool $caseInsensitive): bool
    {
        if ($className === '') {
            return false;
        }

        foreach (preg_split('/[\t\n\f\r ]+/', $classAttribute, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (self::stringsEqual($token, $className, $caseInsensitive)) {
                return true;
            }
        }

        return false;
    }

    private static function isHtmlCaseInsensitiveAttribute(Element $element, string $name): bool
    {
        return $element->ownerDocument instanceof Document && isset(self::HTML_CASE_INSENSITIVE_ATTRIBUTES[$name]);
    }

    private static function isQuirksMode(Element $element): bool
    {
        $document = $element->ownerDocument;

        return $document instanceof Document && $document->isQuirksMode();
    }

    private static function stringsEqual(string $actual, string $expected, bool $caseInsensitive): bool
    {
        return $caseInsensitive ? strcasecmp($actual, $expected) === 0 : $actual === $expected;
    }

    private static function startsWith(string $actual, string $expected, bool $caseInsensitive): bool
    {
        return $caseInsensitive
            ? strncasecmp($actual, $expected, strlen($expected)) === 0
            : str_starts_with($actual, $expected);
    }

    private static function endsWith(string $actual, string $expected, bool $caseInsensitive): bool
    {
        if (strlen($expected) > strlen($actual)) {
            return false;
        }

        return $caseInsensitive
            ? strcasecmp(substr($actual, -strlen($expected)), $expected) === 0
            : str_ends_with($actual, $expected);
    }

    private static function contains(string $actual, string $expected, bool $caseInsensitive): bool
    {
        return $caseInsensitive
            ? str_contains(strtolower($actual), strtolower($expected))
            : str_contains($actual, $expected);
    }
}
