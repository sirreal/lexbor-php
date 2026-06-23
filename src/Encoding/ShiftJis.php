<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class ShiftJis
{
    private const string LABEL = 'Shift_JIS';
    private const int TRAIL_COUNT = 188;
    private const int JIS0208_LENGTH = 11104;
    private const int POINTER_MAX = (0xFC - 0xC1) * self::TRAIL_COUNT + (0xFC - 0x41);

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

            if ($lead <= 0x80) {
                $codePoints[] = $lead;
                continue;
            }

            if (self::isHalfWidthLead($lead)) {
                $codePoints[] = 0xFF61 - 0xA1 + $lead;
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

            if ($byte <= 0x80) {
                $codePoints[] = $byte;
                continue;
            }

            if (self::isHalfWidthLead($byte)) {
                $codePoints[] = 0xFF61 - 0xA1 + $byte;
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

        $singleByte = self::encodeSingleByte($codePoint);
        if ($singleByte !== null) {
            return chr($singleByte);
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

        $singleByte = self::encodeSingleByte($codePoint);
        if ($singleByte !== null) {
            return new EncodeResult(1, chr($singleByte));
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

            $singleByte = self::encodeSingleByte($codePoint);
            if ($singleByte !== null) {
                if (strlen($out) >= $capacity) {
                    return new BufferEncodeResult(Status::SmallBuffer, $out);
                }

                $out .= chr($singleByte);
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
        if ($codePoint === 0x2212) {
            $codePoint = 0xFF0D;
        }

        return ShiftJisData::ENCODE_INDEX[$codePoint] ?? null;
    }

    public static function bytesForPointer(int $pointer): string
    {
        if ($pointer < 0 || $pointer > self::POINTER_MAX) {
            self::unexpected(self::LABEL . ' pointer is out of range.');
        }

        $lead = intdiv($pointer, self::TRAIL_COUNT);
        $trail = $pointer % self::TRAIL_COUNT;

        return chr($lead + ($lead < 0x1F ? 0x81 : 0xC1))
            . chr($trail + ($trail < 0x3F ? 0x40 : 0x41));
    }

    private static function decodePair(int $lead, int $trail): ?int
    {
        $pointer = self::pointerForBytes($lead, $trail);

        if ($pointer === null || $pointer >= self::JIS0208_LENGTH) {
            return null;
        }

        if ($pointer >= 8836 && $pointer <= 10715) {
            return 0xE000 - 8836 + $pointer;
        }

        return ShiftJisData::JIS0208_DECODE_INDEX[$pointer] ?? null;
    }

    private static function pointerForBytes(int $lead, int $trail): ?int
    {
        if (! self::isLeadByte($lead) || ! self::isTrailByte($trail)) {
            return null;
        }

        $trailOffset = $trail < 0x7F ? 0x40 : 0x41;
        $leadOffset = $lead < 0xA0 ? 0x81 : 0xC1;

        return ($lead - $leadOffset) * self::TRAIL_COUNT + ($trail - $trailOffset);
    }

    private static function encodeSingleByte(int $codePoint): ?int
    {
        if ($codePoint <= 0x80) {
            return $codePoint;
        }

        if ($codePoint >= 0xFF61 && $codePoint <= 0xFF9F) {
            return $codePoint - 0xFF61 + 0xA1;
        }

        return match ($codePoint) {
            0x00A5 => 0x5C,
            0x203E => 0x7E,
            default => null,
        };
    }

    private static function isLeadByte(int $byte): bool
    {
        return ($byte >= 0x81 && $byte <= 0x9F) || $byte === 0xE0 || $byte === 0xFC;
    }

    private static function isTrailByte(int $byte): bool
    {
        return ($byte >= 0x40 && $byte <= 0x7E) || ($byte >= 0x80 && $byte <= 0xFC);
    }

    private static function isHalfWidthLead(int $byte): bool
    {
        return $byte >= 0xA1 && $byte <= 0xDF;
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
