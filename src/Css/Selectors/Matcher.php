<?php

declare(strict_types=1);

namespace Lexbor\Css\Selectors;

use Lexbor\Css\Syntax\AnPlusBParser;
use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;
use Lexbor\Dom\Comment;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\Text;
use Lexbor\Html\Document;
use Lexbor\Html\Tag;

final class Matcher
{
    public const int OPT_DEFAULT = 0;
    public const int OPT_MATCH_ROOT = 1;
    public const int OPT_MATCH_FIRST = 2;

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

    private const array SIMPLE_PSEUDO_CLASSES = [
        'active' => true,
        'any-link' => true,
        'blank' => true,
        'checked' => true,
        'disabled' => true,
        'empty' => true,
        'enabled' => true,
        'first-child' => true,
        'first-of-type' => true,
        'focus' => true,
        'hover' => true,
        'last-child' => true,
        'last-of-type' => true,
        'link' => true,
        'only-child' => true,
        'only-of-type' => true,
        'optional' => true,
        'placeholder-shown' => true,
        'read-only' => true,
        'read-write' => true,
        'required' => true,
        'root' => true,
    ];

    public function __construct(
        private readonly Parser $parser = new Parser(),
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @return list<Element>
     */
    public function find(Node $root, string $selector, int $options = self::OPT_DEFAULT): array
    {
        $selectors = $this->parseSelectorList($selector);
        if ($selectors === null) {
            return [];
        }

        $matches = [];
        $appendMatches = function (Element $element) use ($selectors, &$matches, $options): void {
            foreach ($selectors as $complex) {
                if ($this->matchesComplex($element, $complex)) {
                    $matches[] = $element;

                    if (($options & self::OPT_MATCH_FIRST) !== 0) {
                        return;
                    }
                }
            }
        };

        if (($options & self::OPT_MATCH_ROOT) !== 0 && $root instanceof Element) {
            $appendMatches($root);
        }

        $this->walkDescendantElements($root, $appendMatches);

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
        $tokens = $this->stripWhitespaceTokens($this->tokenizer->tokenize($selector));
        $collapsedNestedNot = self::collapseNestedNotSelector($tokens);
        if ($collapsedNestedNot !== null) {
            $collapsedSelectors = $this->parseSelectorTokenList($collapsedNestedNot);
            if ($collapsedSelectors !== null) {
                return $collapsedSelectors;
            }
        }

        $parsed = $this->parser->parseForMatching($selector);
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
     * @return list<Token>|null
     */
    private static function collapseNestedNotSelector(array $tokens): ?array
    {
        $count = count($tokens);
        if ($count < 5) {
            return null;
        }

        $offset = 0;
        $wrappers = 0;
        while (
            ($tokens[$offset] ?? null)?->type === 'colon'
            && ($tokens[$offset + 1] ?? null)?->type === 'function'
            && strtolower($tokens[$offset + 1]->value) === 'not('
        ) {
            $wrappers++;
            $offset += 2;
        }

        if ($wrappers < 2 || $offset >= $count) {
            return null;
        }

        for ($index = 0; $index < $wrappers; $index++) {
            if (($tokens[$count - 1 - $index] ?? null)?->type !== 'right-parenthesis') {
                return null;
            }
        }

        $inner = self::trimTokenList(array_slice($tokens, $offset, $count - $wrappers - $offset));
        if ($inner === [] || self::hasTopLevelSelectorListComma($inner) || ! self::selectorTokensAreBalanced($inner)) {
            return null;
        }

        if ($wrappers % 2 === 0) {
            return $inner;
        }

        return [
            $tokens[0],
            $tokens[1],
            ...$inner,
            $tokens[$count - 1],
        ];
    }

    /**
     * @param list<Token> $tokens
     */
    private static function hasTopLevelSelectorListComma(array $tokens): bool
    {
        $bracketDepth = 0;
        $functionDepth = 0;

        foreach ($tokens as $token) {
            if ($token->type === 'comma' && $bracketDepth === 0 && $functionDepth === 0) {
                return true;
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
        }

        return false;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function selectorTokensAreBalanced(array $tokens): bool
    {
        $bracketDepth = 0;
        $functionDepth = 0;

        foreach ($tokens as $token) {
            if ($token->type === 'left-square-bracket') {
                $bracketDepth++;
                continue;
            }

            if ($token->type === 'right-square-bracket') {
                if ($bracketDepth === 0) {
                    return false;
                }

                $bracketDepth--;
                continue;
            }

            if ($token->type === 'function') {
                $functionDepth++;
                continue;
            }

            if ($token->type === 'right-parenthesis') {
                if ($functionDepth === 0) {
                    return false;
                }

                $functionDepth--;
            }
        }

        return $bracketDepth === 0 && $functionDepth === 0;
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
                $pseudoOffset = $offset;
                $pseudo = $this->parseFunctionalPseudoSelector($tokens, $pseudoOffset);
                if ($pseudo === null) {
                    $pseudoOffset = $offset;
                    $pseudo = $this->parseSimplePseudoSelector($tokens, $pseudoOffset);
                }

                if ($pseudo === null) {
                    return null;
                }

                $compound['pseudos'][] = $pseudo;
                $offset = $pseudoOffset;
                continue;
            }

            return null;
        }

        return $compound;
    }

    /**
     * @param list<Token> $tokens
     * @return array{name: string}|null
     */
    private function parseSimplePseudoSelector(array $tokens, int &$offset): ?array
    {
        $name = $tokens[$offset + 1] ?? null;
        if ($name?->type !== 'ident') {
            return null;
        }

        $nameValue = strtolower($name->value);
        if (! isset(self::SIMPLE_PSEUDO_CLASSES[$nameValue])) {
            return null;
        }

        $offset += 2;

        return [
            'name' => $nameValue,
        ];
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
     * @return array{name: string, selectors: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>}|array{name: string, a: int, b: int, of: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null}|array{name: string, needle: string, caseInsensitive: bool}|null
     */
    private function parseFunctionalPseudoSelector(array $tokens, int &$offset): ?array
    {
        $function = $tokens[$offset + 1] ?? null;
        if ($function?->type !== 'function') {
            return null;
        }

        $name = strtolower(substr($function->value, 0, -1));
        if (! in_array($name, ['current', 'not', 'is', 'where', 'has', 'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type', 'lexbor-contains'], true)) {
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
        if (in_array($name, ['nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type'], true)) {
            $nth = $this->parseNthChildPseudoSelector($name, $body);
            if ($nth === null) {
                return null;
            }

            return $nth;
        }

        if ($name === 'lexbor-contains') {
            return $this->parseLexborContainsPseudoSelector($body);
        }

        $selectors = match ($name) {
            'current', 'not' => $this->parseSelectorTokenList($body),
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
     * @param list<Token> $body
     * @return array{name: string, needle: string, caseInsensitive: bool}|null
     */
    private function parseLexborContainsPseudoSelector(array $body): ?array
    {
        $offset = 0;
        $needle = self::attributeValue($body[$offset] ?? null);
        if ($needle === null) {
            return null;
        }

        $offset++;
        self::skipWhitespace($body, $offset);

        $caseInsensitive = false;
        if (($body[$offset] ?? null)?->type === 'ident') {
            if (strtolower($body[$offset]->value) !== 'i') {
                return null;
            }

            $caseInsensitive = true;
            $offset++;
            self::skipWhitespace($body, $offset);
        }

        if (isset($body[$offset])) {
            return null;
        }

        return [
            'name' => 'lexbor-contains',
            'needle' => $needle,
            'caseInsensitive' => $caseInsensitive,
        ];
    }

    /**
     * @param list<Token> $body
     * @return array{name: string, a: int, b: int, of: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null}|null
     */
    private function parseNthChildPseudoSelector(string $name, array $body): ?array
    {
        [$anPlusBTokens, $ofSelectorTokens] = self::splitNthChildOfSelector($body);
        $anPlusBSource = self::serializeAnPlusBTokens($anPlusBTokens);
        $anPlusB = (new AnPlusBParser())->parse($anPlusBSource);
        if ($anPlusB['value'] === '') {
            return null;
        }

        $formula = self::parseAnPlusBFormula($anPlusBSource);
        if ($formula === null) {
            return null;
        }

        $ofSelectors = null;
        if ($ofSelectorTokens !== null) {
            $ofSelectors = $this->parseSelectorTokenList($this->stripWhitespaceTokens($ofSelectorTokens));
            if ($ofSelectors === null) {
                return null;
            }
        }

        return [
            'name' => $name,
            'a' => $formula['a'],
            'b' => $formula['b'],
            'of' => $ofSelectors,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{list<Token>, list<Token>|null}
     */
    private static function splitNthChildOfSelector(array $tokens): array
    {
        $anPlusBTokens = [];
        $stack = [];
        $count = count($tokens);

        for ($offset = 0; $offset < $count; $offset++) {
            $token = $tokens[$offset];
            if ($token->type === 'ident' && strtolower($token->value) === 'of' && $stack === []) {
                return [
                    self::trimTokenList($anPlusBTokens),
                    self::trimTokenList(array_slice($tokens, $offset + 1)),
                ];
            }

            if ($token->type === 'function') {
                $stack[] = 'right-parenthesis';
                $anPlusBTokens[] = $token;
                continue;
            }

            if ($token->type === 'left-square-bracket') {
                $stack[] = 'right-square-bracket';
                $anPlusBTokens[] = $token;
                continue;
            }

            if ($stack !== [] && $token->type === $stack[array_key_last($stack)]) {
                array_pop($stack);
            }

            $anPlusBTokens[] = $token;
        }

        return [self::trimTokenList($anPlusBTokens), null];
    }

    /**
     * @param list<Token> $tokens
     */
    private static function serializeAnPlusBTokens(array $tokens): string
    {
        $value = '';
        $previous = null;

        foreach ($tokens as $token) {
            if ($token->type === 'whitespace') {
                continue;
            }

            if (
                $token->type === 'number'
                && $token->value !== ''
                && ! str_starts_with($token->value, '+')
                && ! str_starts_with($token->value, '-')
                && $previous !== null
                && str_ends_with(strtolower(rtrim($previous->value, '(')), 'n')
            ) {
                $value .= '+';
            }

            $value .= $token->value;
            $previous = $token;
        }

        return $value;
    }

    /**
     * @return array{a: int, b: int}|null
     */
    private static function parseAnPlusBFormula(string $value): ?array
    {
        $value = strtolower($value);

        if ($value === 'odd') {
            return ['a' => 2, 'b' => 1];
        }

        if ($value === 'even') {
            return ['a' => 2, 'b' => 0];
        }

        if (preg_match('/^[+-]?\d+$/', $value) === 1) {
            return ['a' => 0, 'b' => (int) $value];
        }

        if (preg_match('/^([+-]?)(?:(\d+))?n(?:([+-])(\d+))?$/', $value, $matches) === 1) {
            $coefficient = $matches[2] ?? '';
            if ($coefficient === '') {
                $a = ($matches[1] ?? '') === '-' ? -1 : 1;
            } else {
                $a = (int) $coefficient;
                if (($matches[1] ?? '') === '-') {
                    $a *= -1;
                }
            }

            $b = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0;
            if (($matches[3] ?? '') === '-') {
                $b *= -1;
            }

            return ['a' => $a, 'b' => $b];
        }

        return null;
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
     * @param array{name: string, selectors?: list<array{parts: list<array<string, mixed>>, combinators: list<string>, relative?: string}>, a?: int, b?: int, of?: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null, needle?: string, caseInsensitive?: bool} $pseudo
     */
    private function matchesFunctionalPseudo(Element $element, array $pseudo, ?Element $scopeRoot): bool
    {
        return match ($pseudo['name']) {
            'active' => $element->hasAttribute('active'),
            'any-link' => self::hasHrefOnElement($element, ['a', 'area', 'map']),
            'blank' => self::elementIsBlank($element),
            'checked' => self::elementIsChecked($element),
            'disabled' => self::elementIsDisabled($element),
            'root' => self::documentRootElement($element) === $element,
            'empty' => self::elementIsEmpty($element),
            'enabled' => ! self::elementIsDisabled($element),
            'first-child' => self::previousElementSibling($element) === null,
            'last-child' => self::nextElementSibling($element) === null,
            'only-child' => self::previousElementSibling($element) === null && self::nextElementSibling($element) === null,
            'first-of-type' => self::previousElementSiblingOfType($element) === null,
            'last-of-type' => self::nextElementSiblingOfType($element) === null,
            'only-of-type' => self::previousElementSiblingOfType($element) === null && self::nextElementSiblingOfType($element) === null,
            'focus' => $element->hasAttribute('focus'),
            'hover' => $element->hasAttribute('hover'),
            'link' => self::hasHrefOnElement($element, ['a', 'area', 'link']),
            'optional' => self::isRequiredOptionalElement($element) && ! $element->hasAttribute('required'),
            'placeholder-shown' => self::isPlaceholderElement($element) && $element->hasAttribute('placeholder'),
            'read-only' => ! self::elementIsReadWrite($element),
            'read-write' => self::elementIsReadWrite($element),
            'required' => self::isRequiredOptionalElement($element) && $element->hasAttribute('required'),
            'current', 'is', 'where' => $this->matchesAnyComplex($element, $pseudo['selectors'], $scopeRoot),
            'not' => ! $this->matchesAnyComplex($element, $pseudo['selectors'], $scopeRoot),
            'has' => $this->hasRelativeMatchingAnyComplex($element, $pseudo['selectors']),
            'nth-child' => $this->matchesNthChild($element, $pseudo, false),
            'nth-last-child' => $this->matchesNthChild($element, $pseudo, true),
            'nth-of-type' => $this->matchesNthOfType($element, $pseudo, false),
            'nth-last-of-type' => $this->matchesNthOfType($element, $pseudo, true),
            'lexbor-contains' => self::hasTextChildContaining($element, $pseudo['needle'], $pseudo['caseInsensitive']),
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
     * @param array{name: string, a: int, b: int, of: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null} $pseudo
     */
    private function matchesNthChild(Element $element, array $pseudo, bool $fromEnd): bool
    {
        if (! $element->parent instanceof Node) {
            return false;
        }

        $siblings = self::elementChildren($element->parent);
        if ($pseudo['of'] !== null) {
            $siblings = array_values(array_filter(
                $siblings,
                fn (Element $sibling): bool => $this->matchesAnyComplex($sibling, $pseudo['of']),
            ));
        }

        if ($fromEnd) {
            $siblings = array_reverse($siblings);
        }

        foreach ($siblings as $index => $sibling) {
            if ($sibling === $element) {
                return self::matchesAnPlusBIndex($index + 1, $pseudo['a'], $pseudo['b']);
            }
        }

        return false;
    }

    /**
     * @param array{name: string, a: int, b: int, of: list<array{parts: list<array<string, mixed>>, combinators: list<string>}>|null} $pseudo
     */
    private function matchesNthOfType(Element $element, array $pseudo, bool $fromEnd): bool
    {
        if (! $element->parent instanceof Node || $pseudo['of'] !== null) {
            return false;
        }

        $siblings = array_values(array_filter(
            self::elementChildren($element->parent),
            fn (Element $sibling): bool => $sibling->tagName === $element->tagName,
        ));

        if ($fromEnd) {
            $siblings = array_reverse($siblings);
        }

        foreach ($siblings as $index => $sibling) {
            if ($sibling === $element) {
                return self::matchesAnPlusBIndex($index + 1, $pseudo['a'], $pseudo['b']);
            }
        }

        return false;
    }

    private static function matchesAnPlusBIndex(int $index, int $a, int $b): bool
    {
        if ($a === 0) {
            return $index === $b;
        }

        if ($a > 0) {
            $diff = $index - $b;

            return $diff >= 0 && $diff % $a === 0;
        }

        $diff = $b - $index;
        $step = -$a;

        return $diff >= 0 && $diff % $step === 0;
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
        return self::trimTokenList($tokens);
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function trimTokenList(array $tokens): array
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

    /**
     * @return list<Element>
     */
    private static function elementChildren(Node $root): array
    {
        $children = [];
        for ($node = $root->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                $children[] = $node;
            }
        }

        return $children;
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

    private static function nextElementSibling(Element $element): ?Element
    {
        for ($node = $element->next; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                return $node;
            }
        }

        return null;
    }

    private static function previousElementSiblingOfType(Element $element): ?Element
    {
        for ($node = $element->prev; $node !== null; $node = $node->prev) {
            if ($node instanceof Element && $node->tagName === $element->tagName) {
                return $node;
            }
        }

        return null;
    }

    private static function nextElementSiblingOfType(Element $element): ?Element
    {
        for ($node = $element->next; $node !== null; $node = $node->next) {
            if ($node instanceof Element && $node->tagName === $element->tagName) {
                return $node;
            }
        }

        return null;
    }

    private static function documentRootElement(Element $element): ?Element
    {
        $ownerDocument = $element->ownerDocument;
        if (! $ownerDocument instanceof Node) {
            return null;
        }

        for ($node = $ownerDocument->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Element) {
                return $node;
            }
        }

        return null;
    }

    private static function elementIsEmpty(Element $element): bool
    {
        for ($node = $element->firstChild; $node !== null; $node = $node->next) {
            if (! $node instanceof Comment) {
                return false;
            }
        }

        return true;
    }

    private static function elementIsBlank(Element $element): bool
    {
        for ($node = $element->firstChild; $node !== null; $node = $node->next) {
            if ($node instanceof Comment) {
                continue;
            }

            if (! $node instanceof Text || strspn($node->data, "\t\n\f\r ") !== strlen($node->data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $tagNames
     */
    private static function hasHrefOnElement(Element $element, array $tagNames): bool
    {
        return in_array($element->tagName, $tagNames, true) && $element->hasAttribute('href');
    }

    private static function elementIsChecked(Element $element): bool
    {
        if ($element->tagName === 'input') {
            $type = $element->getAttribute('type');

            return $type !== null
                && in_array(strtolower($type), ['checkbox', 'radio'], true)
                && $element->hasAttribute('checked');
        }

        if ($element->tagName === 'option') {
            return $element->hasAttribute('selected');
        }

        return self::isDynamicElement($element) && $element->hasAttribute('checked');
    }

    private static function elementIsDisabled(Element $element): bool
    {
        if (! $element->hasAttribute('disabled')) {
            return false;
        }

        if (
            in_array($element->tagName, ['button', 'input', 'select', 'textarea'], true)
            || self::isDynamicElement($element)
        ) {
            return true;
        }

        for ($node = $element->parent; $node !== null; $node = $node->parent) {
            if ($node instanceof Element && $node->tagName === 'fieldset') {
                if (! ($node->firstChild instanceof Element && $node->firstChild->tagName === 'legend')) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function elementIsReadWrite(Element $element): bool
    {
        return in_array($element->tagName, ['input', 'textarea'], true)
            && ! $element->hasAttribute('readonly')
            && ! self::elementIsDisabled($element);
    }

    private static function isRequiredOptionalElement(Element $element): bool
    {
        return in_array($element->tagName, ['input', 'select', 'textarea'], true);
    }

    private static function isPlaceholderElement(Element $element): bool
    {
        return in_array($element->tagName, ['input', 'textarea'], true);
    }

    private static function isDynamicElement(Element $element): bool
    {
        return $element->tagId !== null && $element->tagId >= Tag::LAST_ENTRY;
    }

    private static function hasTextChildContaining(Element $element, string $needle, bool $caseInsensitive): bool
    {
        for ($child = $element->firstChild; $child !== null; $child = $child->next) {
            if ($child instanceof Text && self::contains($child->data, $needle, $caseInsensitive)) {
                return true;
            }
        }

        return false;
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
        return $element->ownerDocument instanceof Document
            && $element->namespace === Element::NAMESPACE_HTML
            && isset(self::HTML_CASE_INSENSITIVE_ATTRIBUTES[$name]);
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
