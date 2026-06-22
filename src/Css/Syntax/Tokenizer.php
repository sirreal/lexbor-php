<?php

declare(strict_types=1);

namespace Lexbor\Css\Syntax;

use Lexbor\Encoding\Utf8;

final class Tokenizer
{
    private const string REPLACEMENT_CHARACTER = "\u{FFFD}";

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

            if (self::startsIdentifier($css, $offset)) {
                [$value, $tokenLength] = self::consumeIdentifier($css, $offset);
                $tokens[] = new Token('ident', $value, $tokenLength);
                $offset += $tokenLength;
                continue;
            }

            $type = self::singleTokenType($char);
            if ($type !== null) {
                $tokens[] = new Token($type, $char, 1);
                $offset++;
                continue;
            }

            $tokens[] = new Token('delim', $char, 1);
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

        if ($cursor >= $length || (! self::isNameByte($css[$cursor]) && ! self::isValidEscape($css, $cursor))) {
            return null;
        }

        [$name, $cursor] = self::consumeName($css, $cursor);

        $tokenLength = $cursor - $offset;

        return ['#' . $name, $tokenLength];
    }

    /**
     * @return array{string, int}
     */
    private static function consumeIdentifier(string $css, int $offset): array
    {
        [$name, $cursor] = self::consumeName($css, $offset);

        return [$name, $cursor - $offset];
    }

    /**
     * @return array{string, int}
     */
    private static function consumeName(string $css, int $offset): array
    {
        $value = '';
        $length = strlen($css);

        while ($offset < $length) {
            $char = $css[$offset];

            if ($char === '\\') {
                if (! self::isValidEscape($css, $offset)) {
                    break;
                }

                [$escaped, $offset] = self::consumeEscapedCodePoint($css, $offset);
                $value .= $escaped;
                continue;
            }

            if (! self::isNameByte($char)) {
                break;
            }

            $value .= $char === "\0" ? self::REPLACEMENT_CHARACTER : $char;
            $offset++;
        }

        return [$value, $offset];
    }

    /**
     * @return array{string, int}
     */
    private static function consumeEscapedCodePoint(string $css, int $offset): array
    {
        $length = strlen($css);
        $cursor = $offset + 1;

        if ($cursor >= $length) {
            return [self::REPLACEMENT_CHARACTER, $cursor];
        }

        if (self::isHexDigit($css[$cursor])) {
            $hex = '';
            $digits = 0;

            while ($cursor < $length && $digits < 6 && self::isHexDigit($css[$cursor])) {
                $hex .= $css[$cursor];
                $cursor++;
                $digits++;
            }

            if ($cursor < $length && self::isWhitespace($css[$cursor])) {
                $cursor += self::whitespaceLength($css, $cursor);
            }

            return [self::encodeEscapedCodePoint((int) hexdec($hex)), $cursor];
        }

        if ($css[$cursor] === "\0") {
            return [self::REPLACEMENT_CHARACTER, $cursor + 1];
        }

        return [$css[$cursor], $cursor + 1];
    }

    private static function startsIdentifier(string $css, int $offset): bool
    {
        $char = $css[$offset];

        if ($char === '-') {
            $next = $css[$offset + 1] ?? '';

            return $next === '-' || self::isNameStartByte($next) || self::isValidEscape($css, $offset + 1);
        }

        return self::isNameStartByte($char) || self::isValidEscape($css, $offset);
    }

    private static function isValidEscape(string $css, int $offset): bool
    {
        if (($css[$offset] ?? '') !== '\\') {
            return false;
        }

        $next = $css[$offset + 1] ?? '';

        return $next === '' || ! self::isNewline($next);
    }

    private static function isNameStartByte(string $char): bool
    {
        return ($char >= 'A' && $char <= 'Z')
            || ($char >= 'a' && $char <= 'z')
            || $char === '_'
            || $char === "\0"
            || ($char !== '' && ord($char) >= 0x80);
    }

    private static function isNameByte(string $char): bool
    {
        return self::isNameStartByte($char)
            || ($char >= '0' && $char <= '9')
            || $char === '-';
    }

    private static function isHexDigit(string $char): bool
    {
        return ($char >= '0' && $char <= '9')
            || ($char >= 'A' && $char <= 'F')
            || ($char >= 'a' && $char <= 'f');
    }

    private static function encodeEscapedCodePoint(int $codePoint): string
    {
        if ($codePoint === 0 || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) || $codePoint > 0x10FFFF) {
            return self::REPLACEMENT_CHARACTER;
        }

        return Utf8::encodeCodePoint($codePoint);
    }

    private static function isWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === "\f";
    }

    private static function isNewline(string $char): bool
    {
        return $char === "\n" || $char === "\r" || $char === "\f";
    }

    private static function whitespaceLength(string $css, int $offset): int
    {
        return $css[$offset] === "\r" && ($css[$offset + 1] ?? '') === "\n" ? 2 : 1;
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
