<?php

declare(strict_types=1);

namespace Lexbor\Unicode;

use InvalidArgumentException;
use Lexbor\Encoding\Utf8;

final class Unicode
{
    public const int IDNA_UNDEF = 0;
    public const int IDNA_DEVIATION = 1;
    public const int IDNA_DISALLOWED = 2;
    public const int IDNA_IGNORED = 3;
    public const int IDNA_MAPPED = 4;
    public const int IDNA_VALID = 5;

    public const string FORM_NFC = 'NFC';
    public const string FORM_NFD = 'NFD';
    public const string FORM_NFKC = 'NFKC';
    public const string FORM_NFKD = 'NFKD';

    private const int HANGUL_L_BASE = 0x1100;
    private const int HANGUL_V_BASE = 0x1161;
    private const int HANGUL_T_BASE = 0x11A7;
    private const int HANGUL_S_BASE = 0xAC00;
    private const int HANGUL_L_COUNT = 19;
    private const int HANGUL_V_COUNT = 21;
    private const int HANGUL_T_COUNT = 28;
    private const int HANGUL_N_COUNT = self::HANGUL_V_COUNT * self::HANGUL_T_COUNT;
    private const int HANGUL_S_COUNT = self::HANGUL_L_COUNT * self::HANGUL_N_COUNT;
    private const int ERROR_CODE_POINT = 0x1FFFFF;

    private function __construct()
    {
    }

    public static function idnaType(int $codePoint): int
    {
        if ($codePoint < 0 || $codePoint > 0x10FFFE) {
            return self::IDNA_DISALLOWED;
        }

        $ranges = UnicodeData::IDNA_TYPE_RANGES;
        $left = 0;
        $right = UnicodeData::IDNA_TYPE_RANGE_COUNT - 1;

        while ($left <= $right) {
            $middle = intdiv($left + $right, 2);
            [$start, $end, $type] = $ranges[$middle];

            if ($codePoint < $start) {
                $right = $middle - 1;
                continue;
            }

            if ($codePoint > $end) {
                $left = $middle + 1;
                continue;
            }

            return $type;
        }

        return self::IDNA_DISALLOWED;
    }

    public static function compose(int $first, int $second): ?int
    {
        return UnicodeData::CANONICAL_COMPOSITION_MAP[$first][$second] ?? null;
    }

    public static function normalize(string $data, string $form): string
    {
        return self::encodeForNormalization(self::normalizeCodePoints(self::decodeForNormalization($data), $form));
    }

    /**
     * @param list<int> $codePoints
     * @return list<int>
     */
    public static function normalizeCodePoints(array $codePoints, string $form): array
    {
        [$compatibility, $compose] = match ($form) {
            self::FORM_NFC => [false, true],
            self::FORM_NFD => [false, false],
            self::FORM_NFKC => [true, true],
            self::FORM_NFKD => [true, false],
            default => throw new InvalidArgumentException("Unknown Unicode normalization form {$form}."),
        };

        $buffer = [];

        foreach ($codePoints as $codePoint) {
            foreach (self::decomposeCodePoint($codePoint, $compatibility) as $decomposed) {
                self::appendOrdered($buffer, $decomposed);
            }
        }

        if ($compose) {
            self::composeBuffer($buffer);
        }

        $normalized = [];

        foreach ($buffer as [$codePoint]) {
            if ($codePoint !== null && $codePoint !== self::ERROR_CODE_POINT) {
                $normalized[] = $codePoint;
            }
        }

        return $normalized;
    }

    public static function combiningClass(int $codePoint): int
    {
        return UnicodeData::COMBINING_CLASS_MAP[$codePoint] ?? 0;
    }

    /**
     * @return list<int>
     */
    private static function decomposeCodePoint(int $codePoint, bool $compatibility): array
    {
        $decompositionMap = $compatibility
            ? UnicodeData::COMPATIBILITY_DECOMPOSITION_MAP
            : UnicodeData::CANONICAL_DECOMPOSITION_MAP;

        if (isset($decompositionMap[$codePoint])) {
            return $decompositionMap[$codePoint];
        }

        if ($codePoint >= self::HANGUL_S_BASE && $codePoint < self::HANGUL_S_BASE + self::HANGUL_S_COUNT) {
            $syllableIndex = $codePoint - self::HANGUL_S_BASE;
            $trailingIndex = $syllableIndex % self::HANGUL_T_COUNT;
            $leadVowelIndex = intdiv($syllableIndex - $trailingIndex, self::HANGUL_T_COUNT);

            $decomposition = [
                self::HANGUL_L_BASE + intdiv($leadVowelIndex, self::HANGUL_V_COUNT),
                self::HANGUL_V_BASE + ($leadVowelIndex % self::HANGUL_V_COUNT),
            ];

            if ($trailingIndex !== 0) {
                $decomposition[] = self::HANGUL_T_BASE + $trailingIndex;
            }

            return $decomposition;
        }

        return [$codePoint];
    }

