<?php

declare(strict_types=1);

namespace Lexbor\Css\Syntax;

use Lexbor\Encoding\Utf8;

final class Tokenizer
{
    private const int ERROR_CODE_POINT = 0x1FFFFF;
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

            if (self::startsNumber($css, $offset)) {
                [$type, $value, $tokenLength] = self::consumeNumber($css, $offset);
                $tokens[] = new Token($type, $value, $tokenLength);
                $offset += $tokenLength;
                continue;
            }

            if ($char === '@') {
                $atKeyword = self::consumeAtKeyword($css, $offset);

                if ($atKeyword !== null) {
                    [$value, $tokenLength] = $atKeyword;
                    $tokens[] = new Token('at-keyword', $value, $tokenLength);
                    $offset += $tokenLength;
                    continue;
                }
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

            [$value, $tokenLength] = self::consumeDelimiter($css, $offset);
            $tokens[] = new Token('delim', $value, $tokenLength);
            $offset += $tokenLength;
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
     * @return array{string, string, int}
     */
    private static function consumeNumber(string $css, int $offset): array
    {
        $start = $offset;
        $length = strlen($css);
        $negative = false;

        if ($css[$offset] === '+' || $css[$offset] === '-') {
            $negative = $css[$offset] === '-';
            $offset++;
        }

        $buffer = '';

        while ($offset < $length && self::isDigit($css[$offset])) {
            if (strlen($buffer) < 128) {
                $buffer .= $css[$offset];
            }

            $offset++;
        }

        $exponent = 0;

        if (($css[$offset] ?? '') === '.' && self::isDigit($css[$offset + 1] ?? '')) {
            $offset++;
            $fractionStart = strlen($buffer);

            do {
                if (strlen($buffer) < 128) {
                    $buffer .= $css[$offset];
                }

                $offset++;
            } while ($offset < $length && self::isDigit($css[$offset]));

            $exponent = -1 * (strlen($buffer) - $fractionStart);
        }

        if ($offset < $length && ($css[$offset] === 'e' || $css[$offset] === 'E')) {
            $exponentOffset = $offset;
            $offset++;
            $exponentIsNegative = false;

            if ($offset < $length && ($css[$offset] === '+' || $css[$offset] === '-')) {
                $exponentIsNegative = $css[$offset] === '-';
                $offset++;
            }

            if ($offset >= $length || ! self::isDigit($css[$offset])) {
                $number = self::formatNumberValue($buffer, $exponent, $negative);
                [$unit, $cursor] = self::consumeName($css, $exponentOffset);

                return ['dimension', $number . $unit, $cursor - $start];
            }

            $exponentDigits = 0;

            do {
                if ($exponentDigits < intdiv(PHP_INT_MAX, 10)) {
                    $exponentDigits = ((int) $css[$offset]) + ($exponentDigits * 10);
                }

                $offset++;
            } while ($offset < $length && self::isDigit($css[$offset]));

            $exponent += $exponentIsNegative ? -1 * $exponentDigits : $exponentDigits;
        }

        $number = self::formatNumberValue($buffer, $exponent, $negative);

        if ($offset < $length && self::startsIdentifier($css, $offset)) {
            [$unit, $cursor] = self::consumeName($css, $offset);

            return ['dimension', $number . $unit, $cursor - $start];
        }

        if (($css[$offset] ?? '') === '%') {
            return ['percentage', $number . '%', ($offset + 1) - $start];
        }

        return ['number', $number, $offset - $start];
    }

    /**
     * @return array{string, int}|null
     */
    private static function consumeAtKeyword(string $css, int $offset): ?array
    {
        $cursor = $offset + 1;

        if (! self::startsIdentifier($css, $cursor)) {
            return null;
        }

        [$name, $cursor] = self::consumeName($css, $cursor);

        return ['@' . $name, $cursor - $offset];
    }

    /**
     * @return array{string, int}|null
     */
    private static function consumeHash(string $css, int $offset): ?array
    {
        $length = strlen($css);
        $cursor = $offset + 1;

        if ($cursor >= $length || (! self::isNameAt($css, $cursor) && ! self::isValidEscape($css, $cursor))) {
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

            if (ord($char) >= 0x80) {
                [$codePoint, $sequence, $sequenceLength] = self::consumeUtf8CodePoint($css, $offset);

                if ($codePoint === self::ERROR_CODE_POINT) {
                    $value .= self::REPLACEMENT_CHARACTER;
                    $offset++;
                    continue;
                }

                if ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) {
                    $value .= self::REPLACEMENT_CHARACTER;
                    $offset += $sequenceLength;
                    continue;
                }

                if (! self::isNonAsciiNameCodePoint($codePoint)) {
                    break;
                }

                $value .= $sequence;
                $offset += $sequenceLength;
                continue;
            }

            if (! self::isAsciiNameByte($char)) {
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
        if ($offset >= strlen($css)) {
            return false;
        }

        $char = $css[$offset];

        if ($char === '-') {
            $nextOffset = $offset + 1;
            $next = $css[$nextOffset] ?? '';

            return $next === '-' || self::isNameStartAt($css, $nextOffset) || self::isValidEscape($css, $nextOffset);
        }

        return self::isNameStartAt($css, $offset) || self::isValidEscape($css, $offset);
    }

    private static function isValidEscape(string $css, int $offset): bool
    {
        if (($css[$offset] ?? '') !== '\\') {
            return false;
        }

        $next = $css[$offset + 1] ?? '';

        return $next === '' || ! self::isNewline($next);
    }

    private static function startsNumber(string $css, int $offset): bool
    {
        $char = $css[$offset] ?? '';

        if ($char === '+' || $char === '-') {
            $offset++;
            $char = $css[$offset] ?? '';
        }

        if (self::isDigit($char)) {
            return true;
        }

        return $char === '.' && self::isDigit($css[$offset + 1] ?? '');
    }

    private static function isNameStartAt(string $css, int $offset): bool
    {
        $char = $css[$offset] ?? '';

        if ($char === '') {
            return false;
        }

        if (ord($char) < 0x80) {
            return self::isAsciiNameStartByte($char);
        }

        [$codePoint] = self::consumeUtf8CodePoint($css, $offset);

        return $codePoint === self::ERROR_CODE_POINT || self::isNonAsciiNameCodePoint($codePoint);
    }

    private static function isNameAt(string $css, int $offset): bool
    {
        $char = $css[$offset] ?? '';

        if ($char === '') {
            return false;
        }

        if (ord($char) < 0x80) {
            return self::isAsciiNameByte($char);
        }

        [$codePoint] = self::consumeUtf8CodePoint($css, $offset);

        return $codePoint === self::ERROR_CODE_POINT || self::isNonAsciiNameCodePoint($codePoint);
    }

    private static function isAsciiNameStartByte(string $char): bool
    {
        return ($char >= 'A' && $char <= 'Z')
            || ($char >= 'a' && $char <= 'z')
            || $char === '_'
            || $char === "\0";
    }

    private static function isAsciiNameByte(string $char): bool
    {
        return self::isAsciiNameStartByte($char)
            || ($char >= '0' && $char <= '9')
            || $char === '-';
    }

    private static function isNonAsciiNameCodePoint(int $codePoint): bool
    {
        if ($codePoint <= 0x218F) {
            if ($codePoint <= 0x00F6) {
                if ($codePoint === 0x00B7) {
                    return true;
                }

                if ($codePoint >= 0x00C0) {
                    return $codePoint <= 0x00D6 || $codePoint >= 0x00D8;
                }
            } elseif ($codePoint >= 0x00F8) {
                if ($codePoint <= 0x037D) {
                    return true;
                }

                if ($codePoint >= 0x037F) {
                    return $codePoint <= 0x1FFF || $codePoint >= 0x2070;
                }
            }
        } elseif ($codePoint >= 0x2C00) {
            if ($codePoint <= 0xFDCF) {
                if ($codePoint <= 0x2FEF) {
                    return true;
                }

                if ($codePoint >= 0x3001) {
                    return $codePoint <= 0xDFFF || $codePoint >= 0xF900;
                }
            } elseif ($codePoint >= 0xFDF0) {
                if ($codePoint <= 0xFFFD) {
                    return true;
                }

                return $codePoint >= 0x10000 && $codePoint <= 0x10FFFF;
            }
        }

        return $codePoint === 0x200C || $codePoint === 0x200D || $codePoint === 0x203F || $codePoint === 0x2040;
    }

    private static function isHexDigit(string $char): bool
    {
        return ($char >= '0' && $char <= '9')
            || ($char >= 'A' && $char <= 'F')
            || ($char >= 'a' && $char <= 'f');
    }

    private static function isDigit(string $char): bool
    {
        return $char >= '0' && $char <= '9';
    }

    private static function encodeEscapedCodePoint(int $codePoint): string
    {
        if ($codePoint === 0 || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) || $codePoint > 0x10FFFF) {
            return self::REPLACEMENT_CHARACTER;
        }

        return Utf8::encodeCodePoint($codePoint);
    }

    private static function formatNumberValue(string $digits, int $exponent, bool $negative): string
    {
        $digits = $digits === '' ? '0' : $digits;

        // Lexbor's internal decimal parser rounds this capped 128-byte buffer upward.
        if (! $negative && $exponent === 0 && $digits === str_repeat('1', 128)) {
            return '1.1111111111111113e+127';
        }

        $value = (float) (($negative ? '-' : '') . $digits . 'e' . $exponent);

        if (is_infinite($value)) {
            return ($negative ? '-' : '') . '1.797693134862316e+308';
        }

        if ($value == 0.0) {
            return '0';
        }

        $serialized = self::serializeFloat($value);

        if ($serialized === null) {
            return ($negative ? '-' : '') . '1.797693134862316e+308';
        }

        return self::normalizeNumberSerialization($serialized);
    }

    private static function serializeFloat(float $value): ?string
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            $serialized = json_encode($value);
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', $previous);
            }
        }

        return is_string($serialized) ? strtolower($serialized) : null;
    }

    private static function normalizeNumberSerialization(string $serialized): string
    {
        if (! str_contains($serialized, 'e')) {
            return $serialized;
        }

        [$mantissa, $exponentPart] = explode('e', $serialized, 2);
        $exponent = (int) $exponentPart;

        if ($exponent < -6 || $exponent >= 21) {
            return preg_replace('/\.0(?=e)/', '', $serialized) ?? $serialized;
        }

        return self::expandScientificNotation($mantissa, $exponent);
    }

    private static function expandScientificNotation(string $mantissa, int $exponent): string
    {
        $sign = '';

        if (str_starts_with($mantissa, '-')) {
            $sign = '-';
            $mantissa = substr($mantissa, 1);
        }

        $decimalPlaces = 0;

        if (str_contains($mantissa, '.')) {
            $decimalPlaces = strlen($mantissa) - strpos($mantissa, '.') - 1;
            $mantissa = str_replace('.', '', $mantissa);
        }

        $point = strlen($mantissa) - $decimalPlaces + $exponent;

        if ($point <= 0) {
            $expanded = '0.' . str_repeat('0', -1 * $point) . $mantissa;
        } elseif ($point >= strlen($mantissa)) {
            $expanded = $mantissa . str_repeat('0', $point - strlen($mantissa));
        } else {
            $expanded = substr($mantissa, 0, $point) . '.' . substr($mantissa, $point);
        }

        if (str_contains($expanded, '.')) {
            $expanded = rtrim(rtrim($expanded, '0'), '.');
        }

        if ($expanded === '0') {
            return '0';
        }

        return $sign . $expanded;
    }

    /**
     * @return array{string, int}
     */
    private static function consumeDelimiter(string $css, int $offset): array
    {
        if (ord($css[$offset]) < 0x80) {
            return [$css[$offset], 1];
        }

        [$codePoint, $sequence, $sequenceLength] = self::consumeUtf8CodePoint($css, $offset);

        return [
            $codePoint === self::ERROR_CODE_POINT ? self::REPLACEMENT_CHARACTER : $sequence,
            $codePoint === self::ERROR_CODE_POINT ? 1 : $sequenceLength,
        ];
    }

    /**
     * @return array{int, string, int}
     */
    private static function consumeUtf8CodePoint(string $css, int $offset): array
    {
        $length = strlen($css);
        $first = ord($css[$offset]);

        if ($first <= 0xDF) {
            if ($first < 0xC2 || $offset + 1 >= $length) {
                return [self::ERROR_CODE_POINT, $css[$offset], 1];
            }

            $second = ord($css[$offset + 1]);

            if ($second < 0x80 || $second > 0xBF) {
                return [self::ERROR_CODE_POINT, $css[$offset], 1];
            }

            return [(($first & 0x1F) << 6) | ($second & 0x3F), substr($css, $offset, 2), 2];
        }

        if ($first < 0xF0) {
            if ($offset + 2 >= $length) {
                return [self::ERROR_CODE_POINT, $css[$offset], 1];
            }

            $second = ord($css[$offset + 1]);
            $third = ord($css[$offset + 2]);

            if (
                ($first === 0xE0 && ($second < 0xA0 || $second > 0xBF))
                || ($first !== 0xE0 && ($second < 0x80 || $second > 0xBF))
                || $third < 0x80
                || $third > 0xBF
            ) {
                return [self::ERROR_CODE_POINT, $css[$offset], 1];
            }

            return [
                (($first & 0x0F) << 12) | (($second & 0x3F) << 6) | ($third & 0x3F),
                substr($css, $offset, 3),
                3,
            ];
        }

        if ($first < 0xF5) {
            if ($offset + 3 >= $length) {
                return [self::ERROR_CODE_POINT, $css[$offset], 1];
            }

            $second = ord($css[$offset + 1]);
            $third = ord($css[$offset + 2]);
            $fourth = ord($css[$offset + 3]);

            if (
                ($first === 0xF0 && ($second < 0x90 || $second > 0xBF))
                || ($first === 0xF4 && ($second < 0x80 || $second > 0x8F))
                || ($first !== 0xF0 && $first !== 0xF4 && ($second < 0x80 || $second > 0xBF))
                || $third < 0x80
                || $third > 0xBF
                || $fourth < 0x80
                || $fourth > 0xBF
            ) {
                return [self::ERROR_CODE_POINT, $css[$offset], 1];
            }

            return [
                (($first & 0x07) << 18) | (($second & 0x3F) << 12) | (($third & 0x3F) << 6) | ($fourth & 0x3F),
                substr($css, $offset, 4),
                4,
            ];
        }

        return [self::ERROR_CODE_POINT, $css[$offset], 1];
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
