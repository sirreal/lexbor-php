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

    $idnaEntries[] = $typeByName[$name];
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
$combiningClassMap = [];
$canonicalDecompositionMap = [];
$compatibilityDecompositionMap = [];
$compositionMap = [];
$compositionPairCount = 0;
$normalizationCompositionMap = [];
$normalizationCompositionPairCount = 0;

foreach (unicodeTableMaps($source) as [$start, $endExclusive, $indexes]) {
    foreach ($indexes as $offset => $entryIndex) {
        $codePoint = $start + $offset;
        [$normalizationIndex, $idnaIndex] = $unicodeEntries[$entryIndex] ?? [0, 0];
        $type = $idnaIndex === 0 ? 2 : ($idnaEntries[$idnaIndex] ?? 2);

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
    "Generated %s with %d IDNA type ranges covering %d non-disallowed code points, %d decomposition mappings, and %d canonical composition pairs.\n",
    $outputPath,
    count($ranges),
    $nonDisallowedCount,
    count($compatibilityDecompositionMap),
    $compositionPairCount,
);

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
