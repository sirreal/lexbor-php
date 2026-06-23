<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Windows1252
{
    /**
     * Lexbor's lxb_encoding_single_index_windows_1252 table for bytes 0x80-0xFF.
     *
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x20AC, 0x0081, 0x201A, 0x0192, 0x201E, 0x2026, 0x2020, 0x2021,
        0x02C6, 0x2030, 0x0160, 0x2039, 0x0152, 0x008D, 0x017D, 0x008F,
        0x0090, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
        0x02DC, 0x2122, 0x0161, 0x203A, 0x0153, 0x009D, 0x017E, 0x0178,
        0x00A0, 0x00A1, 0x00A2, 0x00A3, 0x00A4, 0x00A5, 0x00A6, 0x00A7,
        0x00A8, 0x00A9, 0x00AA, 0x00AB, 0x00AC, 0x00AD, 0x00AE, 0x00AF,
        0x00B0, 0x00B1, 0x00B2, 0x00B3, 0x00B4, 0x00B5, 0x00B6, 0x00B7,
        0x00B8, 0x00B9, 0x00BA, 0x00BB, 0x00BC, 0x00BD, 0x00BE, 0x00BF,
        0x00C0, 0x00C1, 0x00C2, 0x00C3, 0x00C4, 0x00C5, 0x00C6, 0x00C7,
        0x00C8, 0x00C9, 0x00CA, 0x00CB, 0x00CC, 0x00CD, 0x00CE, 0x00CF,
        0x00D0, 0x00D1, 0x00D2, 0x00D3, 0x00D4, 0x00D5, 0x00D6, 0x00D7,
        0x00D8, 0x00D9, 0x00DA, 0x00DB, 0x00DC, 0x00DD, 0x00DE, 0x00DF,
        0x00E0, 0x00E1, 0x00E2, 0x00E3, 0x00E4, 0x00E5, 0x00E6, 0x00E7,
        0x00E8, 0x00E9, 0x00EA, 0x00EB, 0x00EC, 0x00ED, 0x00EE, 0x00EF,
        0x00F0, 0x00F1, 0x00F2, 0x00F3, 0x00F4, 0x00F5, 0x00F6, 0x00F7,
        0x00F8, 0x00F9, 0x00FA, 0x00FB, 0x00FC, 0x00FD, 0x00FE, 0x00FF,
    ];

    /**
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($i = 0; $i < $length; $i++) {
            $codePoints[] = self::decodeByte(ord($data[$i]));
        }

        return $codePoints;
    }

    public static function decodeToBuffer(string $data, int $capacity, int $offset = 0): DecodeResult
    {
        if ($capacity < 0) {
            self::unexpected('Windows-1252 decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];

        while ($offset < $length) {
            if (count($codePoints) >= $capacity) {
                return new DecodeResult(Status::SmallBuffer, $codePoints, $offset);
            }

            $codePoints[] = self::decodeByte(ord($data[$offset]));
            $offset++;
        }

        return new DecodeResult(Status::Ok, $codePoints, $offset);
    }

    public static function encodeCodePoint(int $codePoint): string
    {
        $byte = self::encodeByte($codePoint);

        if ($byte === null) {
            self::unexpected('Code point cannot be encoded as Windows-1252.');
        }

        return chr($byte);
    }

    public static function encodeCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        if ($capacity < 1) {
            self::unexpected('Windows-1252 encode buffer capacity must be positive.');
        }

        $byte = self::encodeByte($codePoint);

        if ($byte === null) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
        }

        return new EncodeResult(1, chr($byte));
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePointsToBuffer(array $codePoints, int $capacity): BufferEncodeResult
    {
        if ($capacity < 0) {
            self::unexpected('Windows-1252 encode buffer capacity cannot be negative.');
        }

        $out = '';

        foreach ($codePoints as $codePoint) {
            $byte = self::encodeByte($codePoint);

            if ($byte === null) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            if (strlen($out) >= $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= chr($byte);
        }

        return new BufferEncodeResult(Status::Ok, $out);
    }

    public static function decodeByte(int $byte): int
    {
        if ($byte < 0 || $byte > 0xFF) {
            self::unexpected('Windows-1252 byte must be in range 0x00-0xFF.');
        }

        if ($byte < 0x80) {
            return $byte;
        }

        return self::DECODE_INDEX[$byte - 0x80];
    }

    public static function encodeByte(int $codePoint): ?int
    {
        if ($codePoint < 0) {
            return null;
        }

        if ($codePoint < 0x80) {
            return $codePoint;
        }

        $index = self::encodeIndex();

        return $index[$codePoint] ?? null;
    }

    /**
     * @return array<int, int>
     */
    private static function encodeIndex(): array
    {
        static $index = null;

        if ($index === null) {
            $index = [];

            foreach (self::DECODE_INDEX as $offset => $codePoint) {
                $index[$codePoint] = $offset + 0x80;
            }
        }

        return $index;
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
