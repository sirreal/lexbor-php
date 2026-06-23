<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\SingleByteEncoding;
use Lexbor\Encoding\Utf8;
use Lexbor\Encoding\Windows1257;
use PHPUnit\Framework\TestCase;

final class Windows1257Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x20AC, 0x0081, 0x201A, 0x0083, 0x201E, 0x2026, 0x2020, 0x2021,
        0x0088, 0x2030, 0x008A, 0x2039, 0x008C, 0x00A8, 0x02C7, 0x00B8,
        0x0090, 0x2018, 0x2019, 0x201C, 0x201D, 0x2022, 0x2013, 0x2014,
        0x0098, 0x2122, 0x009A, 0x203A, 0x009C, 0x00AF, 0x02DB, 0x009F,
        0x00A0, SingleByteEncoding::ERROR_CODE_POINT, 0x00A2, 0x00A3, 0x00A4, SingleByteEncoding::ERROR_CODE_POINT, 0x00A6, 0x00A7,
        0x00D8, 0x00A9, 0x0156, 0x00AB, 0x00AC, 0x00AD, 0x00AE, 0x00C6,
        0x00B0, 0x00B1, 0x00B2, 0x00B3, 0x00B4, 0x00B5, 0x00B6, 0x00B7,
        0x00F8, 0x00B9, 0x0157, 0x00BB, 0x00BC, 0x00BD, 0x00BE, 0x00E6,
        0x0104, 0x012E, 0x0100, 0x0106, 0x00C4, 0x00C5, 0x0118, 0x0112,
        0x010C, 0x00C9, 0x0179, 0x0116, 0x0122, 0x0136, 0x012A, 0x013B,
        0x0160, 0x0143, 0x0145, 0x00D3, 0x014C, 0x00D5, 0x00D6, 0x00D7,
        0x0172, 0x0141, 0x015A, 0x016A, 0x00DC, 0x017B, 0x017D, 0x00DF,
        0x0105, 0x012F, 0x0101, 0x0107, 0x00E4, 0x00E5, 0x0119, 0x0113,
        0x010D, 0x00E9, 0x017A, 0x0117, 0x0123, 0x0137, 0x012B, 0x013C,
        0x0161, 0x0144, 0x0146, 0x00F3, 0x014D, 0x00F5, 0x00F6, 0x00F7,
        0x0173, 0x0142, 0x015B, 0x016B, 0x00FC, 0x017C, 0x017E, 0x02D9,
    ];

    public function testUpstreamWindows1257SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Windows1257::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            self::assertSame([$codePoint], Windows1257::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamWindows1257SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Windows1257::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Windows1257::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1257::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Windows1257::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamWindows1257BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Windows1257::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1257::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamWindows1257BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Windows1257::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Windows1257::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testWindows1257BufferStatusEdges(): void
    {
        $decode = Windows1257::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Windows1257::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Windows1257::encodeCodePointsToBuffer([0x0080], 1)->status);
        self::assertSame(Status::Error, Windows1257::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Windows1257::encodeCodePointWithCapacity(0x0080, 1)->status);

        self::assertSame(
            [Utf8::REPLACEMENT_CODE_POINT, Utf8::REPLACEMENT_CODE_POINT],
            Windows1257::decodeWithReplacement("\xA1\xA5"),
        );

        $undefinedDecode = Windows1257::decodeToBuffer("\xA1", 1);
        self::assertSame(Status::Ok, $undefinedDecode->status);
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], $undefinedDecode->codePoints);
        self::assertSame(1, $undefinedDecode->offset);

        try {
            Windows1257::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable Windows-1257 code point.');
    }
}
