<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Iso885910;
use PHPUnit\Framework\TestCase;

final class Iso885910Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x0091, 0x0092, 0x0093, 0x0094, 0x0095, 0x0096, 0x0097,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x0104, 0x0112, 0x0122, 0x012A, 0x0128, 0x0136, 0x00A7,
        0x013B, 0x0110, 0x0160, 0x0166, 0x017D, 0x00AD, 0x016A, 0x014A,
        0x00B0, 0x0105, 0x0113, 0x0123, 0x012B, 0x0129, 0x0137, 0x00B7,
        0x013C, 0x0111, 0x0161, 0x0167, 0x017E, 0x2015, 0x016B, 0x014B,
        0x0100, 0x00C1, 0x00C2, 0x00C3, 0x00C4, 0x00C5, 0x00C6, 0x012E,
        0x010C, 0x00C9, 0x0118, 0x00CB, 0x0116, 0x00CD, 0x00CE, 0x00CF,
        0x00D0, 0x0145, 0x014C, 0x00D3, 0x00D4, 0x00D5, 0x00D6, 0x0168,
        0x00D8, 0x0172, 0x00DA, 0x00DB, 0x00DC, 0x00DD, 0x00DE, 0x00DF,
        0x0101, 0x00E1, 0x00E2, 0x00E3, 0x00E4, 0x00E5, 0x00E6, 0x012F,
        0x010D, 0x00E9, 0x0119, 0x00EB, 0x0117, 0x00ED, 0x00EE, 0x00EF,
        0x00F0, 0x0146, 0x014D, 0x00F3, 0x00F4, 0x00F5, 0x00F6, 0x0169,
        0x00F8, 0x0173, 0x00FA, 0x00FB, 0x00FC, 0x00FD, 0x00FE, 0x0138,
    ];

    public function testUpstreamIso885910SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Iso885910::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            self::assertSame([$codePoint], Iso885910::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamIso885910SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Iso885910::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Iso885910::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = Iso885910::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Iso885910::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamIso885910BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Iso885910::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = Iso885910::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamIso885910BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Iso885910::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = Iso885910::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testIso885910BufferStatusEdges(): void
    {
        $decode = Iso885910::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Iso885910::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Iso885910::encodeCodePointsToBuffer([0x20AC], 1)->status);
        self::assertSame(Status::Error, Iso885910::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Iso885910::encodeCodePointWithCapacity(0x20AC, 1)->status);
        self::assertSame([0x0080, 0x009F, 0x0104, 0x00B0, 0x0100, 0x0138], Iso885910::decodeWithReplacement("\x80\x9F\xA1\xB0\xC0\xFF"));

        try {
            Iso885910::encodeCodePoint(0x20AC);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable ISO-8859-10 code point.');
    }
}