    /**
     * @return list<int>
     */
    private static function decodeForNormalization(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($offset = 0; $offset < $length;) {
            $first = ord($data[$offset]);

            if ($first < 0x80) {
                $codePoints[] = $first;
                $offset++;
                continue;
            }

            if (($first & 0xE0) === 0xC0) {
                if ($length - $offset < 2) {
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    break;
                }

                $second = ord($data[$offset + 1]);

                $codePoints[] = (($first ^ (0xC0 & $first)) << 6)
                    | ($second ^ (0x80 & $second));
                $offset += 2;
                continue;
            }

            if (($first & 0xF0) === 0xE0) {
                if ($length - $offset < 3) {
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    break;
                }

                $second = ord($data[$offset + 1]);
                $third = ord($data[$offset + 2]);

                $codePoints[] = (($first ^ (0xE0 & $first)) << 12)
                    | (($second ^ (0x80 & $second)) << 6)
                    | ($third ^ (0x80 & $third));
                $offset += 3;
                continue;
            }

            if (($first & 0xF8) === 0xF0) {
                if ($length - $offset < 4) {
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    break;
                }

                $second = ord($data[$offset + 1]);
                $third = ord($data[$offset + 2]);
                $fourth = ord($data[$offset + 3]);

                $codePoint = (($first ^ (0xF0 & $first)) << 18)
                    | (($second ^ (0x80 & $second)) << 12)
                    | (($third ^ (0x80 & $third)) << 6)
                    | ($fourth ^ (0x80 & $fourth));

                $codePoints[] = $codePoint === self::ERROR_CODE_POINT
                    ? Utf8::REPLACEMENT_CODE_POINT
                    : $codePoint;
                $offset += 4;
                continue;
            }

            $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
            $offset++;
        }

        return $codePoints;
    }

    /**
     * @param list<int> $codePoints
     */
    private static function encodeForNormalization(array $codePoints): string
    {
        $data = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint >= 0 && $codePoint < 0x110000) {
                $data .= Utf8::encodeCodePoint($codePoint);
            }
        }

        return $data;
    }

    /**
     * @param list<array{?int, int}> $buffer
     */
    private static function appendOrdered(array &$buffer, int $codePoint): void
    {
        $combiningClass = self::combiningClass($codePoint);
        $buffer[] = [$codePoint, $combiningClass];

        if ($combiningClass === 0) {
            return;
        }

        for ($index = count($buffer) - 1; $index > 0; $index--) {
            if ($buffer[$index - 1][1] <= $combiningClass) {
                return;
            }

            $swap = $buffer[$index - 1];
            $buffer[$index - 1] = $buffer[$index];
            $buffer[$index] = $swap;
        }
    }

    /**
     * @param list<array{?int, int}> $buffer
     */
    private static function composeBuffer(array &$buffer): void
    {
        $starterIndex = null;
        $lastCombiningClass = 0;

        foreach ($buffer as $index => [$codePoint, $combiningClass]) {
            if ($codePoint === null) {
                continue;
            }

            if ($codePoint === self::ERROR_CODE_POINT) {
                $lastCombiningClass = 0;
                continue;
            }

            if (
                $starterIndex !== null
                && ($lastCombiningClass === 0 || $lastCombiningClass < $combiningClass)
            ) {
                $composed = self::composeForNormalization($buffer[$starterIndex][0], $codePoint);

                if ($composed !== null) {
                    $buffer[$starterIndex] = [$composed, self::combiningClass($composed)];
                    $buffer[$index] = [null, 0];
                    continue;
                }
            }

            if ($combiningClass === 0) {
                $starterIndex = $index;
            }

            $lastCombiningClass = $combiningClass;
        }
    }

    private static function composeForNormalization(int $first, int $second): ?int
    {
        $composed = UnicodeData::NORMALIZATION_COMPOSITION_MAP[$first][$second] ?? null;

        if ($composed !== null) {
            return $composed;
        }

        if (
            $first >= self::HANGUL_L_BASE
            && $first < self::HANGUL_L_BASE + self::HANGUL_L_COUNT
            && $second >= self::HANGUL_V_BASE
            && $second < self::HANGUL_V_BASE + self::HANGUL_V_COUNT
        ) {
            return self::HANGUL_S_BASE
                + (((($first - self::HANGUL_L_BASE) * self::HANGUL_V_COUNT)
                    + $second - self::HANGUL_V_BASE) * self::HANGUL_T_COUNT);
        }

        if (
            $first >= self::HANGUL_S_BASE
            && $first < self::HANGUL_S_BASE + self::HANGUL_S_COUNT
            && ($first - self::HANGUL_S_BASE) % self::HANGUL_T_COUNT === 0
            && $second > self::HANGUL_T_BASE
            && $second < self::HANGUL_T_BASE + self::HANGUL_T_COUNT
        ) {
            return $first + $second - self::HANGUL_T_BASE;
        }

        return null;
    }
}
