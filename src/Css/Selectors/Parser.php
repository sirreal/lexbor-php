<?php

declare(strict_types=1);

namespace Lexbor\Css\Selectors;

use Lexbor\Css\Syntax\AnPlusBParser;
use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;

final class Parser
{
    /**
     * Pseudo-classes accepted by Lexbor's parser without additional function data.
     *
     * @var array<string, true>
     */
    private const SUPPORTED_PSEUDO_CLASSES = [
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
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @var array<int, string>
     */
    private array $rawTokenValues = [];

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parse(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        $selectorList = $this->parseSelectorList($tokens);
        if ($selectorList !== null) {
            return $selectorList;
        }

        $class = $this->parseClassSelector($tokens);
        if ($class !== null) {
            return $class;
        }

        $compoundAttribute = $this->parseCompoundAttributeSelector($tokens);
        if ($compoundAttribute !== null) {
            return $compoundAttribute;
        }

        $attribute = $this->parseAttributeSelector($tokens);
        if ($attribute !== null) {
            return $attribute;
        }

        $pseudoFunction = $this->parsePseudoFunctionSelector($tokens);
        if ($pseudoFunction !== null) {
            return $pseudoFunction;
        }

        $descendant = $this->parseDescendantSelector($tokens);
        if ($descendant !== null) {
            return $descendant;
        }

        if (count($tokens) === 1) {
            return match ($tokens[0]->type) {
                'ident', 'hash' => ['value' => $tokens[0]->value, 'errors' => []],
                default => self::error($tokens[0]->value),
            };
        }

        $typeSelector = self::parseTypeSelector($selector);
        if ($typeSelector !== null) {
            return ['value' => $typeSelector, 'errors' => []];
        }

        $complex = $this->parseComplexSelector($tokens);
        if ($complex !== null) {
            return $complex;
        }

        return self::error(self::firstUnexpectedToken($tokens));
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseRelativeList(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        return $this->parseRelativeSelectorList($tokens);
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseComplexList(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        return $this->parseSpecificSelectorList(
            $tokens,
            fn (array $part): array => $this->parseRequiredComplexSelector($part),
            true,
        );
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseCompoundList(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        return $this->parseSpecificSelectorList(
            $tokens,
            fn (array $part): array => $this->parseRequiredCompoundSelector($part),
        );
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseSimpleList(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        return $this->parseSpecificSelectorList(
            $tokens,
            fn (array $part): array => $this->parseRequiredSimpleSelector($part),
        );
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseComplex(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        return $tokens === [] ? self::error('END-OF-FILE') : $this->parseRequiredComplexSelector($tokens);
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseCompound(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        return $tokens === [] ? self::error('END-OF-FILE') : $this->parseRequiredCompoundSelector($tokens);
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseSimple(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        return $tokens === [] ? self::error('END-OF-FILE') : $this->parseRequiredSimpleSelector($tokens);
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parseRelative(string $selector): array
    {
        $tokens = $this->tokenize($selector);

        return $tokens === [] ? self::error('END-OF-FILE') : $this->parseRequiredRelativeSelector($tokens);
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseClassSelector(array $tokens): ?array
    {
        if (
            count($tokens) < 2
            || $tokens[0]->type !== 'delim'
            || $tokens[0]->value !== '.'
        ) {
            return null;
        }

        if ($tokens[1]->type !== 'ident') {
            return self::error($tokens[1]->value);
        }

        $offset = 2;
        while (($tokens[$offset] ?? null)?->type === 'whitespace') {
            $offset++;
        }

        if (isset($tokens[$offset])) {
            if (self::isCombinatorToken($tokens[$offset])) {
                $lookahead = $offset + 1;
                self::skipWhitespace($tokens, $lookahead);

                if (! isset($tokens[$lookahead])) {
                    return self::error('END-OF-FILE');
                }
            }

            return self::error($tokens[$offset]->value);
        }

        return ['value' => ".{$tokens[1]->value}", 'errors' => []];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseSelectorList(array $tokens): ?array
    {
        if (! self::hasTopLevelTokenType($tokens, 'comma')) {
            return null;
        }

        $part = [];
        $serialized = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === 'comma' && $stack === []) {
                $part = self::trimTokenList($part);
                if ($part === []) {
                    return self::error(',');
                }

                if (self::endsWithCombinator($part)) {
                    return self::error(',');
                }

                $selector = $this->parseSelectorComponent($part);
                if ($selector === null) {
                    return self::error(self::firstUnexpectedToken($part));
                }

                if ($selector['errors'] !== []) {
                    return [
                        'value' => '',
                        'errors' => $selector['errors'],
                    ];
                }

                $serialized[] = $selector['value'];
                $part = [];
                continue;
            }

            self::updateTokenStack($stack, $token);
            $part[] = $token;
        }

        $part = self::trimTokenList($part);
        if ($part === [] || self::endsWithCombinator($part)) {
            return self::error('END-OF-FILE');
        }

        $selector = $this->parseSelectorComponent($part);
        if ($selector === null) {
            return self::error(self::firstUnexpectedToken($part));
        }

        if ($selector['errors'] !== []) {
            return [
                'value' => '',
                'errors' => $selector['errors'],
            ];
        }

        $serialized[] = $selector['value'];

        return [
            'value' => implode(', ', $serialized),
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function parseRelativeSelectorList(array $tokens): array
    {
        $part = [];
        $serialized = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === 'comma' && $stack === []) {
                $part = self::trimTokenList($part);
                if ($part === []) {
                    return self::error(',');
                }

                $selector = $this->parseRelativeSelector($part);
                if ($selector['errors'] !== []) {
                    return [
                        'value' => '',
                        'errors' => $selector['errors'],
                    ];
                }

                $serialized[] = $selector['value'];
                $part = [];
                continue;
            }

            self::updateTokenStack($stack, $token);
            $part[] = $token;
        }

        $part = self::trimTokenList($part);
        if ($part === []) {
            return self::error('END-OF-FILE');
        }

        $selector = $this->parseRelativeSelector($part);
        if ($selector['errors'] !== []) {
            return [
                'value' => '',
                'errors' => $selector['errors'],
            ];
        }

        $serialized[] = $selector['value'];

        return [
            'value' => implode(', ', $serialized),
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @param callable(list<Token>): array{value: string, errors: list<string>} $parsePart
     * @return array{value: string, errors: list<string>}
     */
    private function parseSpecificSelectorList(array $tokens, callable $parsePart, bool $reportTrailingCombinatorComma = false): array
    {
        $part = [];
        $serialized = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === 'comma' && $stack === []) {
                $part = self::trimTokenList($part);
                if ($part === []) {
                    return self::error(',');
                }

                if ($reportTrailingCombinatorComma && self::endsWithCombinator($part)) {
                    return self::error(',');
                }

                $selector = $parsePart($part);
                if ($selector['errors'] !== []) {
                    return [
                        'value' => '',
                        'errors' => $selector['errors'],
                    ];
                }

                $serialized[] = $selector['value'];
                $part = [];
                continue;
            }

            self::updateTokenStack($stack, $token);
            $part[] = $token;
        }

        $part = self::trimTokenList($part);
        if ($part === []) {
            return self::error('END-OF-FILE');
        }

        $selector = $parsePart($part);
        if ($selector['errors'] !== []) {
            return [
                'value' => '',
                'errors' => $selector['errors'],
            ];
        }

        $serialized[] = $selector['value'];

        return [
            'value' => implode(', ', $serialized),
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseDescendantSelector(array $tokens): ?array
    {
        if (! self::hasTokenType($tokens, 'whitespace')) {
            return null;
        }

        $parts = [];
        $part = [];

        foreach ($tokens as $token) {
            if ($token->type !== 'whitespace') {
                $part[] = $token;
                continue;
            }

            if ($part !== []) {
                $parts[] = $part;
                $part = [];
            }
        }

        if ($part !== []) {
            $parts[] = $part;
        }

        if (count($parts) < 2) {
            return null;
        }

        $serialized = [];
        foreach ($parts as $tokensPart) {
            $value = $this->serializeSimpleSelector($tokensPart);
            if ($value === null) {
                return null;
            }

            $serialized[] = $value;
        }

        return [
            'value' => implode(' ', $serialized),
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseCompoundAttributeSelector(array $tokens): ?array
    {
        if (
            count($tokens) < 2
            || $tokens[0]->type !== 'ident'
            || $tokens[1]->type !== 'left-square-bracket'
        ) {
            return null;
        }

        $attribute = $this->parseAttributeSelector(array_slice($tokens, 1));
        if ($attribute === null) {
            return null;
        }

        if ($attribute['value'] !== '') {
            $attribute['value'] = $tokens[0]->value . $attribute['value'];
        }

        return $attribute;
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseAttributeSelector(array $tokens): ?array
    {
        if (($tokens[0] ?? null)?->type !== 'left-square-bracket') {
            return null;
        }

        $offset = 1;
        self::skipWhitespace($tokens, $offset);

        $name = $tokens[$offset] ?? null;
        if ($name?->type !== 'ident') {
            return self::error($name?->value ?? 'END-OF-FILE');
        }

        $offset++;
        self::skipWhitespace($tokens, $offset);

        $token = $tokens[$offset] ?? null;
        if ($token?->type === 'right-square-bracket') {
            $offset++;
            self::skipWhitespace($tokens, $offset);

            if (isset($tokens[$offset])) {
                return self::error($tokens[$offset]->value);
            }

            return [
                'value' => "[{$name->value}]",
                'errors' => [],
            ];
        }

        if ($token === null) {
            return self::attributeEof("[{$name->value}]");
        }

        if ($token->type === 'delim' && $token->value !== '=') {
            $lookahead = $offset + 1;
            self::skipWhitespace($tokens, $lookahead);

            return self::error($tokens[$lookahead]->value ?? 'END-OF-FILE');
        }

        if ($token->type !== 'delim' || $token->value !== '=') {
            return self::error($token->value);
        }

        $offset++;
        self::skipWhitespace($tokens, $offset);

        $value = self::serializeAttributeValue($tokens[$offset] ?? null);
        if ($value === null) {
            return self::error($tokens[$offset]->value ?? 'END-OF-FILE');
        }

        $offset++;
        self::skipWhitespace($tokens, $offset);

        $modifier = '';
        $token = $tokens[$offset] ?? null;
        if ($token?->type === 'ident') {
            $modifier = strtolower($token->value);
            if ($modifier !== 'i' && $modifier !== 's') {
                return self::error($token->value);
            }

            $offset++;
            self::skipWhitespace($tokens, $offset);
        }

        $serialized = "[{$name->value}={$value}{$modifier}]";
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return self::attributeEof($serialized);
        }

        if ($token->type !== 'right-square-bracket') {
            return self::error($token->value);
        }

        $offset++;
        self::skipWhitespace($tokens, $offset);

        if (isset($tokens[$offset])) {
            return self::error($tokens[$offset]->value);
        }

        return [
            'value' => $serialized,
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parsePseudoFunctionSelector(array $tokens): ?array
    {
        if (
            count($tokens) < 2
            || $tokens[0]->type !== 'colon'
            || $tokens[1]->type !== 'function'
        ) {
            return null;
        }

        $name = strtolower(substr($tokens[1]->value, 0, -1));
        if (! in_array($name, ['not', 'has', 'nth-child', 'nth-last-child'], true)) {
            return self::error($tokens[1]->value);
        }

        [$body, $closed, $trailing] = self::pseudoFunctionBody($tokens);
        if ($name === 'nth-child' || $name === 'nth-last-child') {
            return $this->parseNthChildPseudoFunction($name, $body, $closed, $trailing);
        }

        if ($name === 'has') {
            return $this->parseHasPseudoFunction($body, $closed, $trailing);
        }

        if ($trailing !== []) {
            return self::error(self::firstUnexpectedToken($trailing));
        }

        $selectorParts = self::splitSelectorList($body);
        if ($selectorParts === []) {
            $errors = [];

            if (! $closed) {
                $errors[] = self::unexpectedTokenError('END-OF-FILE');
                $errors[] = self::eofPseudoFunctionError();
            }

            $errors[] = self::emptyPseudoFunctionError($name);

            return [
                'value' => '',
                'errors' => $errors,
            ];
        }

        $serialized = [];
        $errors = [];
        $invalid = false;
        foreach ($selectorParts as $selectorTokens) {
            $selector = $this->parseSelectorComponent($selectorTokens);
            if ($selector === null) {
                $errors[] = self::unexpectedTokenError(self::firstUnexpectedToken($selectorTokens));
                $invalid = true;
                break;
            }

            if ($selector['errors'] !== []) {
                array_push($errors, ...$selector['errors']);
            }

            if ($selector['value'] === '') {
                $invalid = true;
                break;
            }

            $serialized[] = $selector['value'];
        }

        if (! $closed) {
            $errors[] = self::eofPseudoFunctionError();
        }

        if ($invalid) {
            $errors[] = self::emptyPseudoFunctionError($name);

            return [
                'value' => '',
                'errors' => $errors,
            ];
        }

        return [
            'value' => sprintf(':%s(%s)', $name, implode(', ', $serialized)),
            'errors' => $errors,
        ];
    }

    /**
     * @param list<Token> $body
     * @param list<Token> $trailing
     * @return array{value: string, errors: list<string>}
     */
    private function parseHasPseudoFunction(array $body, bool $closed, array $trailing): array
    {
        [$selectorParts, $errors] = self::splitForgivingSelectorList($body);
        $serialized = [];

        foreach ($selectorParts as $selectorTokens) {
            $selector = $this->parseSelectorComponent($selectorTokens);
            if ($selector === null) {
                $errors[] = self::unexpectedTokenError(self::firstUnexpectedToken($selectorTokens));
                continue;
            }

            if ($selector['errors'] !== []) {
                array_push($errors, ...$selector['errors']);
            }

            if ($selector['value'] !== '') {
                $serialized[] = $selector['value'];
            }
        }

        if (! $closed) {
            $errors[] = self::eofPseudoFunctionError();
        }

        if ($trailing !== []) {
            $errors[] = self::unexpectedTokenError(self::firstUnexpectedToken($trailing));

            return [
                'value' => '',
                'errors' => $errors,
            ];
        }

        if ($serialized === []) {
            $errors[] = self::emptyPseudoFunctionError('has');

            return [
                'value' => '',
                'errors' => $errors,
            ];
        }

        return [
            'value' => sprintf(':has(%s)', implode(', ', $serialized)),
            'errors' => $errors,
        ];
    }

    /**
     * @param list<Token> $body
     * @param list<Token> $trailing
     * @return array{value: string, errors: list<string>}
     */
    private function parseNthChildPseudoFunction(string $name, array $body, bool $closed, array $trailing): array
    {
        [$anPlusBTokens, $ofSelectorTokens] = self::splitNthChildOfSelector($body);
        $anPlusB = (new AnPlusBParser())->parse($this->serializeAnPlusBTokens($anPlusBTokens));
        $errors = [];

        if ($anPlusB['errors'] !== []) {
            $unexpectedToken = self::unexpectedAnPlusBToken($anPlusB['errors']);
            if ($unexpectedToken !== null && self::shouldReportNthChildUnexpectedToken($anPlusBTokens, $unexpectedToken)) {
                $errors[] = self::unexpectedTokenError($unexpectedToken);
            }

            if (! $closed) {
                $errors[] = self::eofPseudoFunctionError();
            }

            $errors[] = self::emptyPseudoFunctionError($name);

            return [
                'value' => '',
                'errors' => $errors,
            ];
        }

        $ofSelector = null;
        if ($ofSelectorTokens !== null) {
            $ofSelectorTokens = self::trimTokenList($ofSelectorTokens);
            if ($ofSelectorTokens === []) {
                if (! $closed) {
                    $errors[] = self::eofPseudoFunctionError();
                }

                $errors[] = self::emptyPseudoFunctionError($name);

                return [
                    'value' => '',
                    'errors' => $errors,
                ];
            }

            $selector = $this->parseSelectorComponent($ofSelectorTokens);
            if ($selector === null) {
                $errors[] = self::unexpectedTokenError(self::firstUnexpectedToken($ofSelectorTokens));

                if (! $closed) {
                    $errors[] = self::eofPseudoFunctionError();
                }

                $errors[] = self::emptyPseudoFunctionError($name);

                return [
                    'value' => '',
                    'errors' => $errors,
                ];
            }

            if ($selector['errors'] !== []) {
                array_push($errors, ...$selector['errors']);
            }

            if ($selector['value'] === '') {
                if (! $closed) {
                    $errors[] = self::eofPseudoFunctionError();
                }

                $errors[] = self::emptyPseudoFunctionError($name);

                return [
                    'value' => '',
                    'errors' => $errors,
                ];
            }

            $ofSelector = $selector['value'];
        }

        if (! $closed) {
            $errors[] = self::eofPseudoFunctionError();
        }

        if ($trailing !== []) {
            $errors[] = self::unexpectedTokenError(self::firstUnexpectedToken($trailing));

            return [
                'value' => '',
                'errors' => $errors,
            ];
        }

        return [
            'value' => $ofSelector === null
                ? sprintf(':%s(%s)', $name, $anPlusB['value'])
                : sprintf(':%s(%s of %s)', $name, $anPlusB['value'], $ofSelector),
            'errors' => $errors,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{list<Token>, bool, list<Token>}
     */
    private static function pseudoFunctionBody(array $tokens): array
    {
        $body = [];
        $stack = ['right-parenthesis'];
        $offset = 2;
        $count = count($tokens);

        while ($offset < $count) {
            $token = $tokens[$offset];

            if ($token->type === 'function') {
                $stack[] = 'right-parenthesis';
                $body[] = $token;
                $offset++;
                continue;
            }

            $closingToken = self::closingTokenFor($token);
            if ($closingToken !== null) {
                $stack[] = $closingToken;
                $body[] = $token;
                $offset++;
                continue;
            }

            $expected = $stack[array_key_last($stack)];
            if ($token->type === $expected) {
                array_pop($stack);

                if ($stack === []) {
                    return [$body, true, array_slice($tokens, $offset + 1)];
                }

                $body[] = $token;
                $offset++;
                continue;
            }

            $body[] = $token;
            $offset++;
        }

        return [$body, false, []];
    }

    private static function closingTokenFor(Token $token): ?string
    {
        return match ($token->type) {
            'left-curly-bracket' => 'right-curly-bracket',
            'left-parenthesis' => 'right-parenthesis',
            'left-square-bracket' => 'right-square-bracket',
            default => null,
        };
    }

    private static function parseTypeSelector(string $selector): ?string
    {
        $selector = trim($selector);

        if (preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_-]*|\*)? ?\|(?:[a-zA-Z_][a-zA-Z0-9_-]*|\*)$/', $selector) === 1) {
            return $selector;
        }

        return null;
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
     * @return list<Token>
     */
    private function tokenize(string $selector): array
    {
        $tokens = $this->tokenizer->tokenize($selector);
        $this->rawTokenValues = [];

        $offset = 0;
        foreach ($tokens as $token) {
            $this->rawTokenValues[spl_object_id($token)] = substr($selector, $offset, $token->length);
            $offset += $token->length;
        }

        return $this->stripWhitespaceTokens($tokens);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function hasTokenType(array $tokens, string $type): bool
    {
        foreach ($tokens as $token) {
            if ($token->type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function hasTopLevelTokenType(array $tokens, string $type): bool
    {
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === $type && $stack === []) {
                return true;
            }

            self::updateTokenStack($stack, $token);
        }

        return false;
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

    private static function isCombinatorToken(Token $token): bool
    {
        return $token->type === 'delim' && in_array($token->value, ['>', '+', '~'], true);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isCombinatorAt(array $tokens, int $offset): bool
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null || $token->type !== 'delim') {
            return false;
        }

        if (in_array($token->value, ['>', '+', '~'], true)) {
            return true;
        }

        return $token->value === '|'
            && ($tokens[$offset + 1] ?? null)?->type === 'delim'
            && $tokens[$offset + 1]->value === '|';
    }

    /**
     * @param list<Token> $tokens
     */
    private static function consumeCombinator(array $tokens, int &$offset): string
    {
        $token = $tokens[$offset];
        if ($token->value !== '|') {
            $offset++;

            return $token->value;
        }

        $offset += 2;

        return '||';
    }

    /**
     * @param list<Token> $tokens
     */
    private static function endsWithCombinator(array $tokens): bool
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return false;
        }

        $last = array_key_last($tokens);
        if (self::isCombinatorToken($tokens[$last])) {
            return true;
        }

        return $last > 0
            && $tokens[$last - 1]->type === 'delim'
            && $tokens[$last - 1]->value === '|'
            && $tokens[$last]->type === 'delim'
            && $tokens[$last]->value === '|';
    }

    private static function serializeAttributeValue(?Token $token): ?string
    {
        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            return "\"{$token->value}\"";
        }

        if ($token->type !== 'string') {
            return null;
        }

        return str_replace('\\"', '\\000022', $token->value);
    }

    /**
     * @param list<Token> $tokens
     */
    private function serializeSimpleSelector(array $tokens): ?string
    {
        $selector = $this->parseSelectorComponent($tokens);
        if ($selector === null || $selector['errors'] !== []) {
            return null;
        }

        return $selector['value'];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseSimpleSelector(array $tokens): ?array
    {
        $tokens = $this->stripWhitespaceTokens($tokens);

        if ($tokens === []) {
            return null;
        }

        $pseudoFunction = $this->parsePseudoFunctionSelector($tokens);
        if ($pseudoFunction !== null) {
            return $pseudoFunction;
        }

        $class = $this->parseClassSelector($tokens);
        if ($class !== null) {
            return $class;
        }

        $attribute = $this->parseAttributeSelector($tokens);
        if ($attribute !== null) {
            return $attribute;
        }

        if (count($tokens) === 1 && in_array($tokens[0]->type, ['ident', 'hash'], true)) {
            return ['value' => $tokens[0]->value, 'errors' => []];
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseSelectorComponent(array $tokens): ?array
    {
        $complex = $this->parseComplexSelector($tokens);
        if ($complex !== null) {
            return $complex;
        }

        return $this->parseSimpleSelector($tokens);
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function parseComplexSelector(array $tokens): ?array
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $compound = $this->consumeCompoundSelector($tokens, $offset);
        if ($compound === null) {
            if (self::isCombinatorAt($tokens, $offset)) {
                return self::error($tokens[$offset]->value);
            }

            return null;
        }

        if ($compound['errors'] !== []) {
            return $compound;
        }

        $serialized = [$compound['value']];
        $count = count($tokens);

        while ($offset < $count) {
            $hadWhitespace = false;
            while (($tokens[$offset] ?? null)?->type === 'whitespace') {
                $hadWhitespace = true;
                $offset++;
            }

            if ($offset >= $count) {
                break;
            }

            if (self::isCombinatorAt($tokens, $offset)) {
                $combinator = self::consumeCombinator($tokens, $offset);
                self::skipWhitespace($tokens, $offset);

                if ($offset >= $count) {
                    return self::error('END-OF-FILE');
                }
            } elseif ($hadWhitespace) {
                $combinator = ' ';
            } else {
                return self::error($tokens[$offset]->value);
            }

            $compound = $this->consumeCompoundSelector($tokens, $offset);
            if ($compound === null) {
                return self::error(self::firstUnexpectedToken(array_slice($tokens, $offset)));
            }

            if ($compound['errors'] !== []) {
                return $compound;
            }

            $serialized[] = $combinator === ' '
                ? ' ' . $compound['value']
                : sprintf(' %s %s', $combinator, $compound['value']);
        }

        return [
            'value' => implode('', $serialized),
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function parseRequiredComplexSelector(array $tokens): array
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        return $this->parseComplexSelector($tokens) ?? self::error(self::firstUnexpectedToken($tokens));
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function parseRequiredCompoundSelector(array $tokens): array
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        $offset = 0;
        $selector = $this->consumeCompoundSelector($tokens, $offset);
        if ($selector === null) {
            return self::error(self::firstUnexpectedToken($tokens));
        }

        return $this->completeSingularSelector($selector, $tokens, $offset);
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function parseRequiredSimpleSelector(array $tokens): array
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        if (
            $tokens[0]->type === 'colon'
            && ($tokens[1] ?? null)?->type === 'colon'
        ) {
            return self::error(':');
        }

        $offset = 0;
        $typeSelector = self::consumeTypeSelector($tokens, $offset);
        if ($typeSelector !== null) {
            return $this->completeSingularSelector(
                ['value' => $typeSelector, 'errors' => []],
                $tokens,
                $offset,
            );
        }

        $selector = $this->consumeNonTypeSimpleSelector($tokens, $offset);
        if ($selector === null) {
            return self::error(self::firstUnexpectedToken($tokens));
        }

        return $this->completeSingularSelector($selector, $tokens, $offset);
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function parseRequiredRelativeSelector(array $tokens): array
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        $offset = 0;
        $combinator = '';
        if (self::isCombinatorAt($tokens, $offset)) {
            $combinator = self::consumeCombinator($tokens, $offset);
            self::skipWhitespace($tokens, $offset);

            if (! isset($tokens[$offset])) {
                return self::error('END-OF-FILE');
            }
        }

        $selector = $this->consumeCompoundSelector($tokens, $offset);
        if ($selector === null) {
            return self::error(self::firstUnexpectedToken(array_slice($tokens, $offset)));
        }

        if ($selector['errors'] !== []) {
            return $selector;
        }

        if ($combinator !== '') {
            $selector['value'] = "{$combinator} {$selector['value']}";
        }

        return $this->completeSingularSelector($selector, $tokens, $offset);
    }

    /**
     * @param array{value: string, errors: list<string>} $selector
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function completeSingularSelector(array $selector, array $tokens, int $offset): array
    {
        if ($selector['errors'] !== []) {
            return $selector;
        }

        self::skipWhitespace($tokens, $offset);
        if (isset($tokens[$offset])) {
            return self::error($tokens[$offset]->value);
        }

        return $selector;
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function parseRelativeSelector(array $tokens): array
    {
        $tokens = self::trimTokenList($tokens);
        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        $offset = 0;
        $combinator = '';
        if (self::isCombinatorAt($tokens, $offset)) {
            $combinator = self::consumeCombinator($tokens, $offset);
            self::skipWhitespace($tokens, $offset);

            if (! isset($tokens[$offset])) {
                return self::error('END-OF-FILE');
            }
        }

        $complex = $this->parseComplexSelector(array_slice($tokens, $offset));
        if ($complex === null) {
            return self::error(self::firstUnexpectedToken(array_slice($tokens, $offset)));
        }

        if ($complex['errors'] !== []) {
            return $complex;
        }

        return [
            'value' => $combinator === '' ? $complex['value'] : "{$combinator} {$complex['value']}",
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function consumeCompoundSelector(array $tokens, int &$offset): ?array
    {
        $start = $offset;
        $serialized = [];

        $typeSelector = self::consumeTypeSelector($tokens, $offset);
        if ($typeSelector !== null) {
            $serialized[] = $typeSelector;
        }

        $count = count($tokens);
        while ($offset < $count) {
            if (
                $tokens[$offset]->type === 'whitespace'
                || $tokens[$offset]->type === 'comma'
                || self::isCombinatorAt($tokens, $offset)
            ) {
                break;
            }

            $selector = $this->consumeNonTypeSimpleSelector($tokens, $offset);
            if ($selector === null) {
                if ($serialized === []) {
                    $offset = $start;

                    return null;
                }

                return self::error($tokens[$offset]->value);
            }

            if ($selector['errors'] !== []) {
                return $selector;
            }

            $serialized[] = $selector['value'];
        }

        if ($serialized === []) {
            $offset = $start;

            return null;
        }

        return [
            'value' => implode('', $serialized),
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     */
    private static function consumeTypeSelector(array $tokens, int &$offset): ?string
    {
        $start = $offset;
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $offset++;

            if (($tokens[$offset] ?? null)?->type === 'delim' && $tokens[$offset]->value === '|') {
                $name = $tokens[$offset + 1] ?? null;
                if ($name?->type === 'ident' || ($name?->type === 'delim' && $name->value === '*')) {
                    $offset += 2;

                    return "{$token->value}|{$name->value}";
                }

                $offset = $start;

                return null;
            }

            return $token->value;
        }

        if ($token->type === 'delim' && $token->value === '*') {
            if (($tokens[$offset + 1] ?? null)?->type === 'delim' && $tokens[$offset + 1]->value === '|') {
                $name = $tokens[$offset + 2] ?? null;
                if ($name?->type === 'ident' || ($name?->type === 'delim' && $name->value === '*')) {
                    $offset += 3;

                    return "*|{$name->value}";
                }
            }

            $offset++;

            return '*';
        }

        if ($token->type === 'delim' && $token->value === '|') {
            $name = $tokens[$offset + 1] ?? null;
            if ($name?->type === 'ident' || ($name?->type === 'delim' && $name->value === '*')) {
                $offset += 2;

                return "|{$name->value}";
            }
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}|null
     */
    private function consumeNonTypeSimpleSelector(array $tokens, int &$offset): ?array
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'hash') {
            $offset++;

            return ['value' => $token->value, 'errors' => []];
        }

        if ($token->type === 'delim' && $token->value === '.') {
            $name = $tokens[$offset + 1] ?? null;
            if ($name?->type !== 'ident') {
                return self::error($name?->value ?? 'END-OF-FILE');
            }

            $offset += 2;

            return ['value' => ".{$name->value}", 'errors' => []];
        }

        if ($token->type === 'left-square-bracket') {
            return $this->consumeAttributeSelector($tokens, $offset);
        }

        if ($token->type === 'colon') {
            return $this->consumePseudoSelector($tokens, $offset);
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function consumeAttributeSelector(array $tokens, int &$offset): array
    {
        $end = $offset;
        $count = count($tokens);
        while ($end < $count && $tokens[$end]->type !== 'right-square-bracket') {
            $end++;
        }

        $closed = $end < $count;
        $slice = array_slice($tokens, $offset, $closed ? $end - $offset + 1 : null);
        $offset = $closed ? $end + 1 : $count;

        return $this->parseAttributeSelector($slice) ?? self::error(self::firstUnexpectedToken($slice));
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function consumePseudoSelector(array $tokens, int &$offset): array
    {
        $next = $tokens[$offset + 1] ?? null;
        if ($next?->type === 'colon') {
            $name = $tokens[$offset + 2] ?? null;
            $offset += $name === null ? 2 : 3;

            return self::error($name?->value ?? 'END-OF-FILE');
        }

        if ($next?->type === 'function') {
            return $this->consumePseudoFunctionSelector($tokens, $offset);
        }

        if ($next?->type !== 'ident') {
            $offset += $next === null ? 1 : 2;

            return self::error($next?->value ?? 'END-OF-FILE');
        }

        $name = strtolower($next->value);
        $offset += 2;

        if (! isset(self::SUPPORTED_PSEUDO_CLASSES[$name])) {
            return self::error($next->value);
        }

        return [
            'value' => ":{$name}",
            'errors' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{value: string, errors: list<string>}
     */
    private function consumePseudoFunctionSelector(array $tokens, int &$offset): array
    {
        $start = $offset;
        $end = $offset + 2;
        $stack = ['right-parenthesis'];
        $count = count($tokens);

        while ($end < $count && $stack !== []) {
            $token = $tokens[$end];

            if ($token->type === 'function') {
                $stack[] = 'right-parenthesis';
                $end++;
                continue;
            }

            $closingToken = self::closingTokenFor($token);
            if ($closingToken !== null) {
                $stack[] = $closingToken;
                $end++;
                continue;
            }

            if ($token->type === $stack[array_key_last($stack)]) {
                array_pop($stack);
            }

            $end++;
        }

        $slice = array_slice($tokens, $start, $end - $start);
        $offset = $end;

        return $this->parsePseudoFunctionSelector($slice) ?? self::error(self::firstUnexpectedToken($slice));
    }

    /**
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    private static function splitSelectorList(array $tokens): array
    {
        $parts = [];
        $part = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === 'comma' && $stack === []) {
                $part = self::trimTokenList($part);
                if ($part !== []) {
                    $parts[] = $part;
                }

                $part = [];
                continue;
            }

            if ($token->type === 'function') {
                $stack[] = 'right-parenthesis';
                $part[] = $token;
                continue;
            }

            $closingToken = self::closingTokenFor($token);
            if ($closingToken !== null) {
                $stack[] = $closingToken;
                $part[] = $token;
                continue;
            }

            if ($stack !== [] && $token->type === $stack[array_key_last($stack)]) {
                array_pop($stack);
                $part[] = $token;
                continue;
            }

            $part[] = $token;
        }

        $part = self::trimTokenList($part);
        if ($part !== []) {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @param list<Token> $tokens
     * @return array{list<list<Token>>, list<string>}
     */
    private static function splitForgivingSelectorList(array $tokens): array
    {
        $parts = [];
        $errors = [];
        $part = [];
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === 'comma' && $stack === []) {
                $part = self::trimTokenList($part);
                if ($part === []) {
                    $errors[] = self::unexpectedTokenError(',');
                } else {
                    $parts[] = $part;
                }

                $part = [];
                continue;
            }

            if ($token->type === 'function') {
                $stack[] = 'right-parenthesis';
                $part[] = $token;
                continue;
            }

            $closingToken = self::closingTokenFor($token);
            if ($closingToken !== null) {
                $stack[] = $closingToken;
                $part[] = $token;
                continue;
            }

            if ($stack !== [] && $token->type === $stack[array_key_last($stack)]) {
                array_pop($stack);
                $part[] = $token;
                continue;
            }

            $part[] = $token;
        }

        $part = self::trimTokenList($part);
        if ($part !== []) {
            $parts[] = $part;
        }

        return [$parts, $errors];
    }

    /**
     * @param list<Token> $tokens
     * @return array{list<Token>, list<Token>|null}
     */
    private static function splitNthChildOfSelector(array $tokens): array
    {
        $stack = [];

        foreach ($tokens as $offset => $token) {
            if ($stack === [] && $token->type === 'ident' && strtolower($token->value) === 'of') {
                return [
                    self::trimTokenList(array_slice($tokens, 0, $offset)),
                    self::trimTokenList(array_slice($tokens, $offset + 1)),
                ];
            }

            self::updateTokenStack($stack, $token);
        }

        return [self::trimTokenList($tokens), null];
    }

    /**
     * @param list<string> $stack
     */
    private static function updateTokenStack(array &$stack, Token $token): void
    {
        if ($token->type === 'function') {
            $stack[] = 'right-parenthesis';
            return;
        }

        $closingToken = self::closingTokenFor($token);
        if ($closingToken !== null) {
            $stack[] = $closingToken;
            return;
        }

        if ($stack !== [] && $token->type === $stack[array_key_last($stack)]) {
            array_pop($stack);
        }
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
     * @param list<Token> $tokens
     */
    private static function firstUnexpectedToken(array $tokens): string
    {
        foreach ($tokens as $token) {
            if ($token->type !== 'whitespace') {
                return $token->value;
            }
        }

        return 'END-OF-FILE';
    }

    /**
     * @param list<Token> $tokens
     */
    private function serializeAnPlusBTokens(array $tokens): string
    {
        $value = '';
        foreach ($tokens as $token) {
            $value .= $this->anPlusBTokenSourceValue($token);
        }

        return $value;
    }

    private function anPlusBTokenSourceValue(Token $token): string
    {
        $raw = $this->rawTokenValues[spl_object_id($token)] ?? null;
        if ($raw !== null && in_array($token->type, ['dimension', 'number'], true)) {
            if ($token->type === 'dimension' && self::shouldUseDecodedEscapedDimension($raw)) {
                return $token->value;
            }

            return $raw;
        }

        if (
            $token->type === 'number'
            && $token->value !== ''
            && ! str_starts_with($token->value, '+')
            && ! str_starts_with($token->value, '-')
            && $token->length > strlen($token->value)
        ) {
            return "+{$token->value}";
        }

        return $token->value;
    }

    private static function shouldUseDecodedEscapedDimension(string $raw): bool
    {
        if (! str_contains($raw, '\\')) {
            return false;
        }

        return preg_match('/^[+-]?(?:\d+\.\d+|\.\d+|\d+[eE][+-]?\d+)/', $raw) !== 1;
    }

    /**
     * @param list<string> $errors
     */
    private static function unexpectedAnPlusBToken(array $errors): ?string
    {
        $error = $errors[0] ?? null;
        if ($error !== null && preg_match('/Unexpected token: (.+)$/', $error, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function shouldReportNthChildUnexpectedToken(array $tokens, string $unexpectedToken): bool
    {
        if (str_contains($unexpectedToken, '.') || str_contains($unexpectedToken, '%')) {
            return true;
        }

        foreach ($tokens as $token) {
            if ($token->type === 'whitespace') {
                continue;
            }

            return ! in_array($token->type, ['ident', 'dimension', 'number', 'delim'], true);
        }

        return false;
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    private static function error(string $token): array
    {
        return [
            'value' => '',
            'errors' => [self::unexpectedTokenError($token)],
        ];
    }

    private static function unexpectedTokenError(string $token): string
    {
        return sprintf('Syntax error. Selectors. Unexpected token: %s', $token);
    }

    private static function eofPseudoFunctionError(): string
    {
        return 'Syntax error. Selectors. End Of File in pseudo function';
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    private static function attributeEof(string $value): array
    {
        return [
            'value' => $value,
            'errors' => ['Syntax error. Selectors. End Of File in attribute selector'],
        ];
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    private static function emptyPseudoFunction(string $name): array
    {
        return [
            'value' => '',
            'errors' => [self::emptyPseudoFunctionError($name)],
        ];
    }

    private static function emptyPseudoFunctionError(string $name): string
    {
        return sprintf("Syntax error. Selectors. Pseudo function can't be empty: %s()", $name);
    }
}
