<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Utf8
{
    public const int REPLACEMENT_CODE_POINT = 0xFFFD;
    public const int DECODE_CONTINUE = 0x2FFFFF;
    public const int ENCODE_OK = 0x00;
    public const int ENCODE_ERROR = -0x01;
    public const int ENCODE_SMALL_BUFFER = -0x02;

    public static function skipUtf8Bom(string $data): string
    {
        return str_starts_with($data, "\xEF\xBB\xBF") ? substr($data, 3) : $data;
    }

    public static function skipUtf16BeBom(string $data): string
    {
        return str_starts_with($data, "\xFE\xFF") ? substr($data, 2) : $data;
    }

    public static function skipUtf16LeBom(string $data): string
    {
        return str_starts_with($data, "\xFF\xFE") ? substr($data, 2) : $data;
    }

    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        ?int $pendingCodePoint = null,
        int $pendingNeed = 0,
        int $pendingLower = 0,
        int $pendingUpper = 0,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected('UTF-8 decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $codePoint = $pendingCodePoint ?? 0;
        $need = $pendingNeed;
        $lower = $pendingLower;
        $upper = $pendingUpper;

        while (true) {
            if ($offset >= $length) {
                if ($need !== 0) {
                    return new DecodeResult(
                        Status::Continue,
                        $codePoints,
                        $offset,
                        pendingUtf8CodePoint: $codePoint,
                        pendingUtf8Need: $need,
                        pendingUtf8Lower: $lower,
                        pendingUtf8Upper: $upper,
                    );
                }

                break;
            }

            if (count($codePoints) >= $capacity) {
                return new DecodeResult(
                    Status::SmallBuffer,
                    $codePoints,
                    $offset,
                    pendingUtf8CodePoint: $need === 0 ? null : $codePoint,
                    pendingUtf8Need: $need,
                    pendingUtf8Lower: $lower,
                    pendingUtf8Upper: $upper,
                );
            }

            if ($need !== 0) {
                $byte = ord($data[$offset]);

                if ($lower !== 0 && ($byte < $lower || $byte > $upper)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $codePoint = 0;
                    $need = 0;
                    $lower = 0;
                    $upper = 0;
                    continue;
                }

                $lower = 0;
                $upper = 0;

                if (! self::isContinuationByte($byte)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $codePoint = 0;
                    $need = 0;
                    continue;
                }

                $offset++;
                $codePoint = ($codePoint << 6) | ($byte & 0x3F);
                $need--;

                if ($need === 0) {
                    $codePoints[] = $codePoint;
                    $codePoint = 0;
                }

                continue;
            }

            $byte = ord($data[$offset]);
            $offset++;

            if ($byte < 0x80) {
                $codePoints[] = $byte;
                continue;
            }

            if ($byte <= 0xDF) {
                if ($byte < 0xC2) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    continue;
                }

                $codePoint = $byte & 0x1F;
                $need = 1;
                continue;
            }

            if ($byte < 0xF0) {
                $codePoint = $byte & 0x0F;
                $need = 2;

                if ($byte === 0xE0) {
                    $lower = 0xA0;
                    $upper = 0xBF;
                } elseif ($byte === 0xED) {
                    $lower = 0x80;
                    $upper = 0x9F;
                }

                continue;
            }

            if ($byte < 0xF5) {
                $codePoint = $byte & 0x07;
                $need = 3;

                if ($byte === 0xF0) {
                    $lower = 0x90;
                    $upper = 0xBF;
                } elseif ($byte === 0xF4) {
                    $lower = 0x80;
                    $upper = 0x8F;
                }

                continue;
            }

            $codePoints[] = self::REPLACEMENT_CODE_POINT;
        }

        return new DecodeResult(Status::Ok, $codePoints, $offset);
    }

    /**
     * Decodes with Lexbor's UTF-8 single-decoder error handling.
     *
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($i = 0; $i < $length;) {
            $first = ord($data[$i]);

            if ($first <= 0x7F) {
                $codePoints[] = $first;
                $i++;
                continue;
            }

            if ($first >= 0xC2 && $first <= 0xDF) {
                if ($i + 1 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $second = ord($data[$i + 1]);

                if (! self::isContinuationByte($second)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $i++;
                    continue;
                }

                $codePoints[] = (($first & 0x1F) << 6) | ($second & 0x3F);
                $i += 2;
                continue;
            }

            if ($first >= 0xE0 && $first <= 0xEF) {
                if ($i + 1 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $second = ord($data[$i + 1]);

                if (! self::isValidThreeByteSecond($first, $second)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $i++;
                    continue;
                }

                if ($i + 2 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $third = ord($data[$i + 2]);

                if (! self::isContinuationByte($third)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $i += 2;
                    continue;
                }

                $codePoints[] = (($first & 0x0F) << 12)
                    | (($second & 0x3F) << 6)
                    | ($third & 0x3F);
                $i += 3;
                continue;
            }

            if ($first >= 0xF0 && $first <= 0xF4) {
                if ($i + 1 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $second = ord($data[$i + 1]);

                if (! self::isValidFourByteSecond($first, $second)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $i++;
                    continue;
                }

                if ($i + 2 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $third = ord($data[$i + 2]);

                if (! self::isContinuationByte($third)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $i += 2;
                    continue;
                }

                if ($i + 3 >= $length) {
                    $codePoints[] = self::DECODE_CONTINUE;
                    break;
                }

                $fourth = ord($data[$i + 3]);

                if (! self::isContinuationByte($fourth)) {
                    $codePoints[] = self::REPLACEMENT_CODE_POINT;
                    $i += 3;
                    continue;
                }

                $codePoints[] = (($first & 0x07) << 18)
                    | (($second & 0x3F) << 12)
                    | (($third & 0x3F) << 6)
                    | ($fourth & 0x3F);
                $i += 4;
                continue;
            }

            $codePoints[] = self::REPLACEMENT_CODE_POINT;
            $i++;
        }

        return $codePoints;
    }

    /**
     * Decodes with Lexbor's lxb_encoding_decode_valid_utf_8_single semantics.
     *
     * This is intentionally byte-shape based: continuation-byte class,
     * overlong, surrogate, and Unicode-range checks are not performed here.
     *
     * @return list<int>
     */
    public static function decode(string $data): array
    {
        $length = strlen($data);
        $codePoints = [];

        for ($i = 0; $i < $length;) {
            $first = ord($data[$i]);

            if ($first < 0x80) {
                $codePoints[] = $first;
                $i++;
                continue;
            }

            if (($first & 0xE0) === 0xC0) {
                self::requireBytes($data, $i, 2);
                $second = ord($data[$i + 1]);

                $codePoints[] = (($first ^ (0xC0 & $first)) << 6)
                    | ($second ^ (0x80 & $second));
                $i += 2;
                continue;
            }

            if (($first & 0xF0) === 0xE0) {
                self::requireBytes($data, $i, 3);
                $second = ord($data[$i + 1]);
                $third = ord($data[$i + 2]);

                $codePoints[] = (($first ^ (0xE0 & $first)) << 12)
                    | (($second ^ (0x80 & $second)) << 6)
                    | ($third ^ (0x80 & $third));
                $i += 3;
                continue;
            }

            if (($first & 0xF8) === 0xF0) {
                self::requireBytes($data, $i, 4);
                $second = ord($data[$i + 1]);
                $third = ord($data[$i + 2]);
                $fourth = ord($data[$i + 3]);

                $codePoints[] = (($first ^ (0xF0 & $first)) << 18)
                    | (($second ^ (0x80 & $second)) << 12)
                    | (($third ^ (0x80 & $third)) << 6)
                    | ($fourth ^ (0x80 & $fourth));
                $i += 4;
                continue;
            }

            self::unexpected('Invalid UTF-8 leading byte.');
        }

        return $codePoints;
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePoints(array $codePoints): string
    {
        $out = '';

        foreach ($codePoints as $codePoint) {
            $out .= self::encodeCodePoint($codePoint);
        }

        return $out;
    }

    public static function encodeCodePoint(int $codePoint): string
    {
        if ($codePoint < 0 || $codePoint >= 0x110000) {
            self::unexpected('Code point cannot be encoded as UTF-8.');
        }

        if ($codePoint < 0x80) {
            return chr($codePoint);
        }

        if ($codePoint < 0x800) {
            return chr(0xC0 | ($codePoint >> 6))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        if ($codePoint < 0x10000) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }

        return chr(0xF0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }

    public static function encodeCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        if ($capacity < 1) {
            self::unexpected('UTF-8 encode buffer capacity must be positive.');
        }

        if ($codePoint < 0 || $codePoint >= 0x110000) {
            return new EncodeResult(self::ENCODE_ERROR, '');
        }

        $bytes = self::encodeCodePoint($codePoint);

        if (strlen($bytes) > $capacity) {
            return new EncodeResult(self::ENCODE_SMALL_BUFFER, '');
        }

        return new EncodeResult(strlen($bytes), $bytes);
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePointsToBuffer(array $codePoints, int $capacity): BufferEncodeResult
    {
        if ($capacity < 0) {
            self::unexpected('UTF-8 encode buffer capacity cannot be negative.');
        }

        $out = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint < 0 || $codePoint >= 0x110000) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            $bytes = self::encodeCodePoint($codePoint);

            if (strlen($out) + strlen($bytes) > $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= $bytes;
        }

        return new BufferEncodeResult(Status::Ok, $out);
    }

    private static function requireBytes(string $data, int $offset, int $needed): void
    {
        if (strlen($data) - $offset < $needed) {
            self::unexpected('Truncated UTF-8 sequence.');
        }
    }

    private static function isContinuationByte(int $byte): bool
    {
        return $byte >= 0x80 && $byte <= 0xBF;
    }

    private static function isValidThreeByteSecond(int $first, int $second): bool
    {
        return match ($first) {
            0xE0 => $second >= 0xA0 && $second <= 0xBF,
            0xED => $second >= 0x80 && $second <= 0x9F,
            default => self::isContinuationByte($second),
        };
    }

    private static function isValidFourByteSecond(int $first, int $second): bool
    {
        return match ($first) {
            0xF0 => $second >= 0x90 && $second <= 0xBF,
            0xF4 => $second >= 0x80 && $second <= 0x8F,
            default => self::isContinuationByte($second),
        };
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
