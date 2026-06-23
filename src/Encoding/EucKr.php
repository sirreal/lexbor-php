<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class EucKr
{
    private const string LABEL = 'EUC-KR';
    private const int LEAD_MIN = 0x81;
    private const int LEAD_MAX = 0xFE;
    private const int TRAIL_MIN = 0x41;
    private const int TRAIL_MAX = 0xFE;
    private const int TRAIL_COUNT = 190;
    private const int POINTER_MAX = (self::LEAD_MAX - self::LEAD_MIN) * self::TRAIL_COUNT
        + (self::TRAIL_MAX - self::TRAIL_MIN);

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
            $codePoint = self::decodePair($lead, $trail);

            if ($codePoint === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if ($trail >= 0x80) {
                    $offset++;
                }

                continue;
            }

            $offset++;
            $codePoints[] = $codePoint;
        }

        return $codePoints;
    }

    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingLeadByte = null,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $lead = $pendingLeadByte;

        while (true) {
            if ($lead !== null) {
                if ($offset >= $length) {
                    return new DecodeResult(Status::Continue, $codePoints, $offset, pendingLeadByte: $lead);
                }

                if (count($codePoints) >= $capacity) {
                    return new DecodeResult(Status::SmallBuffer, $codePoints, $offset, pendingLeadByte: $lead);
                }

                $trail = ord($data[$offset]);
                $codePoint = self::decodePair($lead, $trail);
                $lead = null;

                if ($codePoint === null) {
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                    if ($trail >= 0x80) {
                        $offset++;
                    }

                    continue;
                }

                $offset++;
                $codePoints[] = $codePoint;
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
            $codePoint = self::decodePair($byte, $trail);

            if ($codePoint === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if ($trail >= 0x80) {
                    $offset++;
                }

                continue;
            }

            $offset++;
            $codePoints[] = $codePoint;
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

        if ($capacity < 2) {
            return new EncodeResult(Utf8::ENCODE_SMALL_BUFFER, '');
        }

        $pointer = self::encodePointer($codePoint);

        if ($pointer === null) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
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

    public static function decodePointer(int $lead, int $trail): ?int
    {
        return self::decodePair($lead, $trail);
    }

    public static function encodePointer(int $codePoint): ?int
    {
        if ($codePoint < 0x80) {
            return null;
        }

        $index = self::encodeIndex();

        return $index[$codePoint] ?? null;
    }

    public static function bytesForPointer(int $pointer): string
    {
        if ($pointer < 0 || $pointer > self::POINTER_MAX) {
            self::unexpected(self::LABEL . ' pointer is out of range.');
        }

        return chr(intdiv($pointer, self::TRAIL_COUNT) + self::LEAD_MIN)
            . chr($pointer % self::TRAIL_COUNT + self::TRAIL_MIN);
    }

    private static function decodePair(int $lead, int $trail): ?int
    {
        if (! self::isLeadByte($lead) || ! self::isTrailByte($trail)) {
            return null;
        }

        $pointer = ($lead - self::LEAD_MIN) * self::TRAIL_COUNT + ($trail - self::TRAIL_MIN);

        return EucKrData::DECODE_INDEX[$pointer] ?? null;
    }

    private static function isLeadByte(int $byte): bool
    {
        return $byte >= self::LEAD_MIN && $byte <= self::LEAD_MAX;
    }

    private static function isTrailByte(int $byte): bool
    {
        return $byte >= self::TRAIL_MIN && $byte <= self::TRAIL_MAX;
    }

    /**
     * @return array<int, int>
     */
    private static function encodeIndex(): array
    {
        static $index = null;

        if ($index === null) {
            $index = [];

            foreach (EucKrData::DECODE_INDEX as $pointer => $codePoint) {
                $index[$codePoint] = $pointer;
            }
        }

        return $index;
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
