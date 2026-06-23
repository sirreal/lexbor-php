<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class EucJp
{
    private const string LABEL = 'EUC-JP';
    private const int TRAIL_COUNT = 94;
    private const int JIS0208_LENGTH = 11104;
    private const int JIS0212_LENGTH = 7211;
    private const int POINTER_MAX = (0xFE - 0xA1) * self::TRAIL_COUNT + (0xFE - 0xA1);

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

            $byte = ord($data[$offset]);
            $offset++;

            $result = self::decodeLeadState($lead, $byte, $data, $offset, $length);

            if ($result['continue']) {
                $codePoints[] = Utf8::DECODE_CONTINUE;
                break;
            }

            if ($result['codePoint'] === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if ($result['rewind']) {
                    $offset--;
                }

                continue;
            }

            $codePoints[] = $result['codePoint'];
        }

        return $codePoints;
    }

    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingLeadByte = null,
        bool $pendingJis0212 = false,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $lead = $pendingLeadByte;
        $isJis0212 = $pendingJis0212;

        while (true) {
            if ($lead !== null) {
                if ($offset >= $length) {
                    return new DecodeResult(
                        Status::Continue,
                        $codePoints,
                        $offset,
                        pendingLeadByte: $lead,
                        pendingJis0212: $isJis0212,
                    );
                }

                if (count($codePoints) >= $capacity) {
                    return new DecodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        pendingLeadByte: $lead,
                        pendingJis0212: $isJis0212,
                    );
                }

                $byte = ord($data[$offset]);
                $offset++;

                $result = $isJis0212
                    ? [
                        'codePoint' => self::decodeJisPair($lead, $byte, true),
                        'rewind' => $byte < 0x80,
                        'continue' => false,
                    ]
                    : self::decodeLeadState($lead, $byte, $data, $offset, $length);

                $lead = null;
                $isJis0212 = false;

                if (is_array($result) && ($result['continue'] ?? false)) {
                    return new DecodeResult(
                        Status::Continue,
                        $codePoints,
                        $offset,
                        pendingLeadByte: $result['pendingLead'],
                        pendingJis0212: true,
                    );
                }

                $codePoint = is_array($result) ? $result['codePoint'] : $result;
                $rewind = is_array($result) ? $result['rewind'] : false;

                if ($codePoint === null) {
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                    if ($rewind) {
                        $offset--;
                    }

                    continue;
                }

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
            $offset++;

            $result = self::decodeLeadState($byte, $trail, $data, $offset, $length);

            if ($result['continue']) {
                return new DecodeResult(
                    Status::Continue,
                    $codePoints,
                    $offset,
                    pendingLeadByte: $result['pendingLead'],
                    pendingJis0212: true,
                );
            }

            if ($result['codePoint'] === null) {
                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                if ($result['rewind']) {
                    $offset--;
                }

                continue;
            }

            $codePoints[] = $result['codePoint'];
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

        if ($codePoint >= 0xFF61 && $codePoint <= 0xFF9F) {
            return chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1);
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

        if ($capacity < 2) {
            return new EncodeResult(Utf8::ENCODE_SMALL_BUFFER, '');
        }

        if ($codePoint >= 0xFF61 && $codePoint <= 0xFF9F) {
            return new EncodeResult(2, chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1));
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

            $singleByte = self::encodeSingleByte($codePoint);
            if ($singleByte !== null) {
                if (strlen($out) >= $capacity) {
                    return new BufferEncodeResult(Status::SmallBuffer, $out);
                }

                $out .= chr($singleByte);
                continue;
            }

            if ($codePoint >= 0xFF61 && $codePoint <= 0xFF9F) {
                if (strlen($out) + 2 > $capacity) {
                    return new BufferEncodeResult(Status::SmallBuffer, $out);
                }

                $out .= chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1);
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

    public static function encodePointer(int $codePoint): ?int
    {
        if ($codePoint === 0x2212) {
            $codePoint = 0xFF0D;
        }

        return EucJpData::ENCODE_INDEX[$codePoint] ?? null;
    }

    public static function bytesForPointer(int $pointer): string
    {
        if ($pointer < 0 || $pointer > self::POINTER_MAX) {
            self::unexpected(self::LABEL . ' pointer is out of range.');
        }

        return chr(intdiv($pointer, self::TRAIL_COUNT) + 0xA1)
            . chr($pointer % self::TRAIL_COUNT + 0xA1);
    }

    public static function jis0212BytesForPointer(int $pointer): string
    {
        return chr(0x8F) . self::bytesForPointer($pointer);
    }

    /**
     * @return array{codePoint: ?int, rewind: bool, continue: bool, pendingLead?: int}
     */
    private static function decodeLeadState(
        int $lead,
        int $byte,
        string $data,
        int &$offset,
        int $length,
    ): array {
        if ($lead === 0x8E && $byte >= 0xA1 && $byte <= 0xDF) {
            return ['codePoint' => 0xFF61 - 0xA1 + $byte, 'rewind' => false, 'continue' => false];
        }

        $isJis0212 = false;

        if ($lead === 0x8F && $byte >= 0xA1 && $byte <= 0xFE) {
            if ($offset >= $length) {
                return ['codePoint' => null, 'rewind' => false, 'continue' => true, 'pendingLead' => $byte];
            }

            $lead = $byte;
            $byte = ord($data[$offset]);
            $offset++;
            $isJis0212 = true;
        }

        $codePoint = self::decodeJisPair($lead, $byte, $isJis0212);

        return [
            'codePoint' => $codePoint,
            'rewind' => $codePoint === null && $byte < 0x80,
            'continue' => false,
        ];
    }

    private static function decodeJisPair(int $lead, int $byte, bool $isJis0212): ?int
    {
        if ($lead < 0xA1 || $lead > 0xFE || $byte < 0xA1 || $byte > 0xFE) {
            return null;
        }

        $pointer = ($lead - 0xA1) * self::TRAIL_COUNT + $byte - 0xA1;

        if ($isJis0212) {
            if ($pointer >= self::JIS0212_LENGTH) {
                return null;
            }

            return EucJpData::JIS0212_DECODE_INDEX[$pointer] ?? null;
        }

        if ($pointer >= self::JIS0208_LENGTH) {
            return null;
        }

        return EucJpData::JIS0208_DECODE_INDEX[$pointer] ?? null;
    }

    private static function encodeSingleByte(int $codePoint): ?int
    {
        if ($codePoint < 0x80) {
            return $codePoint;
        }

        return match ($codePoint) {
            0x00A5 => 0x5C,
            0x203E => 0x7E,
            default => null,
        };
    }

    private static function isLeadByte(int $byte): bool
    {
        return ($byte >= 0xA1 && $byte <= 0xFE) || $byte === 0x8E || $byte === 0x8F;
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
