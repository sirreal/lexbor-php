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

$ranges = [];
$current = null;
$nonDisallowedCount = 0;

foreach (unicodeTableMaps($source) as [$start, $endExclusive, $indexes]) {
    foreach ($indexes as $offset => $entryIndex) {
        $codePoint = $start + $offset;
        $idnaIndex = $unicodeEntries[$entryIndex][1] ?? 0;
        $type = $idnaIndex === 0 ? 2 : ($idnaEntries[$idnaIndex] ?? 2);

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
 * tools/generate_unicode_idna_data.php.
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

$output .= "    ];\n";
$output .= "}\n";

file_put_contents($outputPath, $output);

printf(
    "Generated %s with %d IDNA type ranges covering %d non-disallowed code points.\n",
    $outputPath,
    count($ranges),
    $nonDisallowedCount,
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
