<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class SingleByteEncoding
{
    public const int ERROR_CODE_POINT = 0x1FFFFF;

    private function __construct()
    {
    }

    /**
     * @param list<int> $decodeIndex
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data, array $decodeIndex, string $label): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($i = 0; $i < $length; $i++) {
            $codePoint = self::decodeByte(ord($data[$i]), $decodeIndex, $label);
            $codePoints[] = $codePoint === self::ERROR_CODE_POINT ? Utf8::REPLACEMENT_CODE_POINT : $codePoint;
        }

        return $codePoints;
    }

    /**
     * @param list<int> $decodeIndex
     */
    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset,
        array $decodeIndex,
        string $label,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected("{$label} decode buffer capacity cannot be negative.");
        }

        $length = strlen($data);
        $codePoints = [];

        while ($offset < $length) {
            if (count($codePoints) >= $capacity) {
                return new DecodeResult(Status::SmallBuffer, $codePoints, $offset);
            }

            $codePoint = self::decodeByte(ord($data[$offset]), $decodeIndex, $label);
            $codePoints[] = $codePoint === self::ERROR_CODE_POINT ? Utf8::REPLACEMENT_CODE_POINT : $codePoint;
            $offset++;
        }

        return new DecodeResult(Status::Ok, $codePoints, $offset);
    }

    /**
     * @param list<int> $decodeIndex
     */
    public static function encodeCodePoint(int $codePoint, array $decodeIndex, string $label, string $tableKey): string
    {
        $byte = self::encodeByte($codePoint, $decodeIndex, $tableKey);

        if ($byte === null) {
            self::unexpected("Code point cannot be encoded as {$label}.");
        }

        return chr($byte);
    }

    /**
     * @param list<int> $decodeIndex
     */
    public static function encodeCodePointWithCapacity(
        int $codePoint,
        int $capacity,
        array $decodeIndex,
        string $label,
        string $tableKey,
    ): EncodeResult {
        if ($capacity < 1) {
            self::unexpected("{$label} encode buffer capacity must be positive.");
        }

        $byte = self::encodeByte($codePoint, $decodeIndex, $tableKey);

        if ($byte === null) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
        }

        return new EncodeResult(1, chr($byte));
    }

    /**
     * @param list<int> $codePoints
     * @param list<int> $decodeIndex
     */
    public static function encodeCodePointsToBuffer(
        array $codePoints,
        int $capacity,
        array $decodeIndex,
        string $label,
        string $tableKey,
    ): BufferEncodeResult {
        if ($capacity < 0) {
            self::unexpected("{$label} encode buffer capacity cannot be negative.");
        }

        $out = '';

        foreach ($codePoints as $codePoint) {
            $byte = self::encodeByte($codePoint, $decodeIndex, $tableKey);

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

    /**
     * @param list<int> $decodeIndex
     */
    public static function decodeByte(int $byte, array $decodeIndex, string $label): int
    {
        if ($byte < 0 || $byte > 0xFF) {
            self::unexpected("{$label} byte must be in range 0x00-0xFF.");
        }

        if ($byte < 0x80) {
            return $byte;
        }

        return $decodeIndex[$byte - 0x80];
    }

    /**
     * @param list<int> $decodeIndex
     */
    public static function encodeByte(int $codePoint, array $decodeIndex, string $tableKey): ?int
    {
        if ($codePoint < 0) {
            return null;
        }

        if ($codePoint < 0x80) {
            return $codePoint;
        }

        $index = self::encodeIndex($decodeIndex, $tableKey);

        return $index[$codePoint] ?? null;
    }

    /**
     * @param list<int> $decodeIndex
     * @return array<int, int>
     */
    private static function encodeIndex(array $decodeIndex, string $tableKey): array
    {
        static $indexes = [];

        if (! isset($indexes[$tableKey])) {
            $indexes[$tableKey] = [];

            foreach ($decodeIndex as $offset => $codePoint) {
                if ($codePoint === self::ERROR_CODE_POINT) {
                    continue;
                }

                $indexes[$tableKey][$codePoint] = $offset + 0x80;
            }
        }

        return $indexes[$tableKey];
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
