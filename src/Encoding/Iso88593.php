<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

final class Iso88593
{
    private const string LABEL = 'ISO-8859-3';
    private const string TABLE_KEY = 'iso_8859_3';

    /**
     * Lexbor's lxb_encoding_single_index_iso_8859_3 table for bytes 0x80-0xFF.
     *
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x0091, 0x0092, 0x0093, 0x0094, 0x0095, 0x0096, 0x0097,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x0126, 0x02D8, 0x00A3, 0x00A4, SingleByteEncoding::ERROR_CODE_POINT, 0x0124, 0x00A7,
        0x00A8, 0x0130, 0x015E, 0x011E, 0x0134, 0x00AD, SingleByteEncoding::ERROR_CODE_POINT, 0x017B,
        0x00B0, 0x0127, 0x00B2, 0x00B3, 0x00B4, 0x00B5, 0x0125, 0x00B7,
        0x00B8, 0x0131, 0x015F, 0x011F, 0x0135, 0x00BD, SingleByteEncoding::ERROR_CODE_POINT, 0x017C,
        0x00C0, 0x00C1, 0x00C2, SingleByteEncoding::ERROR_CODE_POINT, 0x00C4, 0x010A, 0x0108, 0x00C7,
        0x00C8, 0x00C9, 0x00CA, 0x00CB, 0x00CC, 0x00CD, 0x00CE, 0x00CF,
        SingleByteEncoding::ERROR_CODE_POINT, 0x00D1, 0x00D2, 0x00D3, 0x00D4, 0x0120, 0x00D6, 0x00D7,
        0x011C, 0x00D9, 0x00DA, 0x00DB, 0x00DC, 0x016C, 0x015C, 0x00DF,
        0x00E0, 0x00E1, 0x00E2, SingleByteEncoding::ERROR_CODE_POINT, 0x00E4, 0x010B, 0x0109, 0x00E7,
        0x00E8, 0x00E9, 0x00EA, 0x00EB, 0x00EC, 0x00ED, 0x00EE, 0x00EF,
        SingleByteEncoding::ERROR_CODE_POINT, 0x00F1, 0x00F2, 0x00F3, 0x00F4, 0x0121, 0x00F6, 0x00F7,
        0x011D, 0x00F9, 0x00FA, 0x00FB, 0x00FC, 0x016D, 0x015D, 0x02D9,
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
