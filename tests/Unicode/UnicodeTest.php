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

    public function testUpstreamCompositionFixture(): void
    {
        foreach (self::compositionFixtureRows() as [$first, $second, $composed]) {
            self::assertSame($composed, Unicode::compose($first, $second));
        }

        self::assertNull(Unicode::compose(0x0041, 0x0041));
        self::assertNull(Unicode::compose(-1, 0x0300));
        self::assertNull(Unicode::compose(0x0041, -1));
    }

    public function testGeneratedCanonicalCompositionMapMatchesUpstreamResource(): void
    {
        $expected = self::expectedCanonicalCompositionMap();

        self::assertSame($expected, UnicodeData::CANONICAL_COMPOSITION_MAP);
        self::assertSame(self::compositionPairCount($expected), UnicodeData::CANONICAL_COMPOSITION_PAIR_COUNT);
    }

    public function testUpstreamNormalizationCodePointFixture(): void
    {
        $forms = [
            Unicode::FORM_NFC => 'nfc',
            Unicode::FORM_NFD => 'nfd',
            Unicode::FORM_NFKC => 'nfkc',
            Unicode::FORM_NFKD => 'nfkd',
        ];

        foreach (self::normalizationFixtureRows() as $index => $row) {
            foreach ($forms as $form => $key) {
                self::assertSame(
                    $row[$key],
                    Unicode::normalizeCodePoints($row['source'], $form),
                    "Normalization fixture #{$index} {$form}",
                );
            }
        }
    }

    public function testGeneratedNormalizationTablesMatchUpstreamResource(): void
    {
        [
            'combiningClass' => $combiningClass,
            'canonicalDecomposition' => $canonicalDecomposition,
            'compatibilityDecomposition' => $compatibilityDecomposition,
            'normalizationComposition' => $normalizationComposition,
        ] = self::expectedNormalizationTables();

        self::assertSame($combiningClass, UnicodeData::COMBINING_CLASS_MAP);
        self::assertSame(count($combiningClass), UnicodeData::COMBINING_CLASS_CODE_POINT_COUNT);

        self::assertSame($canonicalDecomposition, UnicodeData::CANONICAL_DECOMPOSITION_MAP);
        self::assertSame(count($canonicalDecomposition), UnicodeData::CANONICAL_DECOMPOSITION_COUNT);

        self::assertSame($compatibilityDecomposition, UnicodeData::COMPATIBILITY_DECOMPOSITION_MAP);
        self::assertSame(count($compatibilityDecomposition), UnicodeData::COMPATIBILITY_DECOMPOSITION_COUNT);

        self::assertSame($normalizationComposition, UnicodeData::NORMALIZATION_COMPOSITION_MAP);
        self::assertSame(self::compositionPairCount($normalizationComposition), UnicodeData::NORMALIZATION_COMPOSITION_PAIR_COUNT);
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

    /**
     * @return array<int, array<int, int>>
     */
    private static function expectedCanonicalCompositionMap(): array
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
            '/\{\s*([^,{}]+),\s*([^,{}]+),\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_normalization_entries'),
            $normalizationMatches,
            PREG_SET_ORDER,
        );

        $normalizationEntries = [];
        foreach ($normalizationMatches as $match) {
            $normalizationEntries[] = (int) $match[6];
        }

        preg_match_all(
            '/\{\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_composition_entries'),
            $compositionEntryMatches,
            PREG_SET_ORDER,
        );

        $compositionEntries = [];
        foreach ($compositionEntryMatches as $match) {
            $compositionEntries[] = [(int) $match[1], (int) $match[2], (int) $match[3]];
        }

        preg_match_all(
            '/\{\s*(0x[0-9A-Fa-f]+|\d+)\s*,\s*(?:true|false)\s*\}/',
            self::captureArray($source, 'lxb_unicode_composition_cps'),
            $compositionCpMatches,
            PREG_SET_ORDER,
        );

        $compositionCodePoints = [];
        foreach ($compositionCpMatches as $match) {
            $compositionCodePoints[] = self::parseNumber($match[1]);
        }

        $map = [];

        foreach (self::unicodeTableMaps($source) as [$start, , $indexes]) {
            foreach ($indexes as $offset => $entryIndex) {
                $codePoint = $start + $offset;
                [$normalizationIndex] = $unicodeEntries[$entryIndex] ?? [0, 0];
                $compositionIndex = $normalizationEntries[$normalizationIndex] ?? 0;
                [$length, $index, $secondStart] = $compositionEntries[$compositionIndex] ?? [0, 0, 0];

                for ($i = 0; $i < $length; $i++) {
                    $composed = $compositionCodePoints[$index + $i] ?? 0;
                    if ($composed === 0) {
                        continue;
                    }

                    $map[$codePoint][$secondStart + $i] = $composed;
                }
            }
        }

        ksort($map);
        foreach ($map as &$seconds) {
            ksort($seconds);
        }
        unset($seconds);

        $cached = $map;

        return $cached;
    }

    /**
     * @return array{
     *     combiningClass: array<int, int>,
     *     canonicalDecomposition: array<int, list<int>>,
     *     compatibilityDecomposition: array<int, list<int>>,
     *     normalizationComposition: array<int, array<int, int>>
     * }
     */
    private static function expectedNormalizationTables(): array
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
            '/\{\s*([^,{}]+),\s*([^,{}]+),\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_normalization_entries'),
            $normalizationMatches,
            PREG_SET_ORDER,
        );

        $normalizationEntries = [];
        foreach ($normalizationMatches as $match) {
            $type = self::parseDecompositionType($match[1]);

            $normalizationEntries[] = [
                'typeMask' => $type & ~(1 << 7),
                'canonicalSeparately' => ($type & (1 << 7)) !== 0,
                'ccc' => (int) $match[3],
                'length' => (int) $match[4],
                'decomposition' => (int) $match[5],
                'composition' => (int) $match[6],
            ];
        }

        preg_match_all(
            '/0x[0-9A-Fa-f]+|\d+/',
            self::captureArray($source, 'lxb_unicode_decomposition_cps'),
            $decompositionCpMatches,
        );

        $decompositionCodePoints = [];
        foreach ($decompositionCpMatches[0] as $number) {
            $decompositionCodePoints[] = self::parseNumber($number);
        }

        preg_match_all(
            '/\{\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_composition_entries'),
            $compositionEntryMatches,
            PREG_SET_ORDER,
        );

        $compositionEntries = [];
        foreach ($compositionEntryMatches as $match) {
            $compositionEntries[] = [(int) $match[1], (int) $match[2], (int) $match[3]];
        }

        preg_match_all(
            '/\{\s*(0x[0-9A-Fa-f]+|\d+)\s*,\s*(true|false)\s*\}/',
            self::captureArray($source, 'lxb_unicode_composition_cps'),
            $compositionCpMatches,
            PREG_SET_ORDER,
        );

        $compositionCodePoints = [];
        foreach ($compositionCpMatches as $match) {
            $compositionCodePoints[] = [self::parseNumber($match[1]), $match[2] === 'true'];
        }

        $combiningClass = [];
        $canonicalDecomposition = [];
        $compatibilityDecomposition = [];
        $normalizationComposition = [];

        foreach (self::unicodeTableMaps($source) as [$start, , $indexes]) {
            foreach ($indexes as $offset => $entryIndex) {
                $codePoint = $start + $offset;
                [$normalizationIndex] = $unicodeEntries[$entryIndex] ?? [0, 0];
                $normalization = $normalizationEntries[$normalizationIndex] ?? null;

                if ($normalization === null) {
                    continue;
                }

                if ($normalization['ccc'] !== 0) {
                    $combiningClass[$codePoint] = $normalization['ccc'];
                }

                if ($normalization['length'] > 0) {
                    $compatibility = array_slice(
                        $decompositionCodePoints,
                        $normalization['decomposition'],
                        $normalization['length'],
                    );

                    $compatibilityDecomposition[$codePoint] = $compatibility;

                    if ($normalization['typeMask'] === 0) {
                        $canonical = $compatibility;

                        if ($normalization['canonicalSeparately']) {
                            $canonicalLengthOffset = $normalization['decomposition'] + $normalization['length'];
                            $canonicalLength = $decompositionCodePoints[$canonicalLengthOffset] ?? 0;
                            $canonical = array_slice(
                                $decompositionCodePoints,
                                $canonicalLengthOffset + 1,
                                $canonicalLength,
                            );
                        }

                        $canonicalDecomposition[$codePoint] = $canonical;
                    }
                }

                $compositionIndex = $normalization['composition'];
                [$length, $index, $secondStart] = $compositionEntries[$compositionIndex] ?? [0, 0, 0];

                for ($i = 0; $i < $length; $i++) {
                    [$composed, $excluded] = $compositionCodePoints[$index + $i] ?? [0, false];
                    if ($composed === 0 || $excluded) {
                        continue;
                    }

                    $normalizationComposition[$codePoint][$secondStart + $i] = $composed;
                }
            }
        }

        ksort($combiningClass);
        ksort($canonicalDecomposition);
        ksort($compatibilityDecomposition);
        ksort($normalizationComposition);

        foreach ($normalizationComposition as &$seconds) {
            ksort($seconds);
        }
        unset($seconds);

        $cached = [
            'combiningClass' => $combiningClass,
            'canonicalDecomposition' => $canonicalDecomposition,
            'compatibilityDecomposition' => $compatibilityDecomposition,
            'normalizationComposition' => $normalizationComposition,
        ];

        return $cached;
    }

    /**
     * @return list<array{int, int, int}>
     */
    private static function compositionFixtureRows(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $sourcePath = dirname(__DIR__, 2) . '/upstream/lexbor/test/lexbor/unicode/composition_test.c';
        $source = file_get_contents($sourcePath);

        if ($source === false) {
            throw new RuntimeException("Unable to read upstream Unicode composition test: {$sourcePath}");
        }

        preg_match_all('/\{\s*(0x[0-9A-Fa-f]+),\s*(0x[0-9A-Fa-f]+),\s*(0x[0-9A-Fa-f]+)\s*\}/', $source, $matches, PREG_SET_ORDER);

        $rows = [];
        foreach ($matches as $match) {
            $rows[] = [
                self::parseNumber($match[1]),
                self::parseNumber($match[2]),
                self::parseNumber($match[3]),
            ];
        }

        return $rows;
    }

    /**
     * @return iterable<int, array{source: list<int>, nfc: list<int>, nfd: list<int>, nfkc: list<int>, nfkd: list<int>}>
     */
    private static function normalizationFixtureRows(): iterable
    {
        $sourcePath = dirname(__DIR__, 2) . '/upstream/lexbor/test/lexbor/unicode/unicode_normalization_test_res.h';
        if (!is_file($sourcePath)) {
            throw new RuntimeException("Unable to read upstream Unicode normalization fixture: {$sourcePath}");
        }

        $byIndex = [];

        foreach (new \SplFileObject($sourcePath) as $line) {
            if (
                !is_string($line)
                || preg_match(
                    '/^static const lxb_codepoint_t lxb_unicode_test_(source|nfc|nfd|nfkc|nfkd)_(\d+)\[\d+\] = \{([^}]*)\};$/',
                    rtrim($line),
                    $match,
                ) !== 1
            ) {
                continue;
            }

            $byIndex[(int) $match[2]][$match[1]] = self::parseCodePointList($match[3], stripSentinel: true);

            if (count($byIndex[(int) $match[2]]) !== 5) {
                continue;
            }

            $index = (int) $match[2];
            $row = $byIndex[$index];

            foreach (['source', 'nfc', 'nfd', 'nfkc', 'nfkd'] as $key) {
                if (!isset($row[$key])) {
                    throw new RuntimeException("Missing {$key} normalization fixture for row {$index}.");
                }
            }

            unset($byIndex[$index]);

            yield $index => $row;
        }

        if ($byIndex !== []) {
            throw new RuntimeException('Unterminated Unicode normalization fixture row.');
        }
    }

    /**
     * @param array<int, array<int, int>> $map
     */
    private static function compositionPairCount(array $map): int
    {
        $count = 0;

        foreach ($map as $seconds) {
            $count += count($seconds);
        }

        return $count;
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

    private static function parseNumber(string $number): int
    {
        if (str_starts_with($number, '0x') || str_starts_with($number, '0X')) {
            return hexdec(substr($number, 2));
        }

        return (int) $number;
    }

    private static function parseDecompositionType(string $expression): int
    {
        $typeByName = [
            '0' => 0,
            'LXB_UNICODE_DECOMPOSITION_TYPE__UNDEF' => 0x00,
            'LXB_UNICODE_DECOMPOSITION_TYPE_CIRCLE' => 0x01,
            'LXB_UNICODE_DECOMPOSITION_TYPE_COMPAT' => 0x02,
            'LXB_UNICODE_DECOMPOSITION_TYPE_FINAL' => 0x03,
            'LXB_UNICODE_DECOMPOSITION_TYPE_FONT' => 0x04,
            'LXB_UNICODE_DECOMPOSITION_TYPE_FRACTION' => 0x05,
            'LXB_UNICODE_DECOMPOSITION_TYPE_INITIAL' => 0x06,
            'LXB_UNICODE_DECOMPOSITION_TYPE_ISOLATED' => 0x07,
            'LXB_UNICODE_DECOMPOSITION_TYPE_MEDIAL' => 0x08,
            'LXB_UNICODE_DECOMPOSITION_TYPE_NARROW' => 0x09,
            'LXB_UNICODE_DECOMPOSITION_TYPE_NOBREAK' => 0x0A,
            'LXB_UNICODE_DECOMPOSITION_TYPE_SMALL' => 0x0B,
            'LXB_UNICODE_DECOMPOSITION_TYPE_SQUARE' => 0x0C,
            'LXB_UNICODE_DECOMPOSITION_TYPE_SUB' => 0x0D,
            'LXB_UNICODE_DECOMPOSITION_TYPE_SUPER' => 0x0E,
            'LXB_UNICODE_DECOMPOSITION_TYPE_VERTICAL' => 0x0F,
            'LXB_UNICODE_DECOMPOSITION_TYPE_WIDE' => 0x10,
        ];

        $type = 0;

        foreach (explode('|', $expression) as $part) {
            $part = trim($part);

            if ($part === 'LXB_UNICODE_CANONICAL_SEPARATELY') {
                $type |= 1 << 7;
                continue;
            }

            if (!array_key_exists($part, $typeByName)) {
                throw new RuntimeException("Unknown decomposition type {$part}.");
            }

            $type |= $typeByName[$part];
        }

        return $type;
    }

    /**
     * @return list<int>
     */
    private static function parseCodePointList(string $source, bool $stripSentinel = false): array
    {
        preg_match_all('/0x[0-9A-Fa-f]+|\d+/', $source, $matches);

        $codePoints = [];
        foreach ($matches[0] as $number) {
            $codePoints[] = self::parseNumber($number);
        }

        if ($stripSentinel && end($codePoints) === 0x10FFFF) {
            array_pop($codePoints);
        }

        return $codePoints;
    }
}
