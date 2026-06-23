<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Ibm866;
use PHPUnit\Framework\TestCase;

final class Ibm866Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0410, 0x0411, 0x0412, 0x0413, 0x0414, 0x0415, 0x0416, 0x0417,
        0x0418, 0x0419, 0x041A, 0x041B, 0x041C, 0x041D, 0x041E, 0x041F,
        0x0420, 0x0421, 0x0422, 0x0423, 0x0424, 0x0425, 0x0426, 0x0427,
        0x0428, 0x0429, 0x042A, 0x042B, 0x042C, 0x042D, 0x042E, 0x042F,
        0x0430, 0x0431, 0x0432, 0x0433, 0x0434, 0x0435, 0x0436, 0x0437,
        0x0438, 0x0439, 0x043A, 0x043B, 0x043C, 0x043D, 0x043E, 0x043F,
        0x2591, 0x2592, 0x2593, 0x2502, 0x2524, 0x2561, 0x2562, 0x2556,
        0x2555, 0x2563, 0x2551, 0x2557, 0x255D, 0x255C, 0x255B, 0x2510,
        0x2514, 0x2534, 0x252C, 0x251C, 0x2500, 0x253C, 0x255E, 0x255F,
        0x255A, 0x2554, 0x2569, 0x2566, 0x2560, 0x2550, 0x256C, 0x2567,
        0x2568, 0x2564, 0x2565, 0x2559, 0x2558, 0x2552, 0x2553, 0x256B,
        0x256A, 0x2518, 0x250C, 0x2588, 0x2584, 0x258C, 0x2590, 0x2580,
        0x0440, 0x0441, 0x0442, 0x0443, 0x0444, 0x0445, 0x0446, 0x0447,
        0x0448, 0x0449, 0x044A, 0x044B, 0x044C, 0x044D, 0x044E, 0x044F,
        0x0401, 0x0451, 0x0404, 0x0454, 0x0407, 0x0457, 0x040E, 0x045E,
        0x00B0, 0x2219, 0x00B7, 0x221A, 0x2116, 0x00A4, 0x25A0, 0x00A0,
    ];

    public function testUpstreamIbm866SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Ibm866::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            self::assertSame([$codePoint], Ibm866::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamIbm866SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Ibm866::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Ibm866::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = Ibm866::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Ibm866::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamIbm866BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Ibm866::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = Ibm866::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamIbm866BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Ibm866::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            $result = Ibm866::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testIbm866BufferStatusEdges(): void
    {
        $decode = Ibm866::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Ibm866::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Ibm866::encodeCodePointsToBuffer([0x0080], 1)->status);
        self::assertSame(Status::Error, Ibm866::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Ibm866::encodeCodePointWithCapacity(0x0080, 1)->status);
        self::assertSame([0x0410, 0x2591, 0x2500, 0x0401, 0x25A0, 0x00A0], Ibm866::decodeWithReplacement("\x80\xB0\xC4\xF0\xFE\xFF"));

        try {
            Ibm866::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable IBM866 code point.');
    }
}
