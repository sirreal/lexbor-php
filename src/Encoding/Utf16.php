<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Utf16
{
    public const int REPLACEMENT_CODE_POINT = Utf8::REPLACEMENT_CODE_POINT;
    public const int DECODE_CONTINUE = Utf8::DECODE_CONTINUE;
    public const int ENCODE_OK = Utf8::ENCODE_OK;
    public const int ENCODE_ERROR = Utf8::ENCODE_ERROR;
    public const int ENCODE_SMALL_BUFFER = Utf8::ENCODE_SMALL_BUFFER;

    /**
     * @return list<int>
     */
    public static function decodeBigEndianWithReplacement(string $data): array
    {
        return self::decodeWithReplacement($data, true);
    }

    public static function decodeBigEndianToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingLeadByte = null,
        ?int $pendingSurrogate = null,
    ): DecodeResult {
        return self::decodeToBuffer($data, $capacity, true, $offset, $pendingLeadByte, $pendingSurrogate);
    }

    /**
     * @return list<int>
     */
    public static function decodeLittleEndianWithReplacement(string $data): array
    {
        return self::decodeWithReplacement($data, false);
    }

    public static function decodeLittleEndianToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingLeadByte = null,
        ?int $pendingSurrogate = null,
    ): DecodeResult {
        return self::decodeToBuffer($data, $capacity, false, $offset, $pendingLeadByte, $pendingSurrogate);
    }

    public static function encodeBigEndianCodePoint(int $codePoint): string
    {
        return self::encodeCodePoint($codePoint, true);
    }

    public static function encodeLittleEndianCodePoint(int $codePoint): string
    {
        return self::encodeCodePoint($codePoint, false);
    }

    public static function encodeBigEndianCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        return self::encodeCodePointWithCapacity($codePoint, $capacity, true);
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeBigEndianCodePointsToBuffer(array $codePoints, int $capacity): BufferEncodeResult
    {
        return self::encodeCodePointsToBuffer($codePoints, $capacity, true);
    }

    public static function encodeLittleEndianCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        return self::encodeCodePointWithCapacity($codePoint, $capacity, false);
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeLittleEndianCodePointsToBuffer(array $codePoints, int $capacity): BufferEncodeResult
    {
        return self::encodeCodePointsToBuffer($codePoints, $capacity, false);
    }

    /**
     * @return list<int>
     */
    private static function decodeWithReplacement(string $data, bool $bigEndian): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($i = 0; $i < $length;) {
            if ($i + 1 >= $length) {
                $codePoints[] = self::DECODE_CONTINUE;
                break;
            }

            $unit = self::readUnit($data, $i, $bigEndian);
            $i += 2;

            if (self::isHighSurrogate($unit)) {
                if ($i >= $length || $i + 1 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $next = self::readUnit($data, $i, $bigEndian);

                if (self::isLowSurrogate($next)) {
                    $codePoints[] = 0x10000 + (($unit - 0xD800) << 10) + ($next - 0xDC00);
                    $i += 2;
                    continue;
                }

                $codePoints[] = self::REPLACEMENT_CODE_POINT;
                continue;
            }

            if (self::isLowSurrogate($unit)) {
                $codePoints[] = self::REPLACEMENT_CODE_POINT;
                continue;
            }

            $codePoints[] = $unit;
        }

        return $codePoints;
    }

    private static function decodeToBuffer(
        string $data,
        int $capacity,
        bool $bigEndian,
        int $offset,
        ?int $pendingLeadByte,
        ?int $pendingSurrogate,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected('UTF-16 decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $highSurrogate = $pendingSurrogate;

        while (true) {
            if ($pendingLeadByte === null && $offset >= $length) {
                if ($highSurrogate !== null) {
                    return new DecodeResult(Status::Continue, $codePoints, $offset, null, $highSurrogate);
                }

                break;
            }

            if (count($codePoints) >= $capacity) {
                return new DecodeResult(Status::SmallBuffer, $codePoints, $offset, $pendingLeadByte, $highSurrogate);
            }

            if ($pendingLeadByte !== null) {
                if ($offset >= $length) {
                    return new DecodeResult(Status::Continue, $codePoints, $offset, $pendingLeadByte, $highSurrogate);
                }

                $lead = $pendingLeadByte;
                $unit = self::unitFromBytes($lead, ord($data[$offset]), $bigEndian);
                $offset++;
                $pendingLeadByte = null;
            } else {
                $lead = ord($data[$offset]);
                $offset++;

                if ($offset >= $length) {
                    return new DecodeResult(Status::Continue, $codePoints, $offset, $lead, $highSurrogate);
                }

                $unit = self::unitFromBytes($lead, ord($data[$offset]), $bigEndian);
                $offset++;
            }

            if ($highSurrogate !== null) {
                if (self::isLowSurrogate($unit)) {
                    $append = 0x10000 + (($highSurrogate - 0xD800) << 10) + ($unit - 0xDC00);

                    if (count($codePoints) >= $capacity) {
                        return new DecodeResult(Status::SmallBuffer, $codePoints, $offset, null, $highSurrogate);
                    }

                    $codePoints[] = $append;
                    $highSurrogate = null;
                    continue;
                }

                $pendingOffset = $offset - 1;

                if (count($codePoints) >= $capacity) {
                    return new DecodeResult(Status::SmallBuffer, $codePoints, $pendingOffset, $lead);
                }

                $codePoints[] = self::REPLACEMENT_CODE_POINT;
                $highSurrogate = null;

                if (count($codePoints) >= $capacity) {
                    return new DecodeResult(Status::SmallBuffer, $codePoints, $pendingOffset, $lead);
                }

                $offset = $pendingOffset;
                $pendingLeadByte = $lead;
                continue;
            }

            if (self::isHighSurrogate($unit)) {
                $highSurrogate = $unit;

                if ($offset >= $length) {
                    return new DecodeResult(Status::Continue, $codePoints, $offset, null, $highSurrogate);
                }

                continue;
            }

            if (self::isLowSurrogate($unit)) {
                if (count($codePoints) >= $capacity) {
                    return new DecodeResult(Status::SmallBuffer, $codePoints, $offset);
                }

                $codePoints[] = self::REPLACEMENT_CODE_POINT;
                continue;
            }

            if (count($codePoints) >= $capacity) {
                return new DecodeResult(Status::SmallBuffer, $codePoints, $offset);
            }

            $codePoints[] = $unit;
        }

        return new DecodeResult(Status::Ok, $codePoints, $offset);
    }

    private static function encodeCodePoint(int $codePoint, bool $bigEndian): string
    {
        if ($codePoint < 0 || $codePoint >= 0x110000) {
            self::unexpected('Code point cannot be encoded as UTF-16.');
        }

        if ($codePoint < 0x10000) {
            return self::writeUnit($codePoint, $bigEndian);
        }

        $codePoint -= 0x10000;

        return self::writeUnit(0xD800 | ($codePoint >> 10), $bigEndian)
            . self::writeUnit(0xDC00 | ($codePoint & 0x03FF), $bigEndian);
    }

    private static function encodeCodePointWithCapacity(
        int $codePoint,
        int $capacity,
        bool $bigEndian,
    ): EncodeResult {
        if ($capacity < 0) {
            self::unexpected('UTF-16 encode buffer capacity cannot be negative.');
        }

        if ($codePoint < 0 || $codePoint >= 0x110000) {
            return new EncodeResult(self::ENCODE_ERROR, '');
        }

        $bytes = self::encodeCodePoint($codePoint, $bigEndian);

        if (strlen($bytes) > $capacity) {
            return new EncodeResult(self::ENCODE_SMALL_BUFFER, '');
        }

        return new EncodeResult(strlen($bytes), $bytes);
    }

    /**
     * @param list<int> $codePoints
     */
    private static function encodeCodePointsToBuffer(array $codePoints, int $capacity, bool $bigEndian): BufferEncodeResult
    {
        if ($capacity < 0) {
            self::unexpected('UTF-16 encode buffer capacity cannot be negative.');
        }

        $out = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint < 0 || $codePoint >= 0x110000) {
                return new BufferEncodeResult(Status::ErrorUnexpectedData, $out);
            }

            $bytes = self::encodeCodePoint($codePoint, $bigEndian);

            if (strlen($out) + strlen($bytes) > $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= $bytes;
        }

        return new BufferEncodeResult(Status::Ok, $out);
    }

    private static function readUnit(string $data, int $offset, bool $bigEndian): int
    {
        return self::unitFromBytes(ord($data[$offset]), ord($data[$offset + 1]), $bigEndian);
    }

    private static function unitFromBytes(int $first, int $second, bool $bigEndian): int
    {
        return $bigEndian ? (($first << 8) | $second) : (($second << 8) | $first);
    }

    private static function writeUnit(int $unit, bool $bigEndian): string
    {
        return $bigEndian
            ? chr($unit >> 8) . chr($unit & 0x00FF)
            : chr($unit & 0x00FF) . chr($unit >> 8);
    }

    private static function isHighSurrogate(int $unit): bool
    {
        return $unit >= 0xD800 && $unit <= 0xDBFF;
    }

    private static function isLowSurrogate(int $unit): bool
    {
        return $unit >= 0xDC00 && $unit <= 0xDFFF;
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
