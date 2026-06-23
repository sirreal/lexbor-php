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
}
