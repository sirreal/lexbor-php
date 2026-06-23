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

    /**
     * @return list<int>
     */
    public static function decodeLittleEndianWithReplacement(string $data): array
    {
        return self::decodeWithReplacement($data, false);
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

    public static function encodeLittleEndianCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        return self::encodeCodePointWithCapacity($codePoint, $capacity, false);
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

    private static function readUnit(string $data, int $offset, bool $bigEndian): int
    {
        $first = ord($data[$offset]);
        $second = ord($data[$offset + 1]);

        return $bigEndian
            ? (($first << 8) | $second)
            : (($second << 8) | $first);
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
