<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\ShiftJis;
use Lexbor\Encoding\ShiftJisData;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class ShiftJisTest extends TestCase
{
    public function testUpstreamShiftJisSingleDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\xA0" => [$replacement],
            "\xE1" => [$replacement],
            "\xA1" => [0xFF61],
            "\xDF" => [0xFF9F],
            "\x81" => [Utf8::DECODE_CONTINUE],
            "\x81\x40" => [0x3000],
            "\x81\x7E" => [0x00D7],
            "\x81\x80" => [0x00F7],
            "\x81\xFC" => [0x25EF],
            "\x9F\xFC" => [0x6ECC],
            "\xFC\xFC" => [$replacement],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, ShiftJis::decodeWithReplacement($input));
        }
    }

    public function testUpstreamShiftJisSingleDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\xFF\x9F\xFC" => [$replacement, 0x6ECC],
            "\xFC\xFC\x41" => [$replacement, 0x41],
            "\xFC\x7E\x41" => [$replacement, 0x7E, 0x41],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, ShiftJis::decodeWithReplacement($input));
        }
    }

    public function testUpstreamShiftJisSingleDecodeMap(): void
    {
        for ($byte = 0; $byte <= 0x80; $byte++) {
            self::assertSame([$byte], ShiftJis::decodeWithReplacement(chr($byte)));
        }

        for ($byte = 0xA1; $byte <= 0xDF; $byte++) {
            self::assertSame([0xFF61 - 0xA1 + $byte], ShiftJis::decodeWithReplacement(chr($byte)));
        }

        foreach (ShiftJisData::JIS0208_DECODE_INDEX as $pointer => $codePoint) {
            if (! self::isDecodablePointer($pointer)) {
                continue;
            }

            self::assertSame([$codePoint], ShiftJis::decodeWithReplacement(ShiftJis::bytesForPointer($pointer)));
        }
    }

    public function testUpstreamShiftJisSingleEncode(): void
    {
        $yen = ShiftJis::encodeCodePointWithCapacity(0x00A5, 1);
        self::assertSame(1, $yen->status);
        self::assertSame("\x5C", $yen->bytes);
        self::assertSame("\x5C", ShiftJis::encodeCodePoint(0x00A5));

        $overline = ShiftJis::encodeCodePointWithCapacity(0x203E, 1);
        self::assertSame(1, $overline->status);
        self::assertSame("\x7E", $overline->bytes);
        self::assertSame("\x7E", ShiftJis::encodeCodePoint(0x203E));

        self::assertSame("\x81\x7C", ShiftJis::encodeCodePoint(0x2212));
    }

    public function testUpstreamShiftJisSingleEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint <= 0x80; $codePoint++) {
            $result = ShiftJis::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), ShiftJis::encodeCodePoint($codePoint));
        }

        for ($codePoint = 0xFF61; $codePoint <= 0xFF9F; $codePoint++) {
            $expected = chr($codePoint - 0xFF61 + 0xA1);
            $result = ShiftJis::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, ShiftJis::encodeCodePoint($codePoint));
        }

        foreach (ShiftJisData::ENCODE_INDEX as $codePoint => $pointer) {
            $expected = ShiftJis::bytesForPointer($pointer);
            $result = ShiftJis::encodeCodePointWithCapacity($codePoint, 2);

            self::assertSame(strlen($expected), $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, ShiftJis::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamShiftJisSingleEncodeBufferCheck(): void
    {
        $small = ShiftJis::encodeCodePointWithCapacity(0xFA1F, 1);
        self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = ShiftJis::encodeCodePointWithCapacity(0xFA1F, 2);
        self::assertSame(2, $encoded->status);
        self::assertSame("\xEE\x81", $encoded->bytes);

        self::assertSame(Utf8::ENCODE_ERROR, ShiftJis::encodeCodePointWithCapacity(0x0081, 2)->status);

        try {
            ShiftJis::encodeCodePoint(0x0081);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable Shift_JIS code point.');
    }

    public function testUpstreamShiftJisBufferDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\xA0" => [$replacement],
            "\xE1" => [$replacement],
            "\xA1" => [0xFF61],
            "\xDF" => [0xFF9F],
            "\x81" => [Utf8::DECODE_CONTINUE],
            "\x81\x40" => [0x3000],
            "\x81\x7E" => [0x00D7],
            "\x81\x80" => [0x00F7],
            "\x81\xFC" => [0x25EF],
            "\x9F\xFC" => [0x6ECC],
            "\xFC\xFC" => [$replacement],
            "\x9F\xFC\x9F\xFC" => [0x6ECC, 0x6ECC],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeShiftJisFull($input, count($expected)));
            self::assertSame($expected, self::decodeShiftJisChunks($input));
        }
    }

    public function testUpstreamShiftJisBufferDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\xFF\x9F\xFC" => [$replacement, 0x6ECC],
            "\xFC\xFC\x41" => [$replacement, 0x41],
            "\xFC\x7E\x41" => [$replacement, 0x7E, 0x41],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeShiftJisFull($input, count($expected)));
            self::assertSame($expected, self::decodeShiftJisChunks($input));
        }
    }

    public function testUpstreamShiftJisBufferDecodeMap(): void
    {
        for ($byte = 0; $byte <= 0x80; $byte++) {
            $result = ShiftJis::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        for ($byte = 0xA1; $byte <= 0xDF; $byte++) {
            $result = ShiftJis::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([0xFF61 - 0xA1 + $byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (ShiftJisData::JIS0208_DECODE_INDEX as $pointer => $codePoint) {
            if (! self::isDecodablePointer($pointer)) {
                continue;
            }

            $result = ShiftJis::decodeToBuffer(ShiftJis::bytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(2, $result->offset);
        }
    }

    public function testUpstreamShiftJisBufferEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint <= 0x80; $codePoint++) {
            $result = ShiftJis::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        for ($codePoint = 0xFF61; $codePoint <= 0xFF9F; $codePoint++) {
            $result = ShiftJis::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint - 0xFF61 + 0xA1), $result->bytes);
        }

        foreach (ShiftJisData::ENCODE_INDEX as $codePoint => $pointer) {
            $result = ShiftJis::encodeCodePointsToBuffer([$codePoint], 2);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(ShiftJis::bytesForPointer($pointer), $result->bytes);
        }
    }

    public function testUpstreamShiftJisBufferEncode(): void
    {
        $yen = ShiftJis::encodeCodePointsToBuffer([0x00A5], 1);
        self::assertSame(Status::Ok, $yen->status);
        self::assertSame("\x5C", $yen->bytes);

        $overline = ShiftJis::encodeCodePointsToBuffer([0x203E], 1);
        self::assertSame(Status::Ok, $overline->status);
        self::assertSame("\x7E", $overline->bytes);
    }

    public function testUpstreamShiftJisBufferEncodeBufferCheck(): void
    {
        $small = ShiftJis::encodeCodePointsToBuffer([0xFA1F], 1);
        self::assertSame(Status::SmallBuffer, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = ShiftJis::encodeCodePointsToBuffer([0xFA1F], 2);
        self::assertSame(Status::Ok, $encoded->status);
        self::assertSame("\xEE\x81", $encoded->bytes);

        self::assertSame(Status::Error, ShiftJis::encodeCodePointsToBuffer([0x0081], 2)->status);
    }

    /**
     * @return list<int>
     */
    private static function decodeShiftJisFull(string $input, int $capacity): array
    {
        $result = ShiftJis::decodeToBuffer($input, $capacity);
        $codePoints = $result->codePoints;

        if ($result->status === Status::Continue) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
            self::assertSame(strlen($input), $result->offset);

            return $codePoints;
        }

        self::assertSame(Status::Ok, $result->status);
        self::assertSame(strlen($input), $result->offset);

        return $codePoints;
    }

    /**
     * @return list<int>
     */
    private static function decodeShiftJisChunks(string $input): array
    {
        $codePoints = [];
        $pendingLeadByte = null;
        $capacity = strlen($input) + 1;

        for ($offset = 0, $length = strlen($input); $offset < $length; $offset++) {
            $result = ShiftJis::decodeToBuffer($input[$offset], $capacity, pendingLeadByte: $pendingLeadByte);

            self::assertContains($result->status, [Status::Ok, Status::Continue]);

            array_push($codePoints, ...$result->codePoints);
            $pendingLeadByte = $result->pendingLeadByte;
        }

        if ($pendingLeadByte !== null) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }

    private static function isDecodablePointer(int $pointer): bool
    {
        $lead = intdiv($pointer, 188);
        $leadByte = $lead + ($lead < 0x1F ? 0x81 : 0xC1);

        return ($leadByte >= 0x81 && $leadByte <= 0x9F) || $leadByte === 0xE0 || $leadByte === 0xFC;
    }
}
