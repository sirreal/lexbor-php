<?php

declare(strict_types=1);

namespace Lexbor\Css\Syntax;

final class Tokenizer
{
    /**
     * @return list<Token>
     */
    public function tokenize(string $css): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($css);

        while ($offset < $length) {
            $char = $css[$offset];

            if ($char === '/' && ($css[$offset + 1] ?? '') === '*') {
                [$value, $tokenLength] = self::consumeComment($css, $offset);
                $tokens[] = new Token('comment', $value, $tokenLength);
                $offset += $tokenLength;
                continue;
            }

            if (self::isWhitespace($char)) {
                [$value, $tokenLength] = self::consumeWhitespace($css, $offset);
                $tokens[] = new Token('whitespace', $value, $tokenLength);
                $offset += $tokenLength;
                continue;
            }

            if ($char === '#') {
                $hash = self::consumeHash($css, $offset);

                if ($hash !== null) {
                    [$value, $tokenLength] = $hash;
                    $tokens[] = new Token('hash', $value, $tokenLength);
                    $offset += $tokenLength;
                    continue;
                }
            }

            $type = self::singleTokenType($char);
            if ($type !== null) {
                $tokens[] = new Token($type, $char, 1);
            }

            $offset++;
        }

        return $tokens;
    }

    /**
     * @return array{string, int}
     */
    private static function consumeComment(string $css, int $offset): array
    {
        $value = '/*';
        $start = $offset;
        $offset += 2;
        $length = strlen($css);

        while ($offset < $length) {
            $char = $css[$offset];

            if ($char === '*' && ($css[$offset + 1] ?? '') === '/') {
                $value .= '*/';
                $offset += 2;

                return [$value, $offset - $start];
            }

            if ($char === "\0") {
                $value .= "\u{FFFD}";
                $offset++;
                continue;
            }

            if ($char === "\r") {
                $value .= "\n";
                $offset += ($css[$offset + 1] ?? '') === "\n" ? 2 : 1;
                continue;
            }

            $value .= $char === "\f" ? "\n" : $char;
            $offset++;
        }

        return [$value . '*/', $offset - $start];
    }

    /**
     * @return array{string, int}
     */
    private static function consumeWhitespace(string $css, int $offset): array
    {
        $value = '';
        $start = $offset;
        $length = strlen($css);

        while ($offset < $length && self::isWhitespace($css[$offset])) {
            $char = $css[$offset];

            if ($char === "\r") {
                $value .= "\n";
                $offset += ($css[$offset + 1] ?? '') === "\n" ? 2 : 1;
                continue;
            }

            $value .= $char === "\f" ? "\n" : $char;
            $offset++;
        }

        return [$value, $offset - $start];
    }

    /**
     * @return array{string, int}|null
     */
    private static function consumeHash(string $css, int $offset): ?array
    {
        $length = strlen($css);
        $cursor = $offset + 1;

        if ($cursor >= $length || ! self::isNameChar($css[$cursor])) {
            return null;
        }

        while ($cursor < $length && self::isNameChar($css[$cursor])) {
            $cursor++;
        }

        $tokenLength = $cursor - $offset;

        return [substr($css, $offset, $tokenLength), $tokenLength];
    }

    private static function isNameChar(string $char): bool
    {
        return ($char >= 'A' && $char <= 'Z')
            || ($char >= 'a' && $char <= 'z')
            || ($char >= '0' && $char <= '9')
            || $char === '-'
            || $char === '_';
    }

    private static function isWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === "\f";
    }

    private static function singleTokenType(string $char): ?string
    {
        return [
            '(' => 'left-parenthesis',
            ')' => 'right-parenthesis',
            ',' => 'comma',
            ':' => 'colon',
            ';' => 'semicolon',
            '[' => 'left-square-bracket',
            ']' => 'right-square-bracket',
            '{' => 'left-curly-bracket',
            '}' => 'right-curly-bracket',
        ][$char] ?? null;
    }
}
