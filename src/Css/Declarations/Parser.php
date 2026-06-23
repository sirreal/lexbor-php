<?php

declare(strict_types=1);

namespace Lexbor\Css\Declarations;

use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;

final class Parser
{
    private const array KNOWN_PROPERTIES = [
        'height' => true,
        'text-decoration' => true,
        'width' => true,
    ];

    private const array LENGTH_UNITS = [
        'cap' => true,
        'ch' => true,
        'cm' => true,
        'em' => true,
        'ex' => true,
        'ic' => true,
        'in' => true,
        'lh' => true,
        'mm' => true,
        'pc' => true,
        'pt' => true,
        'px' => true,
        'q' => true,
        'rem' => true,
        'rlh' => true,
        'vb' => true,
        'vh' => true,
        'vi' => true,
        'vmax' => true,
        'vmin' => true,
        'vw' => true,
    ];

    public function __construct(
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @return list<array{type: string, name: string, value: string, important: bool}>
     */
    public function parseList(string $css): array
    {
        $tokens = $this->tokenizer->tokenize($css);
        $offset = 0;
        $declarations = [];

        while ($offset < count($tokens)) {
            $this->skipDeclarationSeparators($tokens, $offset);

            if ($offset >= count($tokens)) {
                break;
            }

            if ($tokens[$offset]->type !== 'ident' || ! $this->hasDeclarationColon($tokens, $offset)) {
                $declarations[] = $this->consumeInvalidDeclaration($tokens, $offset);
                continue;
            }

            $declarations[] = $this->consumeDeclaration($tokens, $offset);
        }

        return $declarations;
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipDeclarationSeparators(array $tokens, int &$offset): void
    {
        while ($offset < count($tokens) && in_array($tokens[$offset]->type, ['whitespace', 'semicolon', 'comment'], true)) {
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private function hasDeclarationColon(array $tokens, int $offset): bool
    {
        $offset++;

        while ($offset < count($tokens) && $tokens[$offset]->type === 'whitespace') {
            $offset++;
        }

        return ($tokens[$offset] ?? null)?->type === 'colon';
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, name: string, value: string, important: bool}
     */
    private function consumeDeclaration(array $tokens, int &$offset): array
    {
        $name = $tokens[$offset]->value;
        $offset++;

        while ($offset < count($tokens) && $tokens[$offset]->type === 'whitespace') {
            $offset++;
        }

        if (($tokens[$offset] ?? null)?->type === 'colon') {
            $offset++;
        }

        while ($offset < count($tokens) && $tokens[$offset]->type === 'whitespace') {
            $offset++;
        }

        $valueTokens = $this->consumeValueTokens($tokens, $offset);
        [$valueTokens, $important] = self::extractImportant($valueTokens);
        $value = self::serializeComponentValue($valueTokens);

        return [
            'type' => $this->classifyDeclaration($name, $value, $valueTokens),
            'name' => $name,
            'value' => $value,
            'important' => $important,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, name: string, value: string, important: bool}
     */
    private function consumeInvalidDeclaration(array $tokens, int &$offset): array
    {
        $valueTokens = $this->consumeValueTokens($tokens, $offset);

        return [
            'type' => 'undef',
            'name' => '',
            'value' => self::serializeComponentValue($valueTokens),
            'important' => false,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function consumeValueTokens(array $tokens, int &$offset): array
    {
        $valueTokens = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === [] && $token->type === 'semicolon') {
                $offset++;
                break;
            }

            $valueTokens[] = $token;
            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }

        return $valueTokens;
    }

    /**
     * @param list<string> $blockEndStack
     */
    private function updateBlockEndStack(array &$blockEndStack, Token $token): void
    {
        $endToken = match ($token->type) {
            'function', 'left-parenthesis' => 'right-parenthesis',
            'left-square-bracket' => 'right-square-bracket',
            'left-curly-bracket' => 'right-curly-bracket',
            default => null,
        };

        if ($endToken !== null) {
            $blockEndStack[] = $endToken;
            return;
        }

        if ($blockEndStack !== [] && $token->type === end($blockEndStack)) {
            array_pop($blockEndStack);
        }
    }

    /**
     * @param list<Token> $valueTokens
     */
    private function classifyDeclaration(string $name, string $value, array $valueTokens): string
    {
        $property = strtolower($name);

        if (! isset(self::KNOWN_PROPERTIES[$property])) {
            return 'custom';
        }

        foreach ($valueTokens as $token) {
            if ($token->type === 'comment') {
                return 'undef';
            }
        }

        return match ($property) {
            'height', 'width' => self::isValidLengthSize($value, $valueTokens) ? 'property' : 'undef',
            default => 'undef',
        };
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidLengthSize(string $value, array $tokens): bool
    {
        $tokens = self::stripWhitespaceTokens($tokens);
        $lowerValue = strtolower($value);

        if (in_array($lowerValue, ['auto', 'inherit', 'initial', 'max-content', 'min-content', 'revert', 'unset'], true)) {
            return count($tokens) === 1 && $tokens[0]->type === 'ident';
        }

        if (count($tokens) !== 1) {
            return false;
        }

        $token = $tokens[0];

        if ($token->type === 'number') {
            return $value === '0';
        }

        if ($token->type === 'percentage') {
            return self::isNonNegativePercentage($value);
        }

        if ($token->type !== 'dimension') {
            return false;
        }

        if (! preg_match('/^\+?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?([a-zA-Z]+)$/i', $value, $matches)) {
            return false;
        }

        return isset(self::LENGTH_UNITS[strtolower($matches[1])]);
    }

    private static function isNonNegativePercentage(string $value): bool
    {
        return preg_match('/^\+?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?%$/i', $value) === 1;
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function stripWhitespaceTokens(array $tokens): array
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
    private static function serializeComponentValue(array $tokens): string
    {
        while ($tokens !== [] && $tokens[0]->type === 'whitespace') {
            array_shift($tokens);
        }

        while ($tokens !== [] && $tokens[array_key_last($tokens)]->type === 'whitespace') {
            array_pop($tokens);
        }

        $value = '';

        foreach ($tokens as $token) {
            if ($token->type === 'comment') {
                continue;
            }

            $value .= $token->value;
        }

        return $value;
    }

    /**
     * @param list<Token> $tokens
     * @return array{list<Token>, bool}
     */
    private static function extractImportant(array $tokens): array
    {
        while ($tokens !== [] && $tokens[array_key_last($tokens)]->type === 'whitespace') {
            array_pop($tokens);
        }

        $importantToken = array_pop($tokens);
        $bangToken = array_pop($tokens);

        if (
            $importantToken !== null
            && $bangToken !== null
            && $importantToken->type === 'ident'
            && strcasecmp($importantToken->value, 'important') === 0
            && $bangToken->type === 'delim'
            && $bangToken->value === '!'
        ) {
            while ($tokens !== [] && $tokens[array_key_last($tokens)]->type === 'whitespace') {
                array_pop($tokens);
            }

            return [$tokens, true];
        }

        if ($bangToken !== null) {
            $tokens[] = $bangToken;
        }

        if ($importantToken !== null) {
            $tokens[] = $importantToken;
        }

        return [$tokens, false];
    }
}
