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
                foreach ($selectors as $complex) {
                    if ($this->matchesComplex($element, $complex)) {
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

        foreach ($selectors as $complex) {
            if ($this->matchesComplex($element, $complex)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null
     */
    private function parseSelectorList(string $selector): ?array
    {
        $parsed = $this->parser->parse($selector);
        if ($parsed['value'] === '') {
            return null;
        }

        return $this->parseSelectorTokenList($this->stripWhitespaceTokens($this->tokenizer->tokenize($parsed['value'])));
    }

    /**
     * @param list<Token> $tokens
     * @return list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null
     */
    private function parseSelectorTokenList(array $tokens): ?array
    {
        $parts = $this->splitSelectorList($tokens);
        if ($parts === []) {
            return null;
        }

        $selectors = [];
        foreach ($parts as $part) {
            $complex = $this->parseComplexSelector($part);
            if ($complex === null) {
                return null;
            }

            $selectors[] = $complex;
        }

        return $selectors;
    }

    /**
     * @param list<Token> $tokens
     * @return list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null
     */
    private function parseForgivingSelectorTokenList(array $tokens): ?array
    {
        $parts = $this->splitForgivingSelectorList($tokens);
        if ($parts === []) {
            return null;
        }

        $selectors = [];
        foreach ($parts as $part) {
            $complex = $this->parseComplexSelector($part);
            if ($complex !== null) {
                $selectors[] = $complex;
            }
        }

        return $selectors === [] ? null : $selectors;
    }

    /**
     * @param list<Token> $tokens
     * @return list<array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string}>|null
     */
    private function parseForgivingRelativeSelectorTokenList(array $tokens): ?array
    {
        $parts = $this->splitForgivingSelectorList($tokens);
        if ($parts === []) {
            return null;
        }

        $selectors = [];
        foreach ($parts as $part) {
            $complex = $this->parseRelativeSelector($part);
            if ($complex !== null) {
                $selectors[] = $complex;
            }
        }

        return $selectors === [] ? null : $selectors;
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
        $functionDepth = 0;

        foreach ($tokens as $token) {
            if ($token->type === 'left-square-bracket') {
                $bracketDepth++;
            } elseif ($token->type === 'right-square-bracket' && $bracketDepth > 0) {
                $bracketDepth--;
            }

            if ($token->type === 'function') {
                $functionDepth++;
            } elseif ($token->type === 'right-parenthesis' && $functionDepth > 0) {
                $functionDepth--;
            }

            if ($token->type === 'comma' && $bracketDepth === 0 && $functionDepth === 0) {
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
     * @return list<list<Token>>
     */
    private function splitForgivingSelectorList(array $tokens): array
    {
        $parts = [];
        $part = [];
        $bracketDepth = 0;
        $functionDepth = 0;

        foreach ($tokens as $token) {
            if ($token->type === 'left-square-bracket') {
                $bracketDepth++;
            } elseif ($token->type === 'right-square-bracket' && $bracketDepth > 0) {
                $bracketDepth--;
            }

            if ($token->type === 'function') {
                $functionDepth++;
            } elseif ($token->type === 'right-parenthesis' && $functionDepth > 0) {
                $functionDepth--;
            }

            if ($token->type === 'comma' && $bracketDepth === 0 && $functionDepth === 0) {
                $part = $this->stripWhitespaceTokens($part);
                if ($part !== []) {
                    $parts[] = $part;
                }

                $part = [];
                continue;
            }

            $part[] = $token;
        }

        $part = $this->stripWhitespaceTokens($part);
        if ($part !== []) {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @param list<Token> $tokens
     * @return array{parts: list<array<string, mixed>>, combinators: list<string>}|null
     */
    private function parseComplexSelector(array $tokens): ?array
    {
        $offset = 0;
        $parts = [];
        $combinators = [];

        self::skipWhitespace($tokens, $offset);

        while ($offset < count($tokens)) {
            $compoundTokens = self::consumeCompoundTokens($tokens, $offset);
            if ($compoundTokens === []) {
                return null;
            }

            $compound = $this->parseCompoundSelector($compoundTokens);
            if ($compound === null) {
                return null;
            }

            $parts[] = $compound;

            $hadWhitespace = self::skipWhitespaceAndReport($tokens, $offset);
            if ($offset >= count($tokens)) {
                break;
            }

            $token = $tokens[$offset];
            if ($token->type === 'delim' && in_array($token->value, ['>', '+', '~'], true)) {
                $combinators[] = $token->value;
                $offset++;
                self::skipWhitespace($tokens, $offset);

                if ($offset >= count($tokens)) {
                    return null;
                }

                continue;
            }

            if ($hadWhitespace) {
                $combinators[] = ' ';
                continue;
            }

            return null;
        }

        if ($parts === [] || count($combinators) !== count($parts) - 1) {
            return null;
        }

        return [
            'parts' => $parts,
            'combinators' => $combinators,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{parts: list<array<string, mixed>>, combinators: list<string>, relative: string}|null
     */
    private function parseRelativeSelector(array $tokens): ?array
    {
        $tokens = $this->stripWhitespaceTokens($tokens);
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $relative = ' ';
        $token = $tokens[$offset] ?? null;
        if ($token?->type === 'delim' && in_array($token->value, ['>', '+', '~'], true)) {
            $relative = $token->value;
            $offset++;
            self::skipWhitespace($tokens, $offset);

            if ($offset >= count($tokens)) {
                return null;
            }
        }

        $complex = $this->parseComplexSelector(array_slice($tokens, $offset));
        if ($complex === null) {
            return null;
        }

        $complex['relative'] = $relative;

        return $complex;
    }

    /**
     * @param list<Token> $tokens
     * @return array<string, mixed>|null
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
            'pseudos' => [],
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

            if ($token->type === 'colon') {
                $pseudo = $this->parseFunctionalPseudoSelector($tokens, $offset);
                if ($pseudo === null) {
                    return null;
                }

                $compound['pseudos'][] = $pseudo;
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
     * @param list<Token> $tokens
     * @return array{name: string, selectors: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>}|null
     */
    private function parseFunctionalPseudoSelector(array $tokens, int &$offset): ?array
    {
        $function = $tokens[$offset + 1] ?? null;
        if ($function?->type !== 'function') {
            return null;
        }

        $name = strtolower(substr($function->value, 0, -1));
        if (! in_array($name, ['not', 'is', 'where', 'has'], true)) {
            return null;
        }

        $offset += 2;
        $body = [];
        $depth = 1;

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($token->type === 'right-parenthesis') {
                $depth--;
                if ($depth === 0) {
                    $offset++;
                    break;
                }

                $body[] = $token;
                $offset++;
                continue;
            }

            if ($token->type === 'function') {
                $depth++;
            }

            $body[] = $token;
            $offset++;
        }

        if ($depth !== 0) {
            return null;
        }

        $body = $this->stripWhitespaceTokens($body);
        $selectors = match ($name) {
            'not' => $this->parseSelectorTokenList($body),
            'has' => $this->parseForgivingRelativeSelectorTokenList($body),
            default => $this->parseForgivingSelectorTokenList($body),
        };
        if ($selectors === null) {
            return null;
        }

        return [
            'name' => $name,
            'selectors' => $selectors,
        ];
    }

    /**
     * @param array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string} $complex
     */
    private function matchesComplex(
        Element $element,
        array $complex,
        ?Element $scopeRoot = null,
        ?Element $relativeRoot = null,
        ?string $relativeCombinator = null,
    ): bool
    {
        return $this->matchesComplexAt(
            $element,
            $complex,
            count($complex['parts']) - 1,
            $scopeRoot,
            $relativeRoot,
            $relativeCombinator,
        );
    }

    /**
     * @param array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string} $complex
     */
    private function matchesComplexAt(
        Element $element,
        array $complex,
        int $index,
        ?Element $scopeRoot = null,
        ?Element $relativeRoot = null,
        ?string $relativeCombinator = null,
    ): bool {
        if (! $this->matchesCompound($element, $complex['parts'][$index], $scopeRoot)) {
            return false;
        }

        if ($index === 0) {
            return $relativeRoot === null
                || $relativeCombinator === null
                || self::matchesRelativeAnchor($element, $relativeRoot, $relativeCombinator);
        }

        return match ($complex['combinators'][$index - 1]) {
            ' ' => $this->hasAncestorMatching($element, $complex, $index - 1, $scopeRoot, $relativeRoot, $relativeCombinator),
            '>' => $element->parent instanceof Element
                && ($scopeRoot === null || self::isDescendantOf($element->parent, $scopeRoot))
                && $this->matchesComplexAt($element->parent, $complex, $index - 1, $scopeRoot, $relativeRoot, $relativeCombinator),
            '+' => ($previous = self::previousElementSibling($element)) !== null
                && $this->matchesComplexAt($previous, $complex, $index - 1, $scopeRoot, $relativeRoot, $relativeCombinator),
            '~' => $this->hasPreviousSiblingMatching($element, $complex, $index - 1, $scopeRoot, $relativeRoot, $relativeCombinator),
            default => false,
        };
    }

    /**
     * @param array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string} $complex
     */
    private function hasAncestorMatching(
        Element $element,
        array $complex,
        int $index,
        ?Element $scopeRoot,
        ?Element $relativeRoot = null,
        ?string $relativeCombinator = null,
    ): bool
    {
        for ($node = $element->parent; $node !== null; $node = $node->parent) {
            if ($scopeRoot !== null && ! ($node instanceof Element && self::isDescendantOf($node, $scopeRoot))) {
                break;
            }

            if ($node instanceof Element && $this->matchesComplexAt($node, $complex, $index, $scopeRoot, $relativeRoot, $relativeCombinator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string} $complex
     */
    private function hasPreviousSiblingMatching(
        Element $element,
        array $complex,
        int $index,
        ?Element $scopeRoot,
        ?Element $relativeRoot = null,
        ?string $relativeCombinator = null,
    ): bool
    {
        for ($node = $element->prev; $node !== null; $node = $node->prev) {
            if ($node instanceof Element && $this->matchesComplexAt($node, $complex, $index, $scopeRoot, $relativeRoot, $relativeCombinator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $compound
     */
    private function matchesCompound(Element $element, array $compound, ?Element $scopeRoot = null): bool
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

        foreach ($compound['pseudos'] as $pseudo) {
            if (! $this->matchesFunctionalPseudo($element, $pseudo, $scopeRoot)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{name: string, selectors: list<array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string}>} $pseudo
     */
    private function matchesFunctionalPseudo(Element $element, array $pseudo, ?Element $scopeRoot): bool
    {
        return match ($pseudo['name']) {
            'is', 'where' => $this->matchesAnyComplex($element, $pseudo['selectors'], $scopeRoot),
            'not' => ! $this->matchesAnyComplex($element, $pseudo['selectors'], $scopeRoot),
            'has' => $this->hasRelativeMatchingAnyComplex($element, $pseudo['selectors']),
            default => false,
        };
    }

    /**
     * @param list<array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string}> $selectors
     */
    private function matchesAnyComplex(Element $element, array $selectors, ?Element $scopeRoot = null): bool
    {
        foreach ($selectors as $complex) {
            if ($this->matchesComplex($element, $complex, $scopeRoot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string}> $selectors
     */
    private function hasRelativeMatchingAnyComplex(Element $root, array $selectors): bool
    {
        foreach ($selectors as $complex) {
            $relative = $complex['relative'] ?? ' ';
            $scopeRoot = in_array($relative, [' ', '>'], true) ? $root : null;

            foreach ($this->relativeCandidateElements($root, $relative) as $element) {
                if ($this->matchesComplex($element, $complex, $scopeRoot, $root, $relative)) {
                    return true;
                }
            }
        }

        return false;
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
     * @return list<Token>
     */
    private static function consumeCompoundTokens(array $tokens, int &$offset): array
    {
        $part = [];
        $bracketDepth = 0;
        $functionDepth = 0;

        for (; $offset < count($tokens); $offset++) {
            $token = $tokens[$offset];

            if ($bracketDepth === 0 && $functionDepth === 0) {
                if ($token->type === 'whitespace') {
                    break;
                }

                if ($token->type === 'delim' && in_array($token->value, ['>', '+', '~'], true)) {
                    break;
                }
            }

            if ($token->type === 'left-square-bracket') {
                $bracketDepth++;
            } elseif ($token->type === 'right-square-bracket' && $bracketDepth > 0) {
                $bracketDepth--;
            }

            if ($token->type === 'function') {
                $functionDepth++;
            } elseif ($token->type === 'right-parenthesis' && $functionDepth > 0) {
                $functionDepth--;
            }

            $part[] = $token;
        }

        return $part;
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
     */
    private static function skipWhitespaceAndReport(array $tokens, int &$offset): bool
    {
        $hadWhitespace = false;

        while (($tokens[$offset] ?? null)?->type === 'whitespace') {
            $hadWhitespace = true;
            $offset++;
        }

        return $hadWhitespace;
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

    /**
     * @return iterable<Element>
     */
    private function relativeCandidateElements(Element $root, string $relative): iterable
    {
        if ($relative === ' ' || $relative === '>') {
            yield from $this->descendantElements($root);
            return;
        }

        if ($relative !== '+' && $relative !== '~') {
            return;
        }

        for ($node = $root->next; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                yield from $this->elementAndDescendantElements($node);
            } else {
                yield from $this->descendantElements($node);
            }
        }
    }

    /**
     * @return iterable<Element>
     */
    private function elementAndDescendantElements(Element $root): iterable
    {
        yield $root;
        yield from $this->descendantElements($root);
    }

    /**
     * @return iterable<Element>
     */
    private function descendantElements(Node $root): iterable
    {
        for ($node = $root->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                yield $node;
            }

            yield from $this->descendantElements($node);
        }
    }

    private static function matchesRelativeAnchor(Element $element, Element $root, string $relative): bool
    {
        return match ($relative) {
            ' ' => self::isDescendantOf($element, $root),
            '>' => $element->parent === $root,
            '+' => self::previousElementSibling($element) === $root,
            '~' => self::hasPreviousElementSibling($element, $root),
            default => false,
        };
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

    private static function previousElementSibling(Element $element): ?Element
    {
        for ($node = $element->prev; $node !== null; $node = $node->prev) {
            if ($node instanceof Element) {
                return $node;
            }
        }

        return null;
    }

    private static function hasPreviousElementSibling(Element $element, Element $previous): bool
    {
        for ($node = $element->prev; $node !== null; $node = $node->prev) {
            if ($node === $previous) {
                return true;
            }
        }

        return false;
    }

    private static function isDescendantOf(Element $element, Element $ancestor): bool
    {
        for ($node = $element->parent; $node !== null; $node = $node->parent) {
            if ($node === $ancestor) {
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
