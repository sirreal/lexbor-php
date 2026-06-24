<?php

declare(strict_types=1);

namespace Lexbor\Unicode;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Punycode\Punycode;

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

    public const int IDNA_FLAG_UNDEF = 0x00;
    public const int IDNA_FLAG_USE_STD3ASCII_RULES = 1 << 1;
    public const int IDNA_FLAG_CHECK_HYPHENS = 1 << 2;
    public const int IDNA_FLAG_CHECK_BIDI = 1 << 3;
    public const int IDNA_FLAG_CHECK_JOINERS = 1 << 4;
    public const int IDNA_FLAG_TRANSITIONAL_PROCESSING = 1 << 5;
    public const int IDNA_FLAG_VERIFY_DNS_LENGTH = 1 << 6;

    private const int MAX_CODE_POINT = 0x10FFFF;
    private const int MAX_DNS_LABEL_LENGTH = 63;
    private const int MAX_DNS_DOMAIN_LENGTH = 253;
    private const int VIRAMA_COMBINING_CLASS = 9;
    private const int BIDI_L = 0;
    private const int BIDI_R = 1;
    private const int BIDI_EN = 2;
    private const int BIDI_ES = 3;
    private const int BIDI_ET = 4;
    private const int BIDI_AN = 5;
    private const int BIDI_CS = 6;
    private const int BIDI_ON = 10;
    private const int BIDI_AL = 13;
    private const int BIDI_NSM = 17;
    private const int BIDI_BN = 18;
    private const int JOINING_D = 2;
    private const int JOINING_L = 3;
    private const int JOINING_R = 4;
    private const int JOINING_T = 5;
    private const int IDNA_KNOWN_FLAGS = self::IDNA_FLAG_USE_STD3ASCII_RULES
        | self::IDNA_FLAG_CHECK_HYPHENS
        | self::IDNA_FLAG_CHECK_BIDI
        | self::IDNA_FLAG_CHECK_JOINERS
        | self::IDNA_FLAG_TRANSITIONAL_PROCESSING
        | self::IDNA_FLAG_VERIFY_DNS_LENGTH;

    /**
     * @var array<int, list<int>>
     */
    private const array IDNA_TRANSITIONAL_DEVIATION_MAP = [
        0x00DF => [0x0073, 0x0073],
        0x03C2 => [0x03C3],
        0x200C => [],
        0x200D => [],
    ];

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

    /**
     * @return list<int>|null
     */
    public static function idnaMapping(int $codePoint): ?array
    {
        return UnicodeData::IDNA_MAPPING_MAP[$codePoint] ?? null;
    }

    public static function idnaToAscii(
        string $data,
        int $flags = self::IDNA_FLAG_USE_STD3ASCII_RULES,
    ): string {
        return self::idnaCodePointsToAscii(self::decodeForIdna($data), $flags);
    }

    /**
     * @param list<int> $codePoints
     */
    public static function idnaCodePointsToAscii(
        array $codePoints,
        int $flags = self::IDNA_FLAG_USE_STD3ASCII_RULES,
    ): string {
        self::validateIdnaFlags($flags);
        self::assertIdnaCodePoints($codePoints);

        $labels = [];
        $processed = self::normalizeCodePoints(self::mapIdnaCodePoints($codePoints, $flags), self::FORM_NFC);
        $start = 0;
        $length = count($processed);

        foreach ($processed as $index => $codePoint) {
            if ($codePoint !== 0x002E) {
                continue;
            }

            $labels[] = array_slice($processed, $start, $index - $start);
            $start = $index + 1;
        }

        if ($length > $start || ($length >= 1 && $processed[$length - 1] === 0x002E)) {
            $labels[] = array_slice($processed, $start);
        }

        $isBidiDomain = ($flags & self::IDNA_FLAG_CHECK_BIDI) !== 0
            && self::isBidiDomain($labels);
        $ascii = [];
        foreach ($labels as $label) {
            $ascii[] = self::idnaLabelToAscii($label, $flags, $isBidiDomain);
        }

        $result = implode('.', $ascii);

        if (($flags & self::IDNA_FLAG_VERIFY_DNS_LENGTH) !== 0) {
            self::verifyDnsLength($ascii, $result);
        }

        return $result;
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

    /**
     * @param list<int> $codePoints
     * @return list<int>
     */
    private static function mapIdnaCodePoints(array $codePoints, int $flags): array
    {
        $mapped = [];

        foreach ($codePoints as $codePoint) {
            $type = self::idnaType($codePoint);

            switch ($type) {
                case self::IDNA_IGNORED:
                    break;

                case self::IDNA_MAPPED:
                    foreach (self::idnaMappedCodePoints($codePoint, $flags) as $mappedCodePoint) {
                        $mapped[] = $mappedCodePoint;
                    }
                    break;

                case self::IDNA_DEVIATION:
                    if (($flags & self::IDNA_FLAG_TRANSITIONAL_PROCESSING) !== 0) {
                        foreach (self::idnaTransitionalDeviationMapping($codePoint) as $mappedCodePoint) {
                            $mapped[] = $mappedCodePoint;
                        }

                        break;
                    }

                    $mapped[] = $codePoint;
                    break;

                case self::IDNA_DISALLOWED:
                case self::IDNA_VALID:
                default:
                    $mapped[] = $codePoint;
                    break;
            }
        }

        return $mapped;
    }

    /**
     * @return list<int>
     */
    private static function idnaMappedCodePoints(int $codePoint, int $flags): array
    {
        if (($flags & self::IDNA_FLAG_TRANSITIONAL_PROCESSING) !== 0 && $codePoint === 0x1E9E) {
            return [0x0073, 0x0073];
        }

        return self::idnaMapping($codePoint) ?? [];
    }

    /**
     * @param list<int> $codePoints
     */
    private static function assertIdnaCodePoints(array $codePoints): void
    {
        foreach ($codePoints as $codePoint) {
            if ($codePoint < 0 || $codePoint > self::MAX_CODE_POINT) {
                self::unexpectedResult('IDNA code point is outside the Unicode range.');
            }
        }
    }

    /**
     * @return list<int>
     */
    private static function idnaTransitionalDeviationMapping(int $codePoint): array
    {
        if (! array_key_exists($codePoint, self::IDNA_TRANSITIONAL_DEVIATION_MAP)) {
            self::unexpectedResult('IDNA deviation code point has no transitional mapping.');
        }

        return self::IDNA_TRANSITIONAL_DEVIATION_MAP[$codePoint];
    }

    private static function validateIdnaFlags(int $flags): void
    {
        if (($flags & ~self::IDNA_KNOWN_FLAGS) !== 0) {
            self::unexpectedData('Unknown IDNA flag.');
        }
    }

    /**
     * @param list<int> $label
     */
    private static function idnaLabelToAscii(array $label, int $flags, bool $isBidiDomain): string
    {
        if (self::startsWithAcePrefix($label)) {
            try {
                $decoded = Punycode::decodeToCodePoints(self::asciiFromCodePoints(array_slice($label, 4)));
            } catch (LexborException) {
                self::unexpectedResult('IDNA A-label could not be decoded.');
            }

            if (! self::isValidDecodedAceLabel($decoded, $flags, $isBidiDomain)) {
                self::unexpectedResult('Decoded IDNA label does not satisfy validity criteria.');
            }

            return self::encodeIdnaLabel($decoded);
        }

        if (! self::idnaValidityCriteria($label, $flags, $isBidiDomain)) {
            self::unexpectedResult('IDNA label does not satisfy validity criteria.');
        }

        return self::encodeIdnaLabel($label);
    }

    /**
     * @param list<int> $label
     */
    private static function isValidDecodedAceLabel(array $label, int $flags, bool $isBidiDomain): bool
    {
        if ($label === [] || self::isAsciiOnly($label)) {
            return false;
        }

        if (self::normalizeCodePoints($label, self::FORM_NFC) !== $label) {
            return false;
        }

        return self::idnaValidityCriteria(
            $label,
            $flags & ~self::IDNA_FLAG_TRANSITIONAL_PROCESSING,
            $isBidiDomain || self::labelHasBidiCodePoint($label),
        );
    }

    /**
     * @param list<int> $label
     */
    private static function encodeIdnaLabel(array $label): string
    {
        $result = Punycode::encodeCodePointsResult($label);

        return $result->unchanged ? $result->data : 'xn--' . $result->data;
    }

    /**
     * @param list<string> $labels
     */
    private static function verifyDnsLength(array $labels, string $domain): void
    {
        $lastIndex = count($labels) - 1;

        if ($lastIndex < 0) {
            self::unexpectedResult('IDNA domain is empty.');
        }

        foreach ($labels as $index => $label) {
            $length = strlen($label);

            if ($length === 0) {
                self::unexpectedResult('IDNA DNS label is empty.');
            }

            if ($length > self::MAX_DNS_LABEL_LENGTH) {
                self::unexpectedResult('IDNA DNS label is too long.');
            }
        }

        $domainLength = str_ends_with($domain, '.') ? strlen($domain) - 1 : strlen($domain);
        if ($domainLength > self::MAX_DNS_DOMAIN_LENGTH) {
            self::unexpectedResult('IDNA DNS domain is too long.');
        }
    }

    /**
     * @param list<int> $label
     */
    private static function startsWithAcePrefix(array $label): bool
    {
        return count($label) >= 4
            && ($label[0] === 0x0078 || $label[0] === 0x0058)
            && ($label[1] === 0x006E || $label[1] === 0x004E)
            && $label[2] === 0x002D
            && $label[3] === 0x002D;
    }

    /**
     * @param list<int> $label
     */
    private static function isAsciiOnly(array $label): bool
    {
        foreach ($label as $codePoint) {
            if ($codePoint >= 0x80) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<list<int>> $labels
     */
    private static function isBidiDomain(array $labels): bool
    {
        foreach ($labels as $label) {
            if (self::startsWithAcePrefix($label)) {
                try {
                    $label = Punycode::decodeToCodePoints(self::asciiFromCodePoints(array_slice($label, 4)));
                } catch (LexborException) {
                    continue;
                }
            }

            if (self::labelHasBidiCodePoint($label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int> $label
     */
    private static function labelHasBidiCodePoint(array $label): bool
    {
        foreach ($label as $codePoint) {
            $class = self::bidiClass($codePoint);
            if ($class === self::BIDI_R || $class === self::BIDI_AL || $class === self::BIDI_AN) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int> $codePoints
     */
    private static function asciiFromCodePoints(array $codePoints): string
    {
        $ascii = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint < 0 || $codePoint >= 0x80) {
                self::unexpectedData('Non-ASCII code point in IDNA ASCII label.');
            }

            $ascii .= chr($codePoint);
        }

        return $ascii;
    }

    /**
     * @param list<int> $label
     */
    private static function idnaValidityCriteria(array $label, int $flags, bool $isBidiDomain): bool
    {
        $length = count($label);

        if ($length >= 1 && self::isMarkCodePoint($label[0])) {
            return false;
        }

        if (($flags & self::IDNA_FLAG_CHECK_HYPHENS) !== 0) {
            if ($length >= 4 && $label[2] === 0x002D && $label[3] === 0x002D) {
                return false;
            }

            if ($length >= 1 && ($label[0] === 0x002D || $label[$length - 1] === 0x002D)) {
                return false;
            }
        } elseif (self::startsWithAcePrefix($label)) {
            return false;
        }

        foreach ($label as $codePoint) {
            if ($codePoint === 0x002E) {
                return false;
            }

            if (
                ($flags & self::IDNA_FLAG_USE_STD3ASCII_RULES) !== 0
                && $codePoint < 0x80
                && ! self::isStd3AsciiCodePoint($codePoint)
            ) {
                return false;
            }

            $type = self::idnaType($codePoint);

            switch ($type) {
                case self::IDNA_VALID:
                    break;

                case self::IDNA_DEVIATION:
                    if (($flags & self::IDNA_FLAG_TRANSITIONAL_PROCESSING) === 0) {
                        break;
                    }

                    return false;

                case self::IDNA_DISALLOWED:
                case self::IDNA_IGNORED:
                case self::IDNA_MAPPED:
                default:
                    return false;
            }
        }

        if (
            ($flags & self::IDNA_FLAG_CHECK_JOINERS) !== 0
            && ! self::satisfiesJoinerContext($label)
        ) {
            return false;
        }

        if (
            ($flags & self::IDNA_FLAG_CHECK_BIDI) !== 0
            && $isBidiDomain
            && ! self::satisfiesBidiRule($label)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param list<int> $label
     */
    private static function satisfiesJoinerContext(array $label): bool
    {
        $length = count($label);

        foreach ($label as $index => $codePoint) {
            if ($codePoint !== 0x200C && $codePoint !== 0x200D) {
                continue;
            }

            if (
                $index > 0
                && self::combiningClass($label[$index - 1]) === self::VIRAMA_COMBINING_CLASS
            ) {
                continue;
            }

            if ($codePoint === 0x200D) {
                return false;
            }

            $left = $index - 1;
            while ($left >= 0 && self::joiningType($label[$left]) === self::JOINING_T) {
                $left--;
            }

            if ($left < 0 || ! self::isLeftJoiningContextType(self::joiningType($label[$left]))) {
                return false;
            }

            $right = $index + 1;
            while ($right < $length && self::joiningType($label[$right]) === self::JOINING_T) {
                $right++;
            }

            if ($right >= $length || ! self::isRightJoiningContextType(self::joiningType($label[$right]))) {
                return false;
            }
        }

        return true;
    }

    private static function isLeftJoiningContextType(int $type): bool
    {
        return $type === self::JOINING_L || $type === self::JOINING_D;
    }

    private static function isRightJoiningContextType(int $type): bool
    {
        return $type === self::JOINING_R || $type === self::JOINING_D;
    }

    /**
     * @param list<int> $label
     */
    private static function satisfiesBidiRule(array $label): bool
    {
        if ($label === []) {
            return true;
        }

        $firstClass = self::bidiClass($label[0]);
        if ($firstClass !== self::BIDI_L && $firstClass !== self::BIDI_R && $firstClass !== self::BIDI_AL) {
            return false;
        }

        if ($firstClass === self::BIDI_R || $firstClass === self::BIDI_AL) {
            return self::satisfiesRtlBidiRule($label);
        }

        return self::satisfiesLtrBidiRule($label);
    }

    /**
     * @param list<int> $label
     */
    private static function satisfiesRtlBidiRule(array $label): bool
    {
        $hasEuropeanNumber = false;
        $hasArabicNumber = false;

        foreach ($label as $codePoint) {
            $class = self::bidiClass($codePoint);
            if (! self::isRtlBidiClassAllowed($class)) {
                return false;
            }

            $hasEuropeanNumber = $hasEuropeanNumber || $class === self::BIDI_EN;
            $hasArabicNumber = $hasArabicNumber || $class === self::BIDI_AN;
        }

        if ($hasEuropeanNumber && $hasArabicNumber) {
            return false;
        }

        return self::isRtlBidiEndClass(self::lastNonNsmBidiClass($label));
    }

    /**
     * @param list<int> $label
     */
    private static function satisfiesLtrBidiRule(array $label): bool
    {
        foreach ($label as $codePoint) {
            if (! self::isLtrBidiClassAllowed(self::bidiClass($codePoint))) {
                return false;
            }
        }

        return self::isLtrBidiEndClass(self::lastNonNsmBidiClass($label));
    }

    private static function isRtlBidiClassAllowed(int $class): bool
    {
        return match ($class) {
            self::BIDI_R,
            self::BIDI_AL,
            self::BIDI_AN,
            self::BIDI_EN,
            self::BIDI_ES,
            self::BIDI_CS,
            self::BIDI_ET,
            self::BIDI_ON,
            self::BIDI_BN,
            self::BIDI_NSM => true,
            default => false,
        };
    }

    private static function isLtrBidiClassAllowed(int $class): bool
    {
        return match ($class) {
            self::BIDI_L,
            self::BIDI_EN,
            self::BIDI_ES,
            self::BIDI_CS,
            self::BIDI_ET,
            self::BIDI_ON,
            self::BIDI_BN,
            self::BIDI_NSM => true,
            default => false,
        };
    }

    private static function isRtlBidiEndClass(int $class): bool
    {
        return $class === self::BIDI_R
            || $class === self::BIDI_AL
            || $class === self::BIDI_EN
            || $class === self::BIDI_AN;
    }

    private static function isLtrBidiEndClass(int $class): bool
    {
        return $class === self::BIDI_L || $class === self::BIDI_EN;
    }

    /**
     * @param list<int> $label
     */
    private static function lastNonNsmBidiClass(array $label): int
    {
        for ($index = count($label) - 1; $index >= 0; $index--) {
            $class = self::bidiClass($label[$index]);
            if ($class !== self::BIDI_NSM) {
                return $class;
            }
        }

        return self::BIDI_NSM;
    }

    private static function isMarkCodePoint(int $codePoint): bool
    {
        return self::lookupRangeValue(
            UnicodeData::MARK_RANGES,
            UnicodeData::MARK_RANGE_COUNT,
            $codePoint,
            0,
        ) === 1;
    }

    private static function bidiClass(int $codePoint): int
    {
        return self::lookupRangeValue(
            UnicodeData::BIDI_CLASS_RANGES,
            UnicodeData::BIDI_CLASS_RANGE_COUNT,
            $codePoint,
            self::BIDI_L,
        );
    }

    private static function joiningType(int $codePoint): int
    {
        return self::lookupRangeValue(
            UnicodeData::JOINING_TYPE_RANGES,
            UnicodeData::JOINING_TYPE_RANGE_COUNT,
            $codePoint,
            0,
        );
    }

    /**
     * @param list<array{int, int}|array{int, int, int}> $ranges
     */
    private static function lookupRangeValue(array $ranges, int $count, int $codePoint, int $default): int
    {
        $left = 0;
        $right = $count - 1;

        while ($left <= $right) {
            $middle = intdiv($left + $right, 2);
            [$start, $end] = $ranges[$middle];

            if ($codePoint < $start) {
                $right = $middle - 1;
                continue;
            }

            if ($codePoint > $end) {
                $left = $middle + 1;
                continue;
            }

            return $ranges[$middle][2] ?? 1;
        }

        return $default;
    }

    /**
     * @return list<int>
     */
    private static function decodeForIdna(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($offset = 0; $offset < $length;) {
            $first = ord($data[$offset]);
            $needed = self::utf8Length($first);

            if ($needed === 0 || $length - $offset < $needed) {
                self::unexpectedData('Invalid UTF-8 data for IDNA processing.');
            }

            $codePoint = self::decodeValidUtf8Single($data, $offset, $needed);
            if ($codePoint > self::MAX_CODE_POINT) {
                self::unexpectedData('Decoded IDNA code point is outside the Unicode range.');
            }

            $codePoints[] = $codePoint;
            $offset += $needed;
        }

        return $codePoints;
    }

    private static function utf8Length(int $byte): int
    {
        if ($byte < 0x80) {
            return 1;
        }

        if (($byte & 0xE0) === 0xC0) {
            return 2;
        }

        if (($byte & 0xF0) === 0xE0) {
            return 3;
        }

        if (($byte & 0xF8) === 0xF0) {
            return 4;
        }

        return 0;
    }

    private static function isStd3AsciiCodePoint(int $codePoint): bool
    {
        return $codePoint === 0x002D
            || ($codePoint >= 0x0030 && $codePoint <= 0x0039)
            || ($codePoint >= 0x0041 && $codePoint <= 0x005A)
            || ($codePoint >= 0x0061 && $codePoint <= 0x007A);
    }

    private static function decodeValidUtf8Single(string $data, int $offset, int $length): int
    {
        $first = ord($data[$offset]);

        if ($length === 1) {
            return $first;
        }

        $second = ord($data[$offset + 1]);

        if ($length === 2) {
            return (($first ^ (0xC0 & $first)) << 6)
                | ($second ^ (0x80 & $second));
        }

        $third = ord($data[$offset + 2]);

        if ($length === 3) {
            return (($first ^ (0xE0 & $first)) << 12)
                | (($second ^ (0x80 & $second)) << 6)
                | ($third ^ (0x80 & $third));
        }

        $fourth = ord($data[$offset + 3]);

        return (($first ^ (0xF0 & $first)) << 18)
            | (($second ^ (0x80 & $second)) << 12)
            | (($third ^ (0x80 & $third)) << 6)
            | ($fourth ^ (0x80 & $fourth));
    }

    private static function unexpectedData(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }

    private static function unexpectedResult(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedResult, $message);
    }
}
