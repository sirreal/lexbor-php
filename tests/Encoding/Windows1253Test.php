<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\SingleByteEncoding;
use Lexbor\Encoding\Utf8;
use Lexbor\Encoding\Windows1253;
use PHPUnit\Framework\TestCase;

final class Windows1253Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x20AC, 0x0081, 0x201A, 0x0192, 0x201E, 0x2026, 0x2020, 0x2021,
        0x0088, 0x2030, 0x008A, 0x2039, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
        0x0098, 0x2122, 0x009A, 0x203A, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x0385, 0x0386, 0x00A3, 0x00A4, 0x00A5, 0x00A6, 0x00A7,
        0x00A8, 0x00A9, SingleByteEncoding::ERROR_CODE_POINT, 0x00AB, 0x00AC, 0x00AD, 0x00AE, 0x2015,
        0x00B0, 0x00B1, 0x00B2, 0x00B3, 0x0384, 0x00B5, 0x00B6, 0x00B7,
        0x0388, 0x0389, 0x038A, 0x00BB, 0x038C, 0x00BD, 0x038E, 0x038F,
        0x0390, 0x0391, 0x0392, 0x0393, 0x0394, 0x0395, 0x0396, 0x0397,
        0x0398, 0x0399, 0x039A, 0x039B, 0x039C, 0x039D, 0x039E, 0x039F,
        0x03A0, 0x03A1, SingleByteEncoding::ERROR_CODE_POINT, 0x03A3, 0x03A4, 0x03A5, 0x03A6, 0x03A7,
        0x03A8, 0x03A9, 0x03AA, 0x03AB, 0x03AC, 0x03AD, 0x03AE, 0x03AF,
        0x03B0, 0x03B1, 0x03B2, 0x03B3, 0x03B4, 0x03B5, 0x03B6, 0x03B7,
        0x03B8, 0x03B9, 0x03BA, 0x03BB, 0x03BC, 0x03BD, 0x03BE, 0x03BF,
        0x03C0, 0x03C1, 0x03C2, 0x03C3, 0x03C4, 0x03C5, 0x03C6, 0x03C7,
        0x03C8, 0x03C9, 0x03CA, 0x03CB, 0x03CC, 0x03CD, 0x03CE, SingleByteEncoding::ERROR_CODE_POINT,
    ];

    public function testUpstreamWindows1253SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Windows1253::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            self::assertSame([$codePoint], Windows1253::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamWindows1253SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Windows1253::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Windows1253::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1253::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Windows1253::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamWindows1253BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Windows1253::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1253::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamWindows1253BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Windows1253::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1253::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testWindows1253BufferStatusEdges(): void
    {
        $decode = Windows1253::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Windows1253::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Windows1253::encodeCodePointsToBuffer([0x0080], 1)->status);
        self::assertSame(Status::Error, Windows1253::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Windows1253::encodeCodePointWithCapacity(0x0080, 1)->status);

        self::assertSame(SingleByteEncoding::ERROR_CODE_POINT, Windows1253::decodeByte(0xAA));
        self::assertSame(SingleByteEncoding::ERROR_CODE_POINT, Windows1253::decodeByte(0xD2));
        self::assertSame(SingleByteEncoding::ERROR_CODE_POINT, Windows1253::decodeByte(0xFF));
        self::assertSame(
            [Utf8::REPLACEMENT_CODE_POINT, Utf8::REPLACEMENT_CODE_POINT, Utf8::REPLACEMENT_CODE_POINT],
            Windows1253::decodeWithReplacement("\xAA\xD2\xFF"),
        );
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], Windows1253::decodeToBuffer("\xAA", 1)->codePoints);

        try {
            Windows1253::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable Windows-1253 code point.');
    }
}
