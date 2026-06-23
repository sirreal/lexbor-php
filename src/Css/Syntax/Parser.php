<?php

declare(strict_types=1);

namespace Lexbor\Css\Syntax;

final class Parser
{
    public function __construct(
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseListRules(string $css): array
    {
        $tokens = $this->tokenizer->tokenize($css);
        $rules = [];
        $offset = 0;

        while ($offset < count($tokens)) {
            $this->skipTopLevelIgnored($tokens, $offset);

            if ($offset >= count($tokens)) {
                break;
            }

            if ($tokens[$offset]->type === 'at-keyword') {
                $rules[] = $this->consumeAtRule($tokens, $offset, true, false);
                continue;
            }

            $rules[] = $this->consumeQualifiedRule($tokens, $offset);
        }

        return $rules;
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, prelude: list<array{type: string, value: string}>, block: list<array<string, mixed>>}
     */
    private function consumeQualifiedRule(array $tokens, int &$offset): array
    {
        $prelude = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($token->type === 'comment') {
                $offset++;
                continue;
            }

            if ($token->type === 'left-curly-bracket' && $blockEndStack === []) {
                $offset++;

                return [
                    'type' => 'qualified-rule',
                    'prelude' => $prelude,
                    'block' => $this->consumeBlock($tokens, $offset),
                ];
            }

            $prelude[] = self::serializeToken($token);
            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }

        return [
            'type' => 'qualified-rule',
            'prelude' => $prelude,
            'block' => [],
        ];
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipTopLevelIgnored(array $tokens, int &$offset): void
    {
        while (
            $offset < count($tokens)
            && in_array($tokens[$offset]->type, ['whitespace', 'CDO', 'CDC', 'comment'], true)
        ) {
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     * @return list<array<string, mixed>>
     */
    private function consumeBlock(array $tokens, int &$offset, bool $includeNestedQualifiedEmptyBlock = false): array
    {
        $items = [];
        $declarations = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($token->type === 'right-curly-bracket') {
                $offset++;
                break;
            }

            if (in_array($token->type, ['whitespace', 'semicolon', 'comment'], true)) {
                $offset++;
                continue;
            }

            if ($token->type === 'at-keyword') {
                self::appendDeclarations($items, $declarations);
                $items[] = $this->consumeAtRule($tokens, $offset);
                continue;
            }

            if ($token->type === 'ident' && $this->hasDeclarationColon($tokens, $offset)) {
                $declarations[] = $this->consumeDeclaration($tokens, $offset);
                continue;
            }

            self::appendDeclarations($items, $declarations);
            $items[] = $this->consumeNestedQualifiedRule($tokens, $offset, $includeNestedQualifiedEmptyBlock);
        }

        self::appendDeclarations($items, $declarations);

        return $items;
    }

    /**
     * @param list<Token> $tokens
     * @return array<string, mixed>
     */
    private function consumeAtRule(
        array $tokens,
        int &$offset,
        bool $includeEmptyBlock = false,
        bool $includeNestedQualifiedEmptyBlock = true,
    ): array
    {
        $rule = [
            'type' => 'at-rule',
            'name' => $tokens[$offset]->value,
            'prelude' => [],
        ];

        if ($includeEmptyBlock) {
            $rule['block'] = [];
        }

        $offset++;

        if (($tokens[$offset] ?? null)?->type === 'whitespace') {
            $offset++;
        }

        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === []) {
                if ($token->type === 'semicolon') {
                    $offset++;
                    return $rule;
                }

                if ($token->type === 'right-curly-bracket') {
                    return $rule;
                }

                if ($token->type === 'left-curly-bracket') {
                    $offset++;
                    $rule['block'] = $this->consumeBlock($tokens, $offset, $includeNestedQualifiedEmptyBlock);

                    if (! $this->isAtBlockLastItem($tokens, $offset)) {
                        $rule['block'] = self::stripEmptyQualifiedBlocks($rule['block']);
                    }

                    return $rule;
                }
            }

            if ($token->type !== 'comment') {
                $rule['prelude'][] = self::serializeToken($token);
                $this->updateBlockEndStack($blockEndStack, $token);
            }

            $offset++;
        }

        return $rule;
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
     */
    private function isAtBlockLastItem(array $tokens, int $offset): bool
    {
        while ($offset < count($tokens) && in_array($tokens[$offset]->type, ['whitespace', 'comment'], true)) {
            $offset++;
        }

        return $offset >= count($tokens) || $tokens[$offset]->type === 'right-curly-bracket';
    }

    /**
     * @param list<Token> $tokens
     * @return array<string, mixed>
     */
    private function consumeNestedQualifiedRule(array $tokens, int &$offset, bool $includeEmptyBlock): array
    {
        $prelude = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === [] && ($token->type === 'right-curly-bracket' || $token->type === 'semicolon')) {
                break;
            }

            if ($token->type !== 'comment') {
                $prelude[] = self::serializeToken($token);
                $this->updateBlockEndStack($blockEndStack, $token);
            }

            $offset++;
        }

        $rule = [
            'type' => 'qualified-rule',
            'prelude' => $prelude,
        ];

        if ($includeEmptyBlock) {
            $rule['block'] = [];
        }

        return $rule;
    }

    /**
     * @param list<Token> $tokens
     * @return array{name: string, value: string, important: bool}
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

        $valueTokens = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === [] && $token->type === 'right-curly-bracket') {
                break;
            }

            if ($blockEndStack === [] && $token->type === 'semicolon') {
                $offset++;
                break;
            }

            $valueTokens[] = $token;
            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }

        [$valueTokens, $important] = self::extractImportant($valueTokens);

        return [
            'name' => $name,
            'value' => self::serializeComponentValue($valueTokens),
            'important' => $important,
        ];
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
     * @return array{type: string, value: string}
     */
    private static function serializeToken(Token $token): array
    {
        return [
            'type' => $token->type,
            'value' => $token->value,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array{name: string, value: string, important: bool}> $declarations
     */
    private static function appendDeclarations(array &$items, array &$declarations): void
    {
        if ($declarations === []) {
            return;
        }

        $items[] = [
            'type' => 'declarations',
            'declarations' => $declarations,
        ];

        $declarations = [];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function stripEmptyQualifiedBlocks(array $items): array
    {
        foreach ($items as &$item) {
            if (($item['type'] ?? null) === 'qualified-rule' && ($item['block'] ?? null) === []) {
                unset($item['block']);
            }
        }

        return $items;
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
