<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

final class Iso88598
{
    private const string LABEL = 'ISO-8859-8';
    private const string TABLE_KEY = 'iso_8859_8';

    /**
     * Lexbor's lxb_encoding_single_index_iso_8859_8 table for bytes 0x80-0xFF.
     *
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x0091, 0x0092, 0x0093, 0x0094, 0x0095, 0x0096, 0x0097,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, SingleByteEncoding::ERROR_CODE_POINT, 0x00A2, 0x00A3, 0x00A4, 0x00A5, 0x00A6, 0x00A7,
        0x00A8, 0x00A9, 0x00D7, 0x00AB, 0x00AC, 0x00AD, 0x00AE, 0x00AF,
        0x00B0, 0x00B1, 0x00B2, 0x00B3, 0x00B4, 0x00B5, 0x00B6, 0x00B7,
        0x00B8, 0x00B9, 0x00F7, 0x00BB, 0x00BC, 0x00BD, 0x00BE, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x2017,
        0x05D0, 0x05D1, 0x05D2, 0x05D3, 0x05D4, 0x05D5, 0x05D6, 0x05D7,
        0x05D8, 0x05D9, 0x05DA, 0x05DB, 0x05DC, 0x05DD, 0x05DE, 0x05DF,
        0x05E0, 0x05E1, 0x05E2, 0x05E3, 0x05E4, 0x05E5, 0x05E6, 0x05E7,
        0x05E8, 0x05E9, 0x05EA, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x200E, 0x200F, SingleByteEncoding::ERROR_CODE_POINT,
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
