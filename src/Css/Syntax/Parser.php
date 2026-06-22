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
     * @return list<array{type: string, prelude: list<array{type: string, value: string}>, block: list<array<string, mixed>>}>
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
                    'block' => $this->consumeEmptyBlock($tokens, $offset),
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
    private function consumeEmptyBlock(array $tokens, int &$offset): array
    {
        $depth = 0;

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($token->type === 'left-curly-bracket') {
                $depth++;
            } elseif ($token->type === 'right-curly-bracket') {
                if ($depth === 0) {
                    $offset++;
                    break;
                }

                $depth--;
            }

            $offset++;
        }

        return [];
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
}
