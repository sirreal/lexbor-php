<?php

declare(strict_types=1);

namespace Lexbor\Tests\Unicode;

use Lexbor\Unicode\Unicode;
use Lexbor\Unicode\UnicodeData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UnicodeTest extends TestCase
{
    public function testUpstreamIdnaTypeEdge(): void
    {
        self::assertSame(Unicode::IDNA_DISALLOWED, Unicode::idnaType(0xE00FF));

        $cases = [
            0x0041 => Unicode::IDNA_MAPPED,
            0x0061 => Unicode::IDNA_VALID,
            0x00AD => Unicode::IDNA_IGNORED,
            0x00DF => Unicode::IDNA_DEVIATION,
            0x200C => Unicode::IDNA_DEVIATION,
            0xE0100 => Unicode::IDNA_IGNORED,
        ];

        foreach ($cases as $codePoint => $type) {
            self::assertSame($type, Unicode::idnaType($codePoint));
        }
    }

    public function testGeneratedIdnaTypeRangesMatchUpstreamResource(): void
    {
        [$expectedRanges, $expectedCount] = self::expectedIdnaTypeRanges();

        self::assertSame($expectedRanges, UnicodeData::IDNA_TYPE_RANGES);
        self::assertSame(count($expectedRanges), UnicodeData::IDNA_TYPE_RANGE_COUNT);
        self::assertSame($expectedCount, UnicodeData::IDNA_NON_DISALLOWED_CODE_POINT_COUNT);
    }

    public function testIdnaTypeMatchesGeneratedRangeBoundaries(): void
    {
        foreach (UnicodeData::IDNA_TYPE_RANGES as [$start, $end, $type]) {
            self::assertSame($type, Unicode::idnaType($start));
            self::assertSame($type, Unicode::idnaType($end));
            self::assertSame($type, Unicode::idnaType(intdiv($start + $end, 2)));
        }

        foreach ([-1, 0x80, 0xE00FF, 0x10FFFF, 0x110000] as $codePoint) {
            self::assertSame(Unicode::IDNA_DISALLOWED, Unicode::idnaType($codePoint));
        }
    }

    /**
     * @return array{list<array{int, int, int}>, int}
     */
    private static function expectedIdnaTypeRanges(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $sourcePath = dirname(__DIR__, 2) . '/upstream/lexbor/source/lexbor/unicode/res.h';
        $source = file_get_contents($sourcePath);

        if ($source === false) {
            throw new RuntimeException("Unable to read upstream Unicode resource: {$sourcePath}");
        }

        $typeByName = [
            '0' => Unicode::IDNA_UNDEF,
            'LXB_UNICODE_IDNA__UNDEF' => Unicode::IDNA_UNDEF,
            'LXB_UNICODE_IDNA_DEVIATION' => Unicode::IDNA_DEVIATION,
            'LXB_UNICODE_IDNA_DISALLOWED' => Unicode::IDNA_DISALLOWED,
            'LXB_UNICODE_IDNA_IGNORED' => Unicode::IDNA_IGNORED,
            'LXB_UNICODE_IDNA_MAPPED' => Unicode::IDNA_MAPPED,
            'LXB_UNICODE_IDNA_VALID' => Unicode::IDNA_VALID,
        ];

        preg_match_all(
            '/\{\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_entries'),
            $entryMatches,
            PREG_SET_ORDER,
        );

        $unicodeEntries = [];
        foreach ($entryMatches as $match) {
            $unicodeEntries[] = [(int) $match[1], (int) $match[2]];
        }

        preg_match_all(
            '/\{\s*(LXB_UNICODE_IDNA_[A-Z_]+|0)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_idna_entries'),
            $idnaMatches,
            PREG_SET_ORDER,
        );

        $idnaEntries = [];
        foreach ($idnaMatches as $match) {
            $name = $match[1];

            if (!array_key_exists($name, $typeByName)) {
                throw new RuntimeException("Unknown IDNA type {$name}.");
            }

            $idnaEntries[] = $typeByName[$name];
        }

        $ranges = [];
        $current = null;
        $nonDisallowedCount = 0;

        foreach (self::unicodeTableMaps($source) as [$start, $endExclusive, $indexes]) {
            foreach ($indexes as $offset => $entryIndex) {
                $codePoint = $start + $offset;
                $idnaIndex = $unicodeEntries[$entryIndex][1] ?? 0;
                $type = $idnaIndex === 0 ? Unicode::IDNA_DISALLOWED : ($idnaEntries[$idnaIndex] ?? Unicode::IDNA_DISALLOWED);

                if ($type === Unicode::IDNA_DISALLOWED) {
                    if ($current !== null) {
                        $ranges[] = $current;
                        $current = null;
                    }

                    continue;
                }

                $nonDisallowedCount++;

                if ($current !== null && $current[1] === $codePoint - 1 && $current[2] === $type) {
                    $current[1] = $codePoint;
                    continue;
                }

                if ($current !== null) {
                    $ranges[] = $current;
                }

                $current = [$codePoint, $codePoint, $type];
            }
        }

        if ($current !== null) {
            $ranges[] = $current;
        }

        $cached = [$ranges, $nonDisallowedCount];

        return $cached;
    }

    private static function captureArray(string $source, string $name): string
    {
        if (
            preg_match(
                '/static const [^{]+ ' . preg_quote($name, '/') . '\[\d+\]\s*=\s*\{(.*?)\};/s',
                $source,
                $match,
            ) !== 1
        ) {
            throw new RuntimeException("Unable to locate {$name}.");
        }

        return $match[1];
    }

    /**
     * @return iterable<array{int, int, list<int>}>
     */
    private static function unicodeTableMaps(string $source): iterable
    {
        $inMap = false;
        $start = 0;
        $endExclusive = 0;
        $indexes = [];

        foreach (explode("\n", $source) as $line) {
            if (!$inMap) {
                if (
                    preg_match(
                        '/^static const uint(?:16|32)_t lxb_unicode_table_map_(\d+)_(\d+)\[(\d+)\] =$/',
                        $line,
                        $match,
                    ) === 1
                ) {
                    $inMap = true;
                    $start = (int) $match[1];
                    $endExclusive = (int) $match[2];
                    $indexes = [];
                }

                continue;
            }

            if (str_starts_with($line, '};')) {
                if (count($indexes) !== $endExclusive - $start) {
                    throw new RuntimeException(
                        "Unexpected length for lxb_unicode_table_map_{$start}_{$endExclusive}."
                    );
                }

                yield [$start, $endExclusive, $indexes];

                $inMap = false;
                continue;
            }

            if (preg_match('/^\s*(\d+),/', $line, $match) === 1) {
                $indexes[] = (int) $match[1];
            }
        }

        if ($inMap) {
            throw new RuntimeException('Unterminated Unicode table map.');
        }
    }
}
