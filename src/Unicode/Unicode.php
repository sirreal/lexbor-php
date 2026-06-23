<?php

declare(strict_types=1);

namespace Lexbor\Unicode;

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
        return (new UnicodeNormalizer($form))->normalize($data);
    }

    /**
     * @param list<int> $codePoints
     * @return list<int>
     */
    public static function normalizeCodePoints(array $codePoints, string $form): array
    {
        return (new UnicodeNormalizer($form))->normalizeCodePoints($codePoints);
    }

    public static function combiningClass(int $codePoint): int
    {
        return UnicodeData::COMBINING_CLASS_MAP[$codePoint] ?? 0;
    }
}
