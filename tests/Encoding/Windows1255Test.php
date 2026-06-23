<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\SingleByteEncoding;
use Lexbor\Encoding\Utf8;
use Lexbor\Encoding\Windows1255;
use PHPUnit\Framework\TestCase;

final class Windows1255Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x20AC, 0x0081, 0x201A, 0x0192, 0x201E, 0x2026, 0x2020, 0x2021,
        0x02C6, 0x2030, 0x008A, 0x2039, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
        0x02DC, 0x2122, 0x009A, 0x203A, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x00A1, 0x00A2, 0x00A3, 0x20AA, 0x00A5, 0x00A6, 0x00A7,
        0x00A8, 0x00A9, 0x00D7, 0x00AB, 0x00AC, 0x00AD, 0x00AE, 0x00AF,
        0x00B0, 0x00B1, 0x00B2, 0x00B3, 0x00B4, 0x00B5, 0x00B6, 0x00B7,
        0x00B8, 0x00B9, 0x00F7, 0x00BB, 0x00BC, 0x00BD, 0x00BE, 0x00BF,
        0x05B0, 0x05B1, 0x05B2, 0x05B3, 0x05B4, 0x05B5, 0x05B6, 0x05B7,
        0x05B8, 0x05B9, 0x05BA, 0x05BB, 0x05BC, 0x05BD, 0x05BE, 0x05BF,
        0x05C0, 0x05C1, 0x05C2, 0x05C3, 0x05F0, 0x05F1, 0x05F2, 0x05F3,
        0x05F4, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        0x05D0, 0x05D1, 0x05D2, 0x05D3, 0x05D4, 0x05D5, 0x05D6, 0x05D7,
        0x05D8, 0x05D9, 0x05DA, 0x05DB, 0x05DC, 0x05DD, 0x05DE, 0x05DF,
        0x05E0, 0x05E1, 0x05E2, 0x05E3, 0x05E4, 0x05E5, 0x05E6, 0x05E7,
        0x05E8, 0x05E9, 0x05EA, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x200E, 0x200F, SingleByteEncoding::ERROR_CODE_POINT,
    ];

    public function testUpstreamWindows1255SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Windows1255::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            self::assertSame([$codePoint], Windows1255::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamWindows1255SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Windows1255::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Windows1255::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1255::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Windows1255::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamWindows1255BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Windows1255::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1255::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamWindows1255BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Windows1255::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1255::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testWindows1255BufferStatusEdges(): void
    {
        $decode = Windows1255::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Windows1255::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Windows1255::encodeCodePointsToBuffer([0x0080], 1)->status);
        self::assertSame(Status::Error, Windows1255::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Windows1255::encodeCodePointWithCapacity(0x0080, 1)->status);

        self::assertSame(
            [Utf8::REPLACEMENT_CODE_POINT, Utf8::REPLACEMENT_CODE_POINT, Utf8::REPLACEMENT_CODE_POINT],
            Windows1255::decodeWithReplacement("\xD9\xFB\xFF"),
        );

        $undefinedDecode = Windows1255::decodeToBuffer("\xD9", 1);
        self::assertSame(Status::Ok, $undefinedDecode->status);
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], $undefinedDecode->codePoints);
        self::assertSame(1, $undefinedDecode->offset);

        try {
            Windows1255::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable Windows-1255 code point.');
    }
}
