<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Gb18030
{
    private const string LABEL = 'GB18030';
    private const int TWO_BYTE_TRAIL_COUNT = 190;
    private const int FOUR_BYTE_SECOND_COUNT = 10;
    private const int FOUR_BYTE_THIRD_COUNT = 126;
    private const int FOUR_BYTE_POINTER_MAX = 1237575;

    private function __construct()
    {
    }

    /**
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data): array
    {
        $result = self::decodeToBuffer($data, strlen($data) + 4);
        $codePoints = $result->codePoints;

        if ($result->status === Status::Continue) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }

    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingLeadByte = null,
        ?int $pendingGb18030Second = null,
        ?int $pendingGb18030Third = null,
        bool $pendingGb18030Prepend = false,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $first = $pendingLeadByte;
        $second = $pendingGb18030Second;
        $third = $pendingGb18030Third;
        $prepend = $pendingGb18030Prepend;

        while (true) {
            if ($first !== null) {
                if ($offset >= $length) {
                    return self::decodeResult(
                        Status::Continue,
                        $codePoints,
                        $offset,
                        $first,
                        $second,
                        $third,
                        $prepend,
                    );
                }

                if (count($codePoints) >= $capacity) {
                    return self::decodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        $first,
                        $second,
                        $third,
                        $prepend,
                    );
                }

                if ($third !== null) {
                    $savedFirst = $first;
                    $savedSecond = self::requirePendingByte($second);
                    $savedThird = $third;
                    $first = null;
                    $second = null;
                    $third = null;

                    if ($prepend) {
                        $codePoints[] = $savedSecond;

                        if (count($codePoints) >= $capacity) {
                            return self::decodeResult(
                                Status::SmallBuffer,
                                $codePoints,
                                $offset,
                                $savedThird,
                                null,
                                null,
                                true,
                            );
                        }

                        $first = $savedThird;
                        $prepend = false;
                        goto prepend_first;
                    }

                    $first = $savedFirst;
                    $second = $savedSecond;
                    $third = $savedThird;
                    goto third_state;
                }

                if ($second !== null) {
                    $savedFirst = $first;
                    $savedSecond = $second;
                    $first = null;
                    $second = null;

                    $first = $savedFirst;
                    $second = $savedSecond;
                    goto second_state;
                }

                $savedFirst = $first;
                $first = null;

                if ($prepend) {
                    $prepend = false;
                    $first = $savedFirst;
                    goto prepend_first;
                }

                $first = $savedFirst;
                goto first_state;
            }

            if ($offset >= $length) {
                return new DecodeResult(Status::Ok, $codePoints, $offset);
            }

            if (count($codePoints) >= $capacity) {
                return new DecodeResult(Status::SmallBuffer, $codePoints, $offset);
            }

            $first = ord($data[$offset]);
            $offset++;

            prepend_first:
            if ($first < 0x80) {
                $codePoints[] = $first;
                $first = null;
                continue;
            }

            if ($first === 0x80) {
                $codePoints[] = 0x20AC;
                $first = null;
                continue;
            }

            if (! self::isLeadByte($first)) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                $first = null;
                continue;
            }

            if ($offset >= $length) {
                return self::decodeResult(Status::Continue, $codePoints, $offset, $first);
            }

            first_state:
            $second = ord($data[$offset]);
            $offset++;

            if (! self::isFourByteSecond($second)) {
                $pointer = self::twoBytePointer($first, $second);

                if ($pointer === null) {
                    if ($second < 0x80) {
                        $offset--;
                    }

                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    $first = null;
                    $second = null;
                    continue;
                }

                $codePoint = Gb18030Data::DECODE_INDEX[$pointer] ?? null;

                if ($codePoint === null) {
                    if ($second < 0x80) {
                        $offset--;
                    }

                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    $first = null;
                    $second = null;
                    continue;
                }

                $codePoints[] = $codePoint;
                $first = null;
                $second = null;
                continue;
            }

            if ($offset >= $length) {
                return self::decodeResult(Status::Continue, $codePoints, $offset, $first, $second);
            }

            second_state:
            $third = ord($data[$offset]);
            $offset++;

            if (! self::isFourByteThird($third)) {
                $offset--;
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if (count($codePoints) >= $capacity) {
                    return self::decodeResult(Status::SmallBuffer, $codePoints, $offset, $second, null, null, true);
                }

                $first = $second;
                $second = null;
                $third = null;
                goto prepend_first;
            }

            if ($offset >= $length) {
                return self::decodeResult(Status::Continue, $codePoints, $offset, $first, $second, $third);
            }

            third_state:
            $fourth = ord($data[$offset]);

            if (! self::isFourByteFourth($fourth)) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if (count($codePoints) >= $capacity) {
                    return self::decodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        0x01,
                        self::requirePendingByte($second),
                        self::requirePendingByte($third),
                        true,
                    );
                }

                $codePoints[] = self::requirePendingByte($second);

                if (count($codePoints) >= $capacity) {
                    return self::decodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        self::requirePendingByte($third),
                        null,
                        null,
                        true,
                    );
                }

                $first = self::requirePendingByte($third);
                $second = null;
                $third = null;
                $prepend = false;
                goto prepend_first;
            }

            $offset++;
            $pointer = self::fourBytePointer(
                self::requirePendingByte($first),
                self::requirePendingByte($second),
                self::requirePendingByte($third),
                $fourth,
            );
            $codePoint = self::decodeRangePointer($pointer);

            if ($codePoint === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
            } else {
                $codePoints[] = $codePoint;
            }

            $first = null;
            $second = null;
            $third = null;
        }
    }

    public static function encodeCodePoint(int $codePoint): string
    {
        if ($codePoint < 0 || $codePoint > 0x10FFFF) {
            self::unexpected('Code point cannot be encoded as ' . self::LABEL . '.');
        }

        if ($codePoint < 0x80) {
            return chr($codePoint);
        }

        if ($codePoint === 0xE5E5) {
            self::unexpected('Code point cannot be encoded as ' . self::LABEL . '.');
        }

        $pointer = self::encodePointer($codePoint);

        if ($pointer !== null) {
            return self::bytesForPointer($pointer);
        }

        return self::fourBytesForPointer(self::encodeRangePointer($codePoint));
    }

    public static function encodeCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        if ($capacity < 1) {
            self::unexpected(self::LABEL . ' encode buffer capacity must be positive.');
        }

        if ($codePoint < 0 || $codePoint > 0x10FFFF) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
        }

        if ($codePoint < 0x80) {
            return new EncodeResult(1, chr($codePoint));
        }

        if ($codePoint === 0xE5E5) {
            return new EncodeResult(Utf8::ENCODE_ERROR, '');
        }

        $pointer = self::encodePointer($codePoint);

        if ($pointer !== null) {
            if ($capacity < 2) {
                return new EncodeResult(Utf8::ENCODE_SMALL_BUFFER, '');
            }

            return new EncodeResult(2, self::bytesForPointer($pointer));
        }

        if ($capacity < 4) {
            return new EncodeResult(Utf8::ENCODE_SMALL_BUFFER, '');
        }

        return new EncodeResult(4, self::fourBytesForPointer(self::encodeRangePointer($codePoint)));
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
            if ($codePoint < 0 || $codePoint > 0x10FFFF) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            if ($codePoint < 0x80) {
                if (strlen($out) >= $capacity) {
                    return new BufferEncodeResult(Status::SmallBuffer, $out);
                }

                $out .= chr($codePoint);
                continue;
            }

            if ($codePoint === 0xE5E5) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            $pointer = self::encodePointer($codePoint);

            if ($pointer !== null) {
                if (strlen($out) + 2 > $capacity) {
                    return new BufferEncodeResult(Status::SmallBuffer, $out);
                }

                $out .= self::bytesForPointer($pointer);
                continue;
            }

            if (strlen($out) + 4 > $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= self::fourBytesForPointer(self::encodeRangePointer($codePoint));
        }

        return new BufferEncodeResult(Status::Ok, $out);
    }

    public static function encodePointer(int $codePoint): ?int
    {
        if ($codePoint < 0x80 || $codePoint === 0xE5E5) {
            return null;
        }

        return Gb18030Data::ENCODE_INDEX[$codePoint] ?? null;
    }

    public static function bytesForPointer(int $pointer): string
    {
        if ($pointer < 0 || $pointer >= count(Gb18030Data::DECODE_INDEX)) {
            self::unexpected(self::LABEL . ' two-byte pointer is out of range.');
        }

        $trail = $pointer % self::TWO_BYTE_TRAIL_COUNT;

        return chr(intdiv($pointer, self::TWO_BYTE_TRAIL_COUNT) + 0x81)
            . chr($trail < 0x3F ? $trail + 0x40 : $trail + 0x41);
    }

    public static function fourBytesForPointer(int $pointer): string
    {
        if ($pointer < 0 || $pointer > self::FOUR_BYTE_POINTER_MAX) {
            self::unexpected(self::LABEL . ' four-byte pointer is out of range.');
        }

        return chr(intdiv($pointer, 10 * self::FOUR_BYTE_THIRD_COUNT * self::FOUR_BYTE_SECOND_COUNT) + 0x81)
            . chr(intdiv($pointer % (10 * self::FOUR_BYTE_THIRD_COUNT * self::FOUR_BYTE_SECOND_COUNT), 10 * self::FOUR_BYTE_THIRD_COUNT) + 0x30)
            . chr(intdiv($pointer % (10 * self::FOUR_BYTE_THIRD_COUNT), 10) + 0x81)
            . chr($pointer % 10 + 0x30);
    }

    public static function decodeRangePointer(int $pointer): ?int
    {
        if (($pointer >= 39419 && $pointer < 189000) || $pointer > self::FOUR_BYTE_POINTER_MAX) {
            return null;
        }

        if ($pointer === 7457) {
            return 0xE7C7;
        }

        $range = self::rangeByPointer($pointer);
        if ($range === null) {
            return null;
        }

        return $range[1] + $pointer - $range[0];
    }

    public static function encodeRangePointer(int $codePoint): int
    {
        if ($codePoint === 0xE7C7) {
            return 7457;
        }

        $range = self::rangeByCodePoint($codePoint);
        if ($range === null) {
            self::unexpected('Code point cannot be encoded as ' . self::LABEL . '.');
        }

        return $range[0] + $codePoint - $range[1];
    }

    private static function twoBytePointer(int $lead, int $trail): ?int
    {
        if (! self::isLeadByte($lead)) {
            return null;
        }

        if ($trail >= 0x40 && $trail <= 0x7E) {
            return ($lead - 0x81) * self::TWO_BYTE_TRAIL_COUNT + ($trail - 0x40);
        }

        if ($trail >= 0x80 && $trail <= 0xFE) {
            return ($lead - 0x81) * self::TWO_BYTE_TRAIL_COUNT + ($trail - 0x41);
        }

        return null;
    }

    private static function fourBytePointer(int $first, int $second, int $third, int $fourth): int
    {
        return (($first - 0x81) * (10 * self::FOUR_BYTE_THIRD_COUNT * self::FOUR_BYTE_SECOND_COUNT))
            + (($second - 0x30) * (10 * self::FOUR_BYTE_THIRD_COUNT))
            + (($third - 0x81) * 10)
            + $fourth - 0x30;
    }

    /**
     * @return array{int, int}|null
     */
    private static function rangeByPointer(int $pointer): ?array
    {
        $left = 0;
        $right = count(Gb18030Data::RANGE_INDEX) - 1;
        $candidate = null;

        while ($left <= $right) {
            $mid = intdiv($left + $right, 2);
            $range = Gb18030Data::RANGE_INDEX[$mid];

            if ($range[0] <= $pointer) {
                $candidate = $range;
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }

        return $candidate;
    }

    /**
     * @return array{int, int}|null
     */
    private static function rangeByCodePoint(int $codePoint): ?array
    {
        $left = 0;
        $right = count(Gb18030Data::RANGE_INDEX) - 1;
        $candidate = null;

        while ($left <= $right) {
            $mid = intdiv($left + $right, 2);
            $range = Gb18030Data::RANGE_INDEX[$mid];

            if ($range[1] <= $codePoint) {
                $candidate = $range;
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }

        return $candidate;
    }

    private static function isLeadByte(int $byte): bool
    {
        return $byte >= 0x81 && $byte <= 0xFE;
    }

    private static function isFourByteSecond(int $byte): bool
    {
        return $byte >= 0x30 && $byte <= 0x39;
    }

    private static function isFourByteThird(int $byte): bool
    {
        return $byte >= 0x81 && $byte <= 0xFE;
    }

    private static function isFourByteFourth(int $byte): bool
    {
        return $byte >= 0x30 && $byte <= 0x39;
    }

    private static function requirePendingByte(?int $byte): int
    {
        if ($byte === null) {
            self::unexpected(self::LABEL . ' decoder state is incomplete.');
        }

        return $byte;
    }

    /**
     * @param list<int> $codePoints
     */
    private static function decodeResult(
        Status $status,
        array $codePoints,
        int $offset,
        ?int $first = null,
        ?int $second = null,
        ?int $third = null,
        bool $prepend = false,
    ): DecodeResult {
        return new DecodeResult(
            $status,
            $codePoints,
            $offset,
            pendingLeadByte: $first,
            pendingGb18030Second: $second,
            pendingGb18030Third: $third,
            pendingGb18030Prepend: $prepend,
        );
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
