<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

final class Iso885910
{
    private const string LABEL = 'ISO-8859-10';
    private const string TABLE_KEY = 'iso_8859_10';

    /**
     * Lexbor's lxb_encoding_single_index_iso_8859_10 table for bytes 0x80-0xFF.
     *
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x0091, 0x0092, 0x0093, 0x0094, 0x0095, 0x0096, 0x0097,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x0104, 0x0112, 0x0122, 0x012A, 0x0128, 0x0136, 0x00A7,
        0x013B, 0x0110, 0x0160, 0x0166, 0x017D, 0x00AD, 0x016A, 0x014A,
        0x00B0, 0x0105, 0x0113, 0x0123, 0x012B, 0x0129, 0x0137, 0x00B7,
        0x013C, 0x0111, 0x0161, 0x0167, 0x017E, 0x2015, 0x016B, 0x014B,
        0x0100, 0x00C1, 0x00C2, 0x00C3, 0x00C4, 0x00C5, 0x00C6, 0x012E,
        0x010C, 0x00C9, 0x0118, 0x00CB, 0x0116, 0x00CD, 0x00CE, 0x00CF,
        0x00D0, 0x0145, 0x014C, 0x00D3, 0x00D4, 0x00D5, 0x00D6, 0x0168,
        0x00D8, 0x0172, 0x00DA, 0x00DB, 0x00DC, 0x00DD, 0x00DE, 0x00DF,
        0x0101, 0x00E1, 0x00E2, 0x00E3, 0x00E4, 0x00E5, 0x00E6, 0x012F,
        0x010D, 0x00E9, 0x0119, 0x00EB, 0x0117, 0x00ED, 0x00EE, 0x00EF,
        0x00F0, 0x0146, 0x014D, 0x00F3, 0x00F4, 0x00F5, 0x00F6, 0x0169,
        0x00F8, 0x0173, 0x00FA, 0x00FB, 0x00FC, 0x00FD, 0x00FE, 0x0138,
    ];

    /**
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data): array
    {
        return SingleByteEncoding::decodeWithReplacement($data, self::DECODE_INDEX, self::LABEL);
    }

    public static function decodeToBuffer(string $data, int $capacity, int $offset = 0): DecodeResult
    {
        return SingleByteEncoding::decodeToBuffer($data, $capacity, $offset, self::DECODE_INDEX, self::LABEL);
    }

    public static function encodeCodePoint(int $codePoint): string
    {
        return SingleByteEncoding::encodeCodePoint($codePoint, self::DECODE_INDEX, self::LABEL, self::TABLE_KEY);
    }

    public static function encodeCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        return SingleByteEncoding::encodeCodePointWithCapacity(
            $codePoint,
            $capacity,
            self::DECODE_INDEX,
            self::LABEL,
            self::TABLE_KEY,
        );
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePointsToBuffer(array $codePoints, int $capacity): BufferEncodeResult
    {
        return SingleByteEncoding::encodeCodePointsToBuffer(
            $codePoints,
            $capacity,
            self::DECODE_INDEX,
            self::LABEL,
            self::TABLE_KEY,
        );
    }

    public static function decodeByte(int $byte): int
    {
        return SingleByteEncoding::decodeByte($byte, self::DECODE_INDEX, self::LABEL);
    }

    public static function encodeByte(int $codePoint): ?int
    {
        return SingleByteEncoding::encodeByte($codePoint, self::DECODE_INDEX, self::TABLE_KEY);
    }
}
