<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

final class Windows874
{
    private const string LABEL = 'Windows-874';
    private const string TABLE_KEY = 'windows_874';

    /**
     * Lexbor's lxb_encoding_single_index_windows_874 table for bytes 0x80-0xFF.
     *
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x20AC, 0x0081, 0x0082, 0x0083, 0x0084, 0x2026, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x0E01, 0x0E02, 0x0E03, 0x0E04, 0x0E05, 0x0E06, 0x0E07,
        0x0E08, 0x0E09, 0x0E0A, 0x0E0B, 0x0E0C, 0x0E0D, 0x0E0E, 0x0E0F,
        0x0E10, 0x0E11, 0x0E12, 0x0E13, 0x0E14, 0x0E15, 0x0E16, 0x0E17,
        0x0E18, 0x0E19, 0x0E1A, 0x0E1B, 0x0E1C, 0x0E1D, 0x0E1E, 0x0E1F,
        0x0E20, 0x0E21, 0x0E22, 0x0E23, 0x0E24, 0x0E25, 0x0E26, 0x0E27,
        0x0E28, 0x0E29, 0x0E2A, 0x0E2B, 0x0E2C, 0x0E2D, 0x0E2E, 0x0E2F,
        0x0E30, 0x0E31, 0x0E32, 0x0E33, 0x0E34, 0x0E35, 0x0E36, 0x0E37,
        0x0E38, 0x0E39, 0x0E3A, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x0E3F,
        0x0E40, 0x0E41, 0x0E42, 0x0E43, 0x0E44, 0x0E45, 0x0E46, 0x0E47,
        0x0E48, 0x0E49, 0x0E4A, 0x0E4B, 0x0E4C, 0x0E4D, 0x0E4E, 0x0E4F,
        0x0E50, 0x0E51, 0x0E52, 0x0E53, 0x0E54, 0x0E55, 0x0E56, 0x0E57,
        0x0E58, 0x0E59, 0x0E5A, 0x0E5B, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
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
