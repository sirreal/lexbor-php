<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Utf8
{
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

    private static function requireBytes(string $data, int $offset, int $needed): void
    {
        if (strlen($data) - $offset < $needed) {
            self::unexpected('Truncated UTF-8 sequence.');
        }
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}
