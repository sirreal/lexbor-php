<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Big5
{
    private const string LABEL = 'Big5';
    private const int LEAD_MIN = 0x81;
    private const int LEAD_MAX = 0xFE;
    private const int TRAIL_LOW_MIN = 0x40;
    private const int TRAIL_LOW_MAX = 0x7E;
    private const int TRAIL_HIGH_MIN = 0xA1;
    private const int TRAIL_HIGH_MAX = 0xFE;
    private const int TRAIL_COUNT = 157;
    private const int POINTER_MAX = (self::LEAD_MAX - self::LEAD_MIN) * self::TRAIL_COUNT
        + (self::TRAIL_HIGH_MAX - 0x62);

    private function __construct()
    {
    }

    /**
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($offset = 0; $offset < $length;) {
            $lead = ord($data[$offset]);
            $offset++;

            if ($lead < 0x80) {
                $codePoints[] = $lead;
                continue;
            }

            if (! self::isLeadByte($lead)) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                continue;
            }

            if ($offset >= $length) {
                $codePoints[] = Utf8::DECODE_CONTINUE;
                break;
            }

            $trail = ord($data[$offset]);
            $decoded = self::decodePair($lead, $trail);

            if ($decoded === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if ($trail >= 0x80) {
                    $offset++;
                }

                continue;
            }

            $offset++;
            array_push($codePoints, ...$decoded);
        }

        return $codePoints;
    }

    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingLeadByte = null,
        ?int $pendingFirstCodePoint = null,
        ?int $pendingSecondCodePoint = null,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $lead = $pendingLeadByte;

        while (true) {
            if ($pendingFirstCodePoint !== null && $pendingSecondCodePoint !== null) {
                if (count($codePoints) + 2 > $capacity) {
                    return new DecodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        pendingFirstCodePoint: $pendingFirstCodePoint,
                        pendingSecondCodePoint: $pendingSecondCodePoint,
                    );
                }

                $codePoints[] = $pendingFirstCodePoint;
                $codePoints[] = $pendingSecondCodePoint;
                $pendingFirstCodePoint = null;
                $pendingSecondCodePoint = null;
                continue;
            }

            if ($lead !== null) {
                if ($offset >= $length) {
                    return new DecodeResult(Status::Continue, $codePoints, $offset, pendingLeadByte: $lead);
                }

                if (count($codePoints) >= $capacity) {
                    return new DecodeResult(Status::SmallBuffer, $codePoints, $offset, pendingLeadByte: $lead);
                }

                $trail = ord($data[$offset]);
                $decoded = self::decodePair($lead, $trail);
                $lead = null;

                if ($decoded === null) {
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                    if ($trail >= 0x80) {
                        $offset++;
                    }

                    continue;
                }

                $offset++;

                if (count($decoded) === 2 && count($codePoints) + 2 > $capacity) {
                    return new DecodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        pendingFirstCodePoint: $decoded[0],
                        pendingSecondCodePoint: $decoded[1],
                    );
                }

                array_push($codePoints, ...$decoded);
                continue;
            }

            if ($offset >= $length) {
                return new DecodeResult(Status::Ok, $codePoints, $offset);
            }

            if (count($codePoints) >= $capacity) {
                return new DecodeResult(Status::SmallBuffer, $codePoints, $offset);
            }

            $byte = ord($data[$offset]);
            $offset++;

            if ($byte < 0x80) {
                $codePoints[] = $byte;
                continue;
            }

            if (! self::isLeadByte($byte)) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                continue;
            }

            if ($offset >= $length) {
                return new DecodeResult(Status::Continue, $codePoints, $offset, pendingLeadByte: $byte);
            }

            $trail = ord($data[$offset]);
            $decoded = self::decodePair($byte, $trail);

            if ($decoded === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if ($trail >= 0x80) {
                    $offset++;
                }

                continue;
            }

            $offset++;

            if (count($decoded) === 2 && count($codePoints) + 2 > $capacity) {
                return new DecodeResult(
                    Status::SmallBuffer,
                    $codePoints,
                    $offset,
                    pendingFirstCodePoint: $decoded[0],
                    pendingSecondCodePoint: $decoded[1],
                );
            }

            array_push($codePoints, ...$decoded);
        }
    }

    public static function encodeCodePoint(int $codePoint): string
    {
        if ($codePoint < 0) {
            self::unexpected('Code point cannot be encoded as ' . self::LABEL . '.');
        }

        if ($codePoint < 0x80) {
            return chr($codePoint);
        }

        $pointer = self::encodePointer($codePoint);

        if ($pointer === null) {
            self::unexpected('Code point cannot be encoded as ' . self::LABEL . '.');
        }

        return self::bytesForPointer($pointer);
    }

    public static function encodeCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        if ($capacity < 1) {
            self::unexpected(self::LABEL . ' encode buffer capacity must be positive.');
        }

        if ($codePoint < 0) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
        }

        if ($codePoint < 0x80) {
            return new EncodeResult(1, chr($codePoint));
        }

        $pointer = self::encodePointer($codePoint);

        if ($pointer === null) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
        }

        if ($capacity < 2) {
            return new EncodeResult(Utf8::ENCODE_SMALL_BUFFER, '');
        }

        return new EncodeResult(2, self::bytesForPointer($pointer));
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePointsToBuffer(array $codePoints, int $capacity): BufferEncodeResult
    {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' encode buffer capacity cannot be negative.');
        }

        $out = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint < 0) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            if ($codePoint < 0x80) {
                if (strlen($out) >= $capacity) {
                    return new BufferEncodeResult(Status::SmallBuffer, $out);
                }

                $out .= chr($codePoint);
                continue;
            }

            $pointer = self::encodePointer($codePoint);

            if ($pointer === null) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            if (strlen($out) + 2 > $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= self::bytesForPointer($pointer);
        }

        return new BufferEncodeResult(Status::Ok, $out);
    }

    /**
     * @return list<int>|null
     */
    public static function decodePointer(int $lead, int $trail): ?array
    {
        return self::decodePair($lead, $trail);
    }

    public static function encodePointer(int $codePoint): ?int
    {
        if ($codePoint < 0x80) {
            return null;
        }

        return Big5Data::ENCODE_INDEX[$codePoint] ?? null;
    }

    public static function bytesForPointer(int $pointer): string
    {
        if ($pointer < 0 || $pointer > self::POINTER_MAX) {
            self::unexpected(self::LABEL . ' pointer is out of range.');
        }

        $offset = $pointer % self::TRAIL_COUNT;
        $trail = $offset < 0x3F ? $offset + self::TRAIL_LOW_MIN : $offset + 0x62;

        return chr(intdiv($pointer, self::TRAIL_COUNT) + self::LEAD_MIN) . chr($trail);
    }

    /**
     * @return list<int>|null
     */
    private static function decodePair(int $lead, int $trail): ?array
    {
        $pointer = self::pointerForBytes($lead, $trail);

        if ($pointer === null) {
            return null;
        }

        return match ($pointer) {
            1133 => [0x00CA, 0x0304],
            1135 => [0x00CA, 0x030C],
            1164 => [0x00EA, 0x0304],
            1166 => [0x00EA, 0x030C],
            default => isset(Big5Data::DECODE_INDEX[$pointer]) ? [Big5Data::DECODE_INDEX[$pointer]] : null,
        };
    }

    private static function pointerForBytes(int $lead, int $trail): ?int
    {
        if (! self::isLeadByte($lead) || ! self::isTrailByte($trail)) {
            return null;
        }

        if ($trail < 0x7F) {
            return ($lead - self::LEAD_MIN) * self::TRAIL_COUNT + ($trail - self::TRAIL_LOW_MIN);
        }

        return ($lead - self::LEAD_MIN) * self::TRAIL_COUNT + ($trail - 0x62);
    }

    private static function isLeadByte(int $byte): bool
    {
        return $byte >= self::LEAD_MIN && $byte <= self::LEAD_MAX;
    }

    private static function isTrailByte(int $byte): bool
    {
        return ($byte >= self::TRAIL_LOW_MIN && $byte <= self::TRAIL_LOW_MAX)
            || ($byte >= self::TRAIL_HIGH_MIN && $byte <= self::TRAIL_HIGH_MAX);
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
