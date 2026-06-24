<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourcePath = $root . '/upstream/lexbor/source/lexbor/unicode/res.h';
$outputPath = $root . '/src/Unicode/UnicodeData.php';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Missing upstream Unicode resource: {$sourcePath}\n");
    exit(1);
}

$source = file_get_contents($sourcePath);
if ($source === false) {
    fwrite(STDERR, "Unable to read upstream Unicode resource: {$sourcePath}\n");
    exit(1);
}

$typeByName = [
    '0' => 0,
    'LXB_UNICODE_IDNA__UNDEF' => 0,
    'LXB_UNICODE_IDNA_DEVIATION' => 1,
    'LXB_UNICODE_IDNA_DISALLOWED' => 2,
    'LXB_UNICODE_IDNA_IGNORED' => 3,
    'LXB_UNICODE_IDNA_MAPPED' => 4,
    'LXB_UNICODE_IDNA_VALID' => 5,
];

$decompositionTypeByName = [
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

$canonicalSeparatelyFlag = 1 << 7;

preg_match_all(
    '/\{\s*(\d+)\s*,\s*(\d+)\s*\}/',
    captureArray($source, 'lxb_unicode_entries'),
    $entryMatches,
    PREG_SET_ORDER,
);

$unicodeEntries = [];
foreach ($entryMatches as $match) {
    $unicodeEntries[] = [(int) $match[1], (int) $match[2]];
}

preg_match_all(
    '/\{\s*(LXB_UNICODE_IDNA_[A-Z_]+|0)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
    captureArray($source, 'lxb_unicode_idna_entries'),
    $idnaMatches,
    PREG_SET_ORDER,
);

$idnaEntries = [];
foreach ($idnaMatches as $match) {
    $name = $match[1];
    if (!array_key_exists($name, $typeByName)) {
        throw new RuntimeException("Unknown IDNA type {$name}.");
    }

    $idnaEntries[] = [
        'type' => $typeByName[$name],
        'length' => (int) $match[2],
        'index' => (int) $match[3],
    ];
}

preg_match_all(
    '/0x[0-9A-Fa-f]+|\d+/',
    captureArray($source, 'lxb_unicode_idna_cps'),
    $idnaCpMatches,
);

$idnaCodePoints = [];
foreach ($idnaCpMatches[0] as $number) {
    $idnaCodePoints[] = parseNumber($number);
}

preg_match_all(
    '/\{\s*([^,{}]+),\s*([^,{}]+),\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
    captureArray($source, 'lxb_unicode_normalization_entries'),
    $normalizationMatches,
    PREG_SET_ORDER,
);

$normalizationEntries = [];
foreach ($normalizationMatches as $match) {
    $type = parseDecompositionType($match[1], $decompositionTypeByName, $canonicalSeparatelyFlag);

    $normalizationEntries[] = [
        'type' => $type,
        'typeMask' => $type & ~$canonicalSeparatelyFlag,
        'canonicalSeparately' => ($type & $canonicalSeparatelyFlag) !== 0,
        'ccc' => (int) $match[3],
        'length' => (int) $match[4],
        'decomposition' => (int) $match[5],
        'composition' => (int) $match[6],
    ];
}

preg_match_all(
    '/0x[0-9A-Fa-f]+|\d+/',
    captureArray($source, 'lxb_unicode_decomposition_cps'),
    $decompositionCpMatches,
);

$decompositionCodePoints = [];
foreach ($decompositionCpMatches[0] as $number) {
    $decompositionCodePoints[] = parseNumber($number);
}

preg_match_all(
    '/\{\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\}/',
    captureArray($source, 'lxb_unicode_composition_entries'),
    $compositionEntryMatches,
    PREG_SET_ORDER,
);

$compositionEntries = [];
foreach ($compositionEntryMatches as $match) {
    $compositionEntries[] = [(int) $match[1], (int) $match[2], (int) $match[3]];
}

preg_match_all(
    '/\{\s*(0x[0-9A-Fa-f]+|\d+)\s*,\s*(true|false)\s*\}/',
    captureArray($source, 'lxb_unicode_composition_cps'),
    $compositionCpMatches,
    PREG_SET_ORDER,
);

$compositionCodePoints = [];
foreach ($compositionCpMatches as $match) {
    $compositionCodePoints[] = [
        parseNumber($match[1]),
        $match[2] === 'true',
    ];
}

$ranges = [];
$current = null;
$nonDisallowedCount = 0;
$idnaMappingMap = [];
$combiningClassMap = [];
$canonicalDecompositionMap = [];
$compatibilityDecompositionMap = [];
$compositionMap = [];
$compositionPairCount = 0;
$normalizationCompositionMap = [];
$normalizationCompositionPairCount = 0;
$markRanges = markRanges();
$bidiClassRanges = bidiClassRanges();
$joiningTypeRanges = joiningTypeRanges();

foreach (unicodeTableMaps($source) as [$start, $endExclusive, $indexes]) {
    foreach ($indexes as $offset => $entryIndex) {
        $codePoint = $start + $offset;
        [$normalizationIndex, $idnaIndex] = $unicodeEntries[$entryIndex] ?? [0, 0];
        $idnaEntry = $idnaEntries[$idnaIndex] ?? null;
        $type = $idnaIndex === 0 ? 2 : ($idnaEntry['type'] ?? 2);

        if ($idnaEntry !== null && $idnaEntry['length'] > 0) {
            $idnaMappingMap[$codePoint] = array_slice(
                $idnaCodePoints,
                $idnaEntry['index'],
                $idnaEntry['length'],
            );
        }

        $normalization = $normalizationEntries[$normalizationIndex] ?? null;
        if ($normalization !== null) {
            if ($normalization['ccc'] !== 0) {
                $combiningClassMap[$codePoint] = $normalization['ccc'];
            }

            if ($normalization['length'] > 0) {
                $compatibility = array_slice(
                    $decompositionCodePoints,
                    $normalization['decomposition'],
                    $normalization['length'],
                );

                $compatibilityDecompositionMap[$codePoint] = $compatibility;

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

                    $canonicalDecompositionMap[$codePoint] = $canonical;
                }
            }
        }

        $compositionIndex = $normalization['composition'] ?? 0;
        [$length, $index, $secondStart] = $compositionEntries[$compositionIndex] ?? [0, 0, 0];

        for ($i = 0; $i < $length; $i++) {
            [$composed, $excluded] = $compositionCodePoints[$index + $i] ?? [0, false];
            if ($composed === 0) {
                continue;
            }

            $compositionMap[$codePoint][$secondStart + $i] = $composed;
            $compositionPairCount++;

            if (!$excluded) {
                $normalizationCompositionMap[$codePoint][$secondStart + $i] = $composed;
                $normalizationCompositionPairCount++;
            }
        }

        if ($type === 2) {
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

if (!is_dir(dirname($outputPath)) && !mkdir(dirname($outputPath), 0777, true)) {
    fwrite(STDERR, "Unable to create output directory: " . dirname($outputPath) . "\n");
    exit(1);
}

$output = <<<'PHP'
<?php

declare(strict_types=1);

namespace Lexbor\Unicode;

/*
 * Generated from upstream/lexbor/source/lexbor/unicode/res.h by
 * tools/generate_unicode_data.php.
 */
final class UnicodeData
{

PHP;

$output .= '    public const int IDNA_TYPE_RANGE_COUNT = ' . count($ranges) . ";\n";
$output .= '    public const int IDNA_NON_DISALLOWED_CODE_POINT_COUNT = ' . $nonDisallowedCount . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var list<array{int, int, int}>\n";
$output .= "     */\n";
$output .= "    public const array IDNA_TYPE_RANGES = [\n";

foreach ($ranges as [$start, $end, $type]) {
    $output .= sprintf("        [0x%X, 0x%X, %d],\n", $start, $end, $type);
}

$output .= "    ];\n\n";
$output .= '    public const int IDNA_MAPPING_CODE_POINT_COUNT = ' . count($idnaMappingMap) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var array<int, list<int>>\n";
$output .= "     */\n";
$output .= "    public const array IDNA_MAPPING_MAP = [\n";

ksort($idnaMappingMap);
foreach ($idnaMappingMap as $codePoint => $mapping) {
    $output .= sprintf("        0x%X => [%s],\n", $codePoint, formatCodePointList($mapping));
}

$output .= "    ];\n\n";
$output .= '    public const int MARK_RANGE_COUNT = ' . count($markRanges) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var list<array{int, int}>\n";
$output .= "     */\n";
$output .= "    public const array MARK_RANGES = [\n";

foreach ($markRanges as [$start, $end]) {
    $output .= sprintf("        [0x%X, 0x%X],\n", $start, $end);
}

$output .= "    ];\n\n";
$output .= '    public const int BIDI_CLASS_RANGE_COUNT = ' . count($bidiClassRanges) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var list<array{int, int, int}>\n";
$output .= "     */\n";
$output .= "    public const array BIDI_CLASS_RANGES = [\n";

foreach ($bidiClassRanges as [$start, $end, $class]) {
    $output .= sprintf("        [0x%X, 0x%X, %d],\n", $start, $end, $class);
}

$output .= "    ];\n\n";
$output .= '    public const int JOINING_TYPE_RANGE_COUNT = ' . count($joiningTypeRanges) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var list<array{int, int, int}>\n";
$output .= "     */\n";
$output .= "    public const array JOINING_TYPE_RANGES = [\n";

foreach ($joiningTypeRanges as [$start, $end, $type]) {
    $output .= sprintf("        [0x%X, 0x%X, %d],\n", $start, $end, $type);
}

$output .= "    ];\n\n";
$output .= '    public const int COMBINING_CLASS_CODE_POINT_COUNT = ' . count($combiningClassMap) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var array<int, int>\n";
$output .= "     */\n";
$output .= "    public const array COMBINING_CLASS_MAP = [\n";

ksort($combiningClassMap);
foreach ($combiningClassMap as $codePoint => $ccc) {
    $output .= sprintf("        0x%X => %d,\n", $codePoint, $ccc);
}

$output .= "    ];\n\n";
$output .= '    public const int CANONICAL_DECOMPOSITION_COUNT = ' . count($canonicalDecompositionMap) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var array<int, list<int>>\n";
$output .= "     */\n";
$output .= "    public const array CANONICAL_DECOMPOSITION_MAP = [\n";

ksort($canonicalDecompositionMap);
foreach ($canonicalDecompositionMap as $codePoint => $decomposition) {
    $output .= sprintf("        0x%X => [%s],\n", $codePoint, formatCodePointList($decomposition));
}

$output .= "    ];\n\n";
$output .= '    public const int COMPATIBILITY_DECOMPOSITION_COUNT = ' . count($compatibilityDecompositionMap) . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var array<int, list<int>>\n";
$output .= "     */\n";
$output .= "    public const array COMPATIBILITY_DECOMPOSITION_MAP = [\n";

ksort($compatibilityDecompositionMap);
foreach ($compatibilityDecompositionMap as $codePoint => $decomposition) {
    $output .= sprintf("        0x%X => [%s],\n", $codePoint, formatCodePointList($decomposition));
}

$output .= "    ];\n\n";
$output .= '    public const int CANONICAL_COMPOSITION_PAIR_COUNT = ' . $compositionPairCount . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var array<int, array<int, int>>\n";
$output .= "     */\n";
$output .= "    public const array CANONICAL_COMPOSITION_MAP = [\n";

ksort($compositionMap);
foreach ($compositionMap as $first => $seconds) {
    ksort($seconds);
    $output .= sprintf("        0x%X => [\n", $first);

    foreach ($seconds as $second => $composed) {
        $output .= sprintf("            0x%X => 0x%X,\n", $second, $composed);
    }

    $output .= "        ],\n";
}

$output .= "    ];\n";
$output .= "\n";
$output .= '    public const int NORMALIZATION_COMPOSITION_PAIR_COUNT = ' . $normalizationCompositionPairCount . ";\n\n";
$output .= "    /**\n";
$output .= "     * @var array<int, array<int, int>>\n";
$output .= "     */\n";
$output .= "    public const array NORMALIZATION_COMPOSITION_MAP = [\n";

ksort($normalizationCompositionMap);
foreach ($normalizationCompositionMap as $first => $seconds) {
    ksort($seconds);
    $output .= sprintf("        0x%X => [\n", $first);

    foreach ($seconds as $second => $composed) {
        $output .= sprintf("            0x%X => 0x%X,\n", $second, $composed);
    }

    $output .= "        ],\n";
}

$output .= "    ];\n";
$output .= "}\n";

file_put_contents($outputPath, $output);

printf(
    "Generated %s with %d IDNA type ranges covering %d non-disallowed code points, %d IDNA mappings, %d Mark ranges, %d Bidi class ranges, %d joining type ranges, %d decomposition mappings, and %d canonical composition pairs.\n",
    $outputPath,
    count($ranges),
    $nonDisallowedCount,
    count($idnaMappingMap),
    count($markRanges),
    count($bidiClassRanges),
    count($joiningTypeRanges),
    count($compatibilityDecompositionMap),
    $compositionPairCount,
);

/**
 * @return list<array{int, int}>
 */
function markRanges(): array
{
    $ranges = [];

    foreach (explode("\n", trim(markRangeData())) as $line) {
        [$start, $end] = explode('..', trim($line), 2);
        $ranges[] = [hexdec($start), hexdec($end)];
    }

    return $ranges;
}

function markRangeData(): string
{
    return <<<'RANGES'
0300..036F
0483..0489
0591..05BD
05BF..05BF
05C1..05C2
05C4..05C5
05C7..05C7
0610..061A
064B..065F
0670..0670
06D6..06DC
06DF..06E4
06E7..06E8
06EA..06ED
0711..0711
0730..074A
07A6..07B0
07EB..07F3
07FD..07FD
0816..0819
081B..0823
0825..0827
0829..082D
0859..085B
0897..089F
08CA..08E1
08E3..0903
093A..093C
093E..094F
0951..0957
0962..0963
0981..0983
09BC..09BC
09BE..09C4
09C7..09C8
09CB..09CD
09D7..09D7
09E2..09E3
09FE..09FE
0A01..0A03
0A3C..0A3C
0A3E..0A42
0A47..0A48
0A4B..0A4D
0A51..0A51
0A70..0A71
0A75..0A75
0A81..0A83
0ABC..0ABC
0ABE..0AC5
0AC7..0AC9
0ACB..0ACD
0AE2..0AE3
0AFA..0AFF
0B01..0B03
0B3C..0B3C
0B3E..0B44
0B47..0B48
0B4B..0B4D
0B55..0B57
0B62..0B63
0B82..0B82
0BBE..0BC2
0BC6..0BC8
0BCA..0BCD
0BD7..0BD7
0C00..0C04
0C3C..0C3C
0C3E..0C44
0C46..0C48
0C4A..0C4D
0C55..0C56
0C62..0C63
0C81..0C83
0CBC..0CBC
0CBE..0CC4
0CC6..0CC8
0CCA..0CCD
0CD5..0CD6
0CE2..0CE3
0CF3..0CF3
0D00..0D03
0D3B..0D3C
0D3E..0D44
0D46..0D48
0D4A..0D4D
0D57..0D57
0D62..0D63
0D81..0D83
0DCA..0DCA
0DCF..0DD4
0DD6..0DD6
0DD8..0DDF
0DF2..0DF3
0E31..0E31
0E34..0E3A
0E47..0E4E
0EB1..0EB1
0EB4..0EBC
0EC8..0ECE
0F18..0F19
0F35..0F35
0F37..0F37
0F39..0F39
0F3E..0F3F
0F71..0F84
0F86..0F87
0F8D..0F97
0F99..0FBC
0FC6..0FC6
102B..103E
1056..1059
105E..1060
1062..1064
1067..106D
1071..1074
1082..108D
108F..108F
109A..109D
135D..135F
1712..1715
1732..1734
1752..1753
1772..1773
17B4..17D3
17DD..17DD
180B..180D
180F..180F
1885..1886
18A9..18A9
1920..192B
1930..193B
1A17..1A1B
1A55..1A5E
1A60..1A7C
1A7F..1A7F
1AB0..1ADD
1AE0..1AEB
1B00..1B04
1B34..1B44
1B6B..1B73
1B80..1B82
1BA1..1BAD
1BE6..1BF3
1C24..1C37
1CD0..1CD2
1CD4..1CE8
1CED..1CED
1CF4..1CF4
1CF7..1CF9
1DC0..1DFF
20D0..20F0
2CEF..2CF1
2D7F..2D7F
2DE0..2DFF
302A..302F
3099..309A
A66F..A672
A674..A67D
A69E..A69F
A6F0..A6F1
A802..A802
A806..A806
A80B..A80B
A823..A827
A82C..A82C
A880..A881
A8B4..A8C5
A8E0..A8F1
A8FF..A8FF
A926..A92D
A947..A953
A980..A983
A9B3..A9C0
A9E5..A9E5
AA29..AA36
AA43..AA43
AA4C..AA4D
AA7B..AA7D
AAB0..AAB0
AAB2..AAB4
AAB7..AAB8
AABE..AABF
AAC1..AAC1
AAEB..AAEF
AAF5..AAF6
ABE3..ABEA
ABEC..ABED
FB1E..FB1E
FE00..FE0F
FE20..FE2F
101FD..101FD
102E0..102E0
10376..1037A
10A01..10A03
10A05..10A06
10A0C..10A0F
10A38..10A3A
10A3F..10A3F
10AE5..10AE6
10D24..10D27
10D69..10D6D
10EAB..10EAC
10EFA..10EFF
10F46..10F50
10F82..10F85
11000..11002
11038..11046
11070..11070
11073..11074
1107F..11082
110B0..110BA
110C2..110C2
11100..11102
11127..11134
11145..11146
11173..11173
11180..11182
111B3..111C0
111C9..111CC
111CE..111CF
1122C..11237
1123E..1123E
11241..11241
112DF..112EA
11300..11303
1133B..1133C
1133E..11344
11347..11348
1134B..1134D
11357..11357
11362..11363
11366..1136C
11370..11374
113B8..113C0
113C2..113C2
113C5..113C5
113C7..113CA
113CC..113D0
113D2..113D2
113E1..113E2
11435..11446
1145E..1145E
114B0..114C3
115AF..115B5
115B8..115C0
115DC..115DD
11630..11640
116AB..116B7
1171D..1172B
1182C..1183A
11930..11935
11937..11938
1193B..1193E
11940..11940
11942..11943
119D1..119D7
119DA..119E0
119E4..119E4
11A01..11A0A
11A33..11A39
11A3B..11A3E
11A47..11A47
11A51..11A5B
11A8A..11A99
11B60..11B67
11C2F..11C36
11C38..11C3F
11C92..11CA7
11CA9..11CB6
11D31..11D36
11D3A..11D3A
11D3C..11D3D
11D3F..11D45
11D47..11D47
11D8A..11D8E
11D90..11D91
11D93..11D97
11EF3..11EF6
11F00..11F01
11F03..11F03
11F34..11F3A
11F3E..11F42
11F5A..11F5A
13440..13440
13447..13455
1611E..1612F
16AF0..16AF4
16B30..16B36
16F4F..16F4F
16F51..16F87
16F8F..16F92
16FE4..16FE4
16FF0..16FF1
1BC9D..1BC9E
1CF00..1CF2D
1CF30..1CF46
1D165..1D169
1D16D..1D172
1D17B..1D182
1D185..1D18B
1D1AA..1D1AD
1D242..1D244
1DA00..1DA36
1DA3B..1DA6C
1DA75..1DA75
1DA84..1DA84
1DA9B..1DA9F
1DAA1..1DAAF
1E000..1E006
1E008..1E018
1E01B..1E021
1E023..1E024
1E026..1E02A
1E08F..1E08F
1E130..1E136
1E2AE..1E2AE
1E2EC..1E2EF
1E4EC..1E4EF
1E5EE..1E5EF
1E6E3..1E6E3
1E6E6..1E6E6
1E6EE..1E6EF
1E6F5..1E6F5
1E8D0..1E8D6
1E944..1E94A
E0100..E01EF
RANGES;
}

/**
 * @return list<array{int, int, int}>
 */
function bidiClassRanges(): array
{
    assertIntlPropertyDataVersion();

    return propertyRanges(
        static fn (int $codePoint): int => IntlChar::charDirection($codePoint),
        [IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT],
    );
}

/**
 * @return list<array{int, int, int}>
 */
function joiningTypeRanges(): array
{
    assertIntlPropertyDataVersion();

    return propertyRanges(
        static fn (int $codePoint): int => IntlChar::getIntPropertyValue($codePoint, IntlChar::PROPERTY_JOINING_TYPE),
        [0, 1],
    );
}

/**
 * @param callable(int): int $property
 * @param list<int> $skipValues
 * @return list<array{int, int, int}>
 */
function propertyRanges(callable $property, array $skipValues): array
{
    $ranges = [];
    $current = null;

    for ($codePoint = 0; $codePoint <= 0x10FFFF; $codePoint++) {
        $value = $property($codePoint);
        if (in_array($value, $skipValues, true)) {
            if ($current !== null) {
                $ranges[] = $current;
                $current = null;
            }

            continue;
        }

        if ($current !== null && $current[1] === $codePoint - 1 && $current[2] === $value) {
            $current[1] = $codePoint;
            continue;
        }

        if ($current !== null) {
            $ranges[] = $current;
        }

        $current = [$codePoint, $codePoint, $value];
    }

    if ($current !== null) {
        $ranges[] = $current;
    }

    return $ranges;
}

function assertIntlPropertyDataVersion(): void
{
    if (!class_exists(IntlChar::class)) {
        throw new RuntimeException('IntlChar is required to generate Unicode Bidi and joining ranges.');
    }

    if (INTL_ICU_VERSION !== '78.3') {
        throw new RuntimeException('Unicode Bidi and joining range generation is pinned to ICU 78.3.');
    }
}

function captureArray(string $source, string $name): string
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

function parseNumber(string $number): int
{
    if (str_starts_with($number, '0x') || str_starts_with($number, '0X')) {
        return hexdec(substr($number, 2));
    }

    return (int) $number;
}

/**
 * @param array<string, int> $typeByName
 */
function parseDecompositionType(string $expression, array $typeByName, int $canonicalSeparatelyFlag): int
{
    $type = 0;

    foreach (explode('|', $expression) as $part) {
        $part = trim($part);

        if ($part === 'LXB_UNICODE_CANONICAL_SEPARATELY') {
            $type |= $canonicalSeparatelyFlag;
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
 * @param list<int> $codePoints
 */
function formatCodePointList(array $codePoints): string
{
    return implode(', ', array_map(
        static fn (int $codePoint): string => sprintf('0x%X', $codePoint),
        $codePoints,
    ));
}

/**
 * @return iterable<array{int, int, list<int>}>
 */
function unicodeTableMaps(string $source): iterable
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
