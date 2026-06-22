<?php

declare(strict_types=1);

namespace Lexbor\Punycode;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Utf8;

final class Punycode
{
    private const BASE = 36;
    private const TMIN = 1;
    private const TMAX = 26;
    private const SKEW = 38;
    private const DAMP = 700;
    private const INITIAL_BIAS = 72;
    private const INITIAL_N = 0x80;
    private const DELIMITER = 0x2D;
    private const UINT32_MAX = 0xFFFFFFFF;

    public static function encode(string $data): string
    {
        return self::encodeResult($data)->data;
    }

    public static function encodeResult(string $data): EncodeResult
    {
        return self::encodeCodePointsResult(Utf8::decode($data));
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePoints(array $codePoints): string
    {
        return self::encodeCodePointsResult($codePoints)->data;
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePointsResult(array $codePoints): EncodeResult
    {
        $output = '';

        foreach ($codePoints as $codePoint) {
            self::assertUnicodeCodePoint($codePoint);

            if ($codePoint < 0x80) {
                $output .= chr($codePoint);
            }
        }

        $basicCount = strlen($output);
        $handledCount = $basicCount;
        $inputLength = count($codePoints);

        if ($handledCount >= $inputLength) {
            return new EncodeResult($output, true);
        }

        if ($basicCount > 0) {
            $output .= chr(self::DELIMITER);
        }

        $n = self::INITIAL_N;
        $delta = 0;
        $bias = self::INITIAL_BIAS;

        while ($handledCount < $inputLength) {
            $m = self::UINT32_MAX;

            foreach ($codePoints as $codePoint) {
                if ($codePoint >= $n && $codePoint < $m) {
                    $m = $codePoint;
                }
            }

            if ($m === self::UINT32_MAX) {
                self::overflow('No remaining code point can be encoded.');
            }

            $pointsPlusOne = $handledCount + 1;
            if ($m - $n > intdiv(self::UINT32_MAX - $delta, $pointsPlusOne)) {
                self::overflow('Punycode encode delta overflow.');
            }

            $delta += ($m - $n) * $pointsPlusOne;
            $n = $m;

            foreach ($codePoints as $codePoint) {
                if ($codePoint < $n) {
                    if ($delta >= self::UINT32_MAX) {
                        self::overflow('Punycode encode delta overflow.');
                    }

                    $delta++;
                }

                if ($codePoint !== $n) {
                    continue;
                }

                $q = $delta;
                for ($k = self::BASE; ; $k += self::BASE) {
                    $t = self::threshold($k, $bias);

                    if ($q < $t) {
                        break;
                    }

                    $output .= self::encodeDigit($t + (($q - $t) % (self::BASE - $t)));
                    $q = intdiv($q - $t, self::BASE - $t);
                }

                $output .= self::encodeDigit($q);
                $bias = self::adapt($delta, $handledCount + 1, $handledCount === $basicCount);
                $delta = 0;
                $handledCount++;
            }

            if ($delta >= self::UINT32_MAX || $n >= self::UINT32_MAX) {
                self::overflow('Punycode encode state overflow.');
            }

            $delta++;
            $n++;
        }

        return new EncodeResult($output, false);
    }

    public static function decode(string $data): string
    {
        return Utf8::encodeCodePoints(self::decodeToCodePoints($data));
    }

    /**
     * @return list<int>
     */
    public static function decodeToCodePoints(string $data): array
    {
        $length = strlen($data);
        $delimiter = strrpos($data, chr(self::DELIMITER));
        $hasDelimiter = $delimiter !== false && $delimiter > 0;
        $basicEnd = $hasDelimiter ? $delimiter : 0;
        $output = [];

        for ($offset = 0; $offset < $basicEnd; $offset++) {
            $codePoint = ord($data[$offset]);

            if ($codePoint >= 0x80) {
                self::unexpected('Non-ASCII byte in Punycode basic prefix.');
            }

            $output[] = $codePoint;
        }

        $i = 0;
        $n = self::INITIAL_N;
        $bias = self::INITIAL_BIAS;
        $inputOffset = $hasDelimiter ? $delimiter + 1 : 0;

        while ($inputOffset < $length) {
            $oldI = $i;
            $w = 1;

            for ($k = self::BASE; ; $k += self::BASE) {
                if ($inputOffset >= $length) {
                    self::unexpected('Truncated Punycode digit sequence.');
                }

                $digit = self::decodeDigit(ord($data[$inputOffset]));
                $inputOffset++;

                if ($digit >= self::BASE) {
                    self::unexpected('Invalid Punycode digit.');
                }

                if ($digit > intdiv(self::UINT32_MAX - $i, $w)) {
                    self::overflow('Punycode decode index overflow.');
                }

                $i += $digit * $w;
                $t = self::threshold($k, $bias);

                if ($digit < $t) {
                    break;
                }

                if ($w > intdiv(self::UINT32_MAX, self::BASE - $t)) {
                    self::overflow('Punycode decode weight overflow.');
                }

                $w *= self::BASE - $t;
            }

            $handledPlusOne = count($output) + 1;
            $bias = self::adapt($i - $oldI, $handledPlusOne, $oldI === 0);

            if (intdiv($i, $handledPlusOne) > self::UINT32_MAX - $n) {
                self::overflow('Punycode decode code point overflow.');
            }

            $n += intdiv($i, $handledPlusOne);
            $i %= $handledPlusOne;

            array_splice($output, $i, 0, [$n]);
            $i++;
        }

        return array_values($output);
    }

    private static function encodeDigit(int $digit): string
    {
        return chr($digit + 22 + ($digit < 26 ? 75 : 0));
    }

    private static function decodeDigit(int $codePoint): int
    {
        if ($codePoint >= 0x30 && $codePoint <= 0x39) {
            return $codePoint - 22;
        }

        if ($codePoint >= 0x41 && $codePoint <= 0x5A) {
            return $codePoint - 65;
        }

        if ($codePoint >= 0x61 && $codePoint <= 0x7A) {
            return $codePoint - 97;
        }

        return self::BASE;
    }

    private static function adapt(int $delta, int $numPoints, bool $firstTime): int
    {
        $delta = $firstTime ? intdiv($delta, self::DAMP) : ($delta >> 1);
        $delta += intdiv($delta, $numPoints);

        $k = 0;
        $limit = intdiv((self::BASE - self::TMIN) * self::TMAX, 2);

        while ($delta > $limit) {
            $delta = intdiv($delta, self::BASE - self::TMIN);
            $k += self::BASE;
        }

        return $k + intdiv((self::BASE - self::TMIN + 1) * $delta, $delta + self::SKEW);
    }

    private static function threshold(int $k, int $bias): int
    {
        if ($k <= $bias) {
            return self::TMIN;
        }

        if ($k >= $bias + self::TMAX) {
            return self::TMAX;
        }

        return $k - $bias;
    }

    private static function assertUnicodeCodePoint(int $codePoint): void
    {
        if ($codePoint < 0 || $codePoint > self::UINT32_MAX) {
            self::unexpected('Invalid Lexbor code point.');
        }
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }

    private static function overflow(string $message): never
    {
        throw new LexborException(Status::ErrorOverflow, $message);
    }
}
