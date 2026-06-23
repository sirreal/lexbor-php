<?php

declare(strict_types=1);

namespace Lexbor\Css\Selectors;

use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;

final class Parser
{
    public function __construct(
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parse(string $selector): array
    {
        $tokens = $this->stripWhitespaceTokens($this->tokenizer->tokenize($selector));

        if ($tokens === []) {
            return self::error('END-OF-FILE');
        }

        $class = $this->parseClassSelector($tokens);
        if ($class !== null) {
            return $class;
        }

        if (count($tokens) === 1) {
            return match ($tokens[0]->type) {
                'ident', 'hash' => ['value' => $tokens[0]->value, 'errors' => []],
                default => self::error($tokens[0]->value),
            };
        }

        $attribute = $this->parseAttributeSelector($tokens);
        if ($attribute !== null) {
            return $attribute;
        }

        $typeSelector = self::parseTypeSelector($selector);
        if ($typeSelector !== null) {
            return ['value' => $typeSelector, 'errors' => []];
        }

        return self::error(self::firstUnexpectedToken($tokens));
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
            return self::error($tokens[$offset]->value);
        }

        return ['value' => ".{$tokens[1]->value}", 'errors' => []];
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
     * @param list<Token> $tokens
     */
    private static function skipWhitespace(array $tokens, int &$offset): void
    {
        while (($tokens[$offset] ?? null)?->type === 'whitespace') {
            $offset++;
        }
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
     * @return array{value: string, errors: list<string>}
     */
    private static function error(string $token): array
    {
        return [
            'value' => '',
            'errors' => [sprintf('Syntax error. Selectors. Unexpected token: %s', $token)],
        ];
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
}
