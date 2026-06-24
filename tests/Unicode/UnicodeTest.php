<?php

declare(strict_types=1);

namespace Lexbor\Tests\Unicode;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Utf8;
use Lexbor\Unicode\Unicode;
use Lexbor\Unicode\UnicodeData;
use Lexbor\Unicode\UnicodeNormalizer;
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

    public function testGeneratedIdnaMappingsMatchUpstreamResource(): void
    {
        $expected = self::expectedIdnaMappings();

        self::assertSame($expected, UnicodeData::IDNA_MAPPING_MAP);
        self::assertSame(count($expected), UnicodeData::IDNA_MAPPING_CODE_POINT_COUNT);

        self::assertSame([0x0061], Unicode::idnaMapping(0x0041));
        self::assertNull(Unicode::idnaMapping(0x0061));
    }

    public function testGeneratedMarkRangesMatchPinnedGeneratorData(): void
    {
        $expected = self::expectedMarkRanges();

        self::assertSame($expected, UnicodeData::MARK_RANGES);
        self::assertSame(count($expected), UnicodeData::MARK_RANGE_COUNT);
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

    public function testUpstreamIdnaToAsciiUtf8Fixture(): void
    {
        foreach (self::idnaFixtureRows() as $index => [$source, $ascii, $status]) {
            self::assertIdnaFixtureResult(
                static fn (): string => Unicode::idnaToAscii($source, Unicode::IDNA_FLAG_USE_STD3ASCII_RULES),
                $ascii,
                $status,
                "IDNA UTF-8 fixture #{$index}",
            );
        }
    }

    public function testUpstreamIdnaToAsciiCodePointFixture(): void
    {
        foreach (self::idnaFixtureRows() as $index => [$source, $ascii, $status]) {
            $codePoints = self::decodeValidUtf8CodePoints($source);

            self::assertIdnaFixtureResult(
                static fn (): string => Unicode::idnaCodePointsToAscii($codePoints, Unicode::IDNA_FLAG_USE_STD3ASCII_RULES),
                $ascii,
                $status,
                "IDNA code-point fixture #{$index}",
            );
        }
    }

    public function testIdnaToUnicodeUtf8AndCodePointEdges(): void
    {
        $std3 = Unicode::IDNA_FLAG_USE_STD3ASCII_RULES;
        $transitional = $std3 | Unicode::IDNA_FLAG_TRANSITIONAL_PROCESSING;

        self::assertSame("fa\u{00DF}.de", Unicode::idnaToUnicode('xn--fa-hia.de', $std3));
        self::assertSame("fa\u{00DF}.de", Unicode::idnaToUnicode('XN--FA-HIA.de', $std3));
        self::assertSame(
            "fa\u{00DF}.de",
            Unicode::idnaCodePointsToUnicode(self::decodeValidUtf8CodePoints('xn--fa-hia.de'), $std3),
        );
        self::assertSame("fa\u{00DF}.de", Unicode::idnaToUnicode("fa\u{00DF}.de", $std3));
        self::assertSame('fass.de', Unicode::idnaToUnicode("fa\u{00DF}.de", $transitional));
        self::assertSame("fa\u{00DF}.de", Unicode::idnaToUnicode('xn--fa-hia.de', $transitional));
        self::assertSame("\u{00DF}.de", Unicode::idnaToUnicode('xn--zca.de', $std3));
        self::assertSame('ss.de', Unicode::idnaToUnicode("\u{1E9E}.de", $transitional));
        self::assertSame("\u{00E1}.de", Unicode::idnaToUnicode("a\u{0301}.de", $std3));
        self::assertSame("\u{00E1}.de", Unicode::idnaToUnicode("\u{0061}\u{0301}.de", $std3));
        self::assertSame('a.b', Unicode::idnaToUnicode("a\xC0\xAEb", $std3));
        self::assertSame('example.com.', Unicode::idnaToUnicode('example.com.', $std3));
        self::assertSame(str_repeat('a', 64), Unicode::idnaToUnicode(
            str_repeat('a', 64),
            $std3 | Unicode::IDNA_FLAG_VERIFY_DNS_LENGTH,
        ));

        self::assertSame('abc', Unicode::idnaToUnicode('xn--abc-', $std3));
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToUnicode('xn--u-ccb.com', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToUnicode('xn--0', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToUnicode("a\u{200C}b", $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToUnicode("\u{05D0}a", $std3 | Unicode::IDNA_FLAG_CHECK_BIDI),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaCodePointsToUnicode([0x0061, 0x1FFFFF], $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToUnicode('abc', 1 << 20),
            Status::ErrorUnexpectedData,
        );
    }

    public function testIdnaToAsciiFlagAndCodePointEdges(): void
    {
        $std3 = Unicode::IDNA_FLAG_USE_STD3ASCII_RULES;
        $verifyDnsLength = $std3 | Unicode::IDNA_FLAG_VERIFY_DNS_LENGTH;

        self::assertSame('xn--fa-hia.de', Unicode::idnaToAscii("fa\u{00DF}.de", $std3));
        self::assertSame(
            'fass.de',
            Unicode::idnaToAscii("fa\u{00DF}.de", $std3 | Unicode::IDNA_FLAG_TRANSITIONAL_PROCESSING),
        );
        self::assertSame(
            'ab',
            Unicode::idnaToAscii("a\u{200C}b", $std3 | Unicode::IDNA_FLAG_TRANSITIONAL_PROCESSING),
        );
        self::assertSame(
            'ss.de',
            Unicode::idnaToAscii("\u{1E9E}.de", $std3 | Unicode::IDNA_FLAG_TRANSITIONAL_PROCESSING),
        );
        self::assertSame(
            'fass.de',
            Unicode::idnaToAscii("fa\u{1E9E}.de", $std3 | Unicode::IDNA_FLAG_TRANSITIONAL_PROCESSING),
        );
        self::assertSame(
            'xn--zca.de',
            Unicode::idnaToAscii("\u{1E9E}.de", $std3),
        );
        self::assertSame(
            'xn--fa-hia.de',
            Unicode::idnaToAscii('xn--fa-hia.de', $std3 | Unicode::IDNA_FLAG_TRANSITIONAL_PROCESSING),
        );
        self::assertSame('xn--a-ttd.de', Unicode::idnaToAscii("a\u{0903}.de", $std3));

        self::assertSame(str_repeat('a', 63), Unicode::idnaToAscii(str_repeat('a', 63), $verifyDnsLength));
        self::assertSame(
            'abc-d',
            Unicode::idnaToAscii('abc-d', $std3 | Unicode::IDNA_FLAG_CHECK_HYPHENS),
        );
        self::assertSame(
            'abcd-e',
            Unicode::idnaToAscii('abcd-e', $std3 | Unicode::IDNA_FLAG_CHECK_HYPHENS),
        );
        self::assertSame('abc', Unicode::idnaToAscii('abc', $std3 | Unicode::IDNA_FLAG_CHECK_BIDI));
        self::assertSame('abc', Unicode::idnaToAscii('abc', $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS));
        self::assertSame(
            'xn--ngba799q',
            Unicode::idnaToAscii("\u{0628}\u{200C}\u{0628}", $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS),
        );
        self::assertSame(
            'xn--11b2ezcs70k',
            Unicode::idnaToAscii("\u{0915}\u{094D}\u{200C}\u{0937}", $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS),
        );
        self::assertSame(
            'xn--11b2ezcw70k',
            Unicode::idnaToAscii("\u{0915}\u{094D}\u{200D}\u{0937}", $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS),
        );
        self::assertSame(
            'xn--4dbc',
            Unicode::idnaToAscii("\u{05D0}\u{05D1}", $std3 | Unicode::IDNA_FLAG_CHECK_BIDI),
        );
        self::assertSame(
            'abc.xn--4dbc',
            Unicode::idnaToAscii("abc.\u{05D0}\u{05D1}", $std3 | Unicode::IDNA_FLAG_CHECK_BIDI),
        );
        self::assertSame('a', Unicode::idnaToAscii("\xC1\x81", $std3));
        self::assertSame('a.b', Unicode::idnaToAscii("a\xC0\xAEb", $std3));
        self::assertSame(
            'xn--ab-mia',
            Unicode::idnaToAscii("a\xC2Ab", $std3),
        );
        self::assertSame(
            Unicode::idnaCodePointsToAscii(Utf8::decode("\xC1\x81"), $std3),
            Unicode::idnaToAscii("\xC1\x81", $std3),
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaCodePointsToAscii([0x1FFFFF], $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaCodePointsToAscii([0x0061, 0x1FFFFF, 0x0062], $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii(str_repeat('a', 64), $verifyDnsLength),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('a..b', $verifyDnsLength),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('example.com.', $verifyDnsLength),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('a_b', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('a b', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('a!b', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('ab--d', $std3 | Unicode::IDNA_FLAG_CHECK_HYPHENS),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("a\u{200C}b", $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("a\u{200D}b", $std3 | Unicode::IDNA_FLAG_CHECK_JOINERS),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("\u{05D0}a", $std3 | Unicode::IDNA_FLAG_CHECK_BIDI),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("\u{05D0}1\u{0661}", $std3 | Unicode::IDNA_FLAG_CHECK_BIDI),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("123.\u{05D0}\u{05D1}", $std3 | Unicode::IDNA_FLAG_CHECK_BIDI),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("\u{0301}.de", $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("\u{0903}.de", $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii("\u{20DD}.de", $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertSame('abc', Unicode::idnaToAscii('xn--abc-', $std3));
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('xn--u-ccb.com', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('xn--0', $std3),
            Status::ErrorUnexpectedResult,
        );
        self::assertIdnaFailure(
            static fn (): string => Unicode::idnaToAscii('abc', 1 << 20),
            Status::ErrorUnexpectedData,
        );
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

    public function testUpstreamNormalizationUtf8Fixture(): void
    {
        $forms = [
            Unicode::FORM_NFC => 'nfc',
            Unicode::FORM_NFD => 'nfd',
            Unicode::FORM_NFKC => 'nfkc',
            Unicode::FORM_NFKD => 'nfkd',
        ];

        foreach (self::normalizationFixtureRows() as $index => $row) {
            $source = Utf8::encodeCodePoints($row['source']);

            foreach ($forms as $form => $key) {
                self::assertSame(
                    Utf8::encodeCodePoints($row[$key]),
                    Unicode::normalize($source, $form),
                    "UTF-8 normalization fixture #{$index} {$form}",
                );
            }
        }
    }

    public function testCompleteUtf8NormalizationReplacesMalformedSequences(): void
    {
        $replacement = Utf8::encodeCodePoint(Utf8::REPLACEMENT_CODE_POINT);
        foreach ([Unicode::FORM_NFC, Unicode::FORM_NFD, Unicode::FORM_NFKC, Unicode::FORM_NFKD] as $form) {
            self::assertSame($replacement, Unicode::normalize("\xFF", $form));
            self::assertSame($replacement, Unicode::normalize("\xC3", $form));
            self::assertSame($replacement, Unicode::normalize("\xE0\x80", $form));
            self::assertSame(
                Utf8::encodeCodePoints(Unicode::normalizeCodePoints([0x00C1], $form)),
                Unicode::normalize("\xC3A", $form),
            );
            self::assertSame($replacement, Unicode::normalize("\xF7\xBF\xBF\xBF", $form));
            self::assertSame('A' . $replacement . Utf8::encodeCodePoint(0x0301), Unicode::normalize("A\xF7\xBF\xBF\xBF\xCC\x81", $form));
        }

        self::assertSame([0x00C1], Unicode::normalizeCodePoints([0x0041, 0x1FFFFF, 0x0301], Unicode::FORM_NFC));
        self::assertSame([0x0041, 0x0301], Unicode::normalizeCodePoints([0x0041, 0x1FFFFF, 0x0301], Unicode::FORM_NFD));
        self::assertSame([0x0300, 0x0315], Unicode::normalizeCodePoints([0x0315, 0x0300], Unicode::FORM_NFD));
    }

    public function testUpstreamNormalizationStreamingEdgeFixture(): void
    {
        $source = "\u{1E9B}\u{0323}";
        $normalizer = new UnicodeNormalizer(Unicode::FORM_NFC);
        $normalized = $normalizer->normalize(substr($source, 0, 2), false);
        $normalized .= $normalizer->normalize(substr($source, 2), true);

        self::assertSame([0x1E9B, 0x0323], Utf8::decode($normalized));

        $source = Utf8::encodeCodePoint(0x04D6)
            . str_repeat(Utf8::encodeCodePoint(0x0300), 1024)
            . Utf8::encodeCodePoint(0x0415)
            . 'abc';
        $expected = Utf8::decode($source);

        $normalizer = new UnicodeNormalizer(Unicode::FORM_NFC);
        $normalizer->setFlushCount(1024);

        self::assertSame($expected, Utf8::decode($normalizer->normalize($source, true)));

        $normalizer = new UnicodeNormalizer(Unicode::FORM_NFC);
        $normalizer->setFlushCount(1024);
        $normalized = '';
        $flushedBeforeEnd = false;

        for ($i = 0, $length = strlen($source); $i < $length; $i++) {
            $chunk = $normalizer->normalize($source[$i], false);
            $flushedBeforeEnd = $flushedBeforeEnd || $chunk !== '';
            $normalized .= $chunk;
        }

        self::assertTrue($flushedBeforeEnd);
        $normalized .= $normalizer->finish();

        self::assertSame($expected, Utf8::decode($normalized));
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
     * @return array<int, list<int>>
     */
    private static function expectedIdnaMappings(): array
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
            '/\{\s*(LXB_UNICODE_IDNA_[A-Z_]+|0)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
            self::captureArray($source, 'lxb_unicode_idna_entries'),
            $idnaMatches,
            PREG_SET_ORDER,
        );

        $idnaEntries = [];
        foreach ($idnaMatches as $match) {
            $idnaEntries[] = [
                'length' => (int) $match[2],
                'index' => (int) $match[3],
            ];
        }

        preg_match_all(
            '/0x[0-9A-Fa-f]+|\d+/',
            self::captureArray($source, 'lxb_unicode_idna_cps'),
            $idnaCpMatches,
        );

        $idnaCodePoints = [];
        foreach ($idnaCpMatches[0] as $number) {
            $idnaCodePoints[] = self::parseNumber($number);
        }

        $map = [];

        foreach (self::unicodeTableMaps($source) as [$start, , $indexes]) {
            foreach ($indexes as $offset => $entryIndex) {
                $codePoint = $start + $offset;
                $idnaIndex = $unicodeEntries[$entryIndex][1] ?? 0;
                $idna = $idnaEntries[$idnaIndex] ?? null;

                if ($idna === null || $idna['length'] === 0) {
                    continue;
                }

                $map[$codePoint] = array_slice($idnaCodePoints, $idna['index'], $idna['length']);
            }
        }

        ksort($map);

        $cached = $map;

        return $cached;
    }

    /**
     * @return list<array{int, int}>
     */
    private static function expectedMarkRanges(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $sourcePath = dirname(__DIR__, 2) . '/tools/generate_unicode_data.php';
        $source = file_get_contents($sourcePath);

        if ($source === false) {
            throw new RuntimeException("Unable to read Unicode data generator: {$sourcePath}");
        }

        if (preg_match("/return <<<'RANGES'\n(.*?)\nRANGES;/s", $source, $match) !== 1) {
            throw new RuntimeException('Unable to locate pinned Mark ranges.');
        }

        $ranges = [];
        foreach (explode("\n", trim($match[1])) as $line) {
            [$start, $end] = explode('..', trim($line), 2);
            $ranges[] = [hexdec($start), hexdec($end)];
        }

        $cached = $ranges;

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
     * @return iterable<int, array{string, string, int}>
     */
    private static function idnaFixtureRows(): iterable
    {
        $sourcePath = dirname(__DIR__, 2) . '/upstream/lexbor/test/lexbor/unicode/unicode_idna_test_res.h';
        if (!is_file($sourcePath)) {
            throw new RuntimeException("Unable to read upstream Unicode IDNA fixture: {$sourcePath}");
        }

        foreach (new \SplFileObject($sourcePath) as $line) {
            if (
                !is_string($line)
                || preg_match(
                    '/^\s*\{\.source = \(const lxb_char_t \*\) "((?:\\\\x[0-9A-Fa-f]{2})*)", \.ascii = \(const lxb_char_t \*\) "((?:\\\\x[0-9A-Fa-f]{2})*)", \.status = (\d+)\} \/\* (\d+) \*\/,?$/',
                    rtrim($line),
                    $match,
                ) !== 1
            ) {
                continue;
            }

            yield (int) $match[4] => [
                self::parseEscapedHexBytes($match[1]),
                self::parseEscapedHexBytes($match[2]),
                (int) $match[3],
            ];
        }
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

    /**
     * @return list<int>
     */
    private static function decodeValidUtf8CodePoints(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($offset = 0; $offset < $length;) {
            $first = ord($data[$offset]);
            $needed = match (true) {
                $first < 0x80 => 1,
                ($first & 0xE0) === 0xC0 => 2,
                ($first & 0xF0) === 0xE0 => 3,
                ($first & 0xF8) === 0xF0 => 4,
                default => 0,
            };

            if ($needed === 0) {
                $codePoints[] = 0x1FFFFF;
                $offset++;
                continue;
            }

            if ($length - $offset < $needed) {
                $codePoints[] = 0x1FFFFF;
                break;
            }

            $second = $needed >= 2 ? ord($data[$offset + 1]) : 0;
            $third = $needed >= 3 ? ord($data[$offset + 2]) : 0;
            $fourth = $needed >= 4 ? ord($data[$offset + 3]) : 0;

            $codePoints[] = match ($needed) {
                1 => $first,
                2 => (($first ^ (0xC0 & $first)) << 6)
                    | ($second ^ (0x80 & $second)),
                3 => (($first ^ (0xE0 & $first)) << 12)
                    | (($second ^ (0x80 & $second)) << 6)
                    | ($third ^ (0x80 & $third)),
                4 => (($first ^ (0xF0 & $first)) << 18)
                    | (($second ^ (0x80 & $second)) << 12)
                    | (($third ^ (0x80 & $third)) << 6)
                    | ($fourth ^ (0x80 & $fourth)),
            };
            $offset += $needed;
        }

        return $codePoints;
    }

    private static function parseEscapedHexBytes(string $source): string
    {
        preg_match_all('/\\\\x([0-9A-Fa-f]{2})/', $source, $matches);

        return implode('', array_map(
            static fn (string $hex): string => chr(hexdec($hex)),
            $matches[1],
        ));
    }

    private static function assertIdnaFixtureResult(callable $convert, string $ascii, int $status, string $message): void
    {
        try {
            $actual = $convert();
        } catch (LexborException $exception) {
            if ($status !== 0) {
                self::assertTrue(true, "{$message}: expected {$exception->status->value}.");
                return;
            }

            self::fail("{$message}: unexpected {$exception->status->value}.");
        }

        self::assertSame($ascii, $actual, $message);
    }

    private static function assertIdnaFailure(callable $convert, Status $status): void
    {
        try {
            $convert();
        } catch (LexborException $exception) {
            self::assertSame($status, $exception->status);
            return;
        }

        self::fail("Expected IDNA failure with {$status->value}.");
    }
}
