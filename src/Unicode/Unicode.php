<?php

declare(strict_types=1);

namespace Lexbor\Unicode;

use InvalidArgumentException;

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
            if ($codePoint !== null) {
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
