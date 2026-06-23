<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\XMacCyrillic;
use PHPUnit\Framework\TestCase;

final class XMacCyrillicTest extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0410, 0x0411, 0x0412, 0x0413, 0x0414, 0x0415, 0x0416, 0x0417,
        0x0418, 0x0419, 0x041A, 0x041B, 0x041C, 0x041D, 0x041E, 0x041F,
        0x0420, 0x0421, 0x0422, 0x0423, 0x0424, 0x0425, 0x0426, 0x0427,
        0x0428, 0x0429, 0x042A, 0x042B, 0x042C, 0x042D, 0x042E, 0x042F,
        0x2020, 0x00B0, 0x0490, 0x00A3, 0x00A7, 0x2022, 0x00B6, 0x0406,
        0x00AE, 0x00A9, 0x2122, 0x0402, 0x0452, 0x2260, 0x0403, 0x0453,
        0x221E, 0x00B1, 0x2264, 0x2265, 0x0456, 0x00B5, 0x0491, 0x0408,
        0x0404, 0x0454, 0x0407, 0x0457, 0x0409, 0x0459, 0x040A, 0x045A,
        0x0458, 0x0405, 0x00AC, 0x221A, 0x0192, 0x2248, 0x2206, 0x00AB,
        0x00BB, 0x2026, 0x00A0, 0x040B, 0x045B, 0x040C, 0x045C, 0x0455,
        0x2013, 0x2014, 0x201C, 0x201D, 0x2018, 0x2019, 0x00F7, 0x201E,
        0x040E, 0x045E, 0x040F, 0x045F, 0x2116, 0x0401, 0x0451, 0x044F,
        0x0430, 0x0431, 0x0432, 0x0433, 0x0434, 0x0435, 0x0436, 0x0437,
        0x0438, 0x0439, 0x043A, 0x043B, 0x043C, 0x043D, 0x043E, 0x043F,
        0x0440, 0x0441, 0x0442, 0x0443, 0x0444, 0x0445, 0x0446, 0x0447,
        0x0448, 0x0449, 0x044A, 0x044B, 0x044C, 0x044D, 0x044E, 0x20AC,
    ];

    public function testUpstreamXMacCyrillicSingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], XMacCyrillic::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            self::assertSame([$codePoint], XMacCyrillic::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamXMacCyrillicSingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = XMacCyrillic::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), XMacCyrillic::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = XMacCyrillic::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), XMacCyrillic::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamXMacCyrillicBufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = XMacCyrillic::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = XMacCyrillic::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamXMacCyrillicBufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = XMacCyrillic::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = XMacCyrillic::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testXMacCyrillicBufferStatusEdges(): void
    {
        $decode = XMacCyrillic::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = XMacCyrillic::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, XMacCyrillic::encodeCodePointsToBuffer([0x0080], 1)->status);
        self::assertSame(Status::Error, XMacCyrillic::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, XMacCyrillic::encodeCodePointWithCapacity(0x0080, 1)->status);
        self::assertSame([0x0410, 0x0490, 0x0401, 0x044E, 0x20AC], XMacCyrillic::decodeWithReplacement("\x80\xA2\xDD\xFE\xFF"));

        try {
            XMacCyrillic::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable x-mac-cyrillic code point.');
    }
}
