<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Iso88593;
use Lexbor\Encoding\SingleByteEncoding;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class Iso88593Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x0091, 0x0092, 0x0093, 0x0094, 0x0095, 0x0096, 0x0097,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, 0x0126, 0x02D8, 0x00A3, 0x00A4, SingleByteEncoding::ERROR_CODE_POINT, 0x0124, 0x00A7,
        0x00A8, 0x0130, 0x015E, 0x011E, 0x0134, 0x00AD, SingleByteEncoding::ERROR_CODE_POINT, 0x017B,
        0x00B0, 0x0127, 0x00B2, 0x00B3, 0x00B4, 0x00B5, 0x0125, 0x00B7,
        0x00B8, 0x0131, 0x015F, 0x011F, 0x0135, 0x00BD, SingleByteEncoding::ERROR_CODE_POINT, 0x017C,
        0x00C0, 0x00C1, 0x00C2, SingleByteEncoding::ERROR_CODE_POINT, 0x00C4, 0x010A, 0x0108, 0x00C7,
        0x00C8, 0x00C9, 0x00CA, 0x00CB, 0x00CC, 0x00CD, 0x00CE, 0x00CF,
        SingleByteEncoding::ERROR_CODE_POINT, 0x00D1, 0x00D2, 0x00D3, 0x00D4, 0x0120, 0x00D6, 0x00D7,
        0x011C, 0x00D9, 0x00DA, 0x00DB, 0x00DC, 0x016C, 0x015C, 0x00DF,
        0x00E0, 0x00E1, 0x00E2, SingleByteEncoding::ERROR_CODE_POINT, 0x00E4, 0x010B, 0x0109, 0x00E7,
        0x00E8, 0x00E9, 0x00EA, 0x00EB, 0x00EC, 0x00ED, 0x00EE, 0x00EF,
        SingleByteEncoding::ERROR_CODE_POINT, 0x00F1, 0x00F2, 0x00F3, 0x00F4, 0x0121, 0x00F6, 0x00F7,
        0x011D, 0x00F9, 0x00FA, 0x00FB, 0x00FC, 0x016D, 0x015D, 0x02D9,
    ];

    public function testUpstreamIso88593SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Iso88593::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            self::assertSame([$codePoint], Iso88593::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamIso88593SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Iso88593::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Iso88593::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Iso88593::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Iso88593::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamIso88593BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Iso88593::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Iso88593::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamIso88593BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Iso88593::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Iso88593::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testIso88593BufferStatusEdges(): void
    {
        $decode = Iso88593::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Iso88593::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Iso88593::encodeCodePointsToBuffer([0x20AC], 1)->status);
        self::assertSame(Status::Error, Iso88593::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Iso88593::encodeCodePointWithCapacity(0x20AC, 1)->status);
        self::assertSame([0x0080, 0x009F, 0x0126, 0x00C0, 0x00E0, 0x02D9], Iso88593::decodeWithReplacement("\x80\x9F\xA1\xC0\xE0\xFF"));

        self::assertSame(
            array_fill(0, 7, Utf8::REPLACEMENT_CODE_POINT),
            Iso88593::decodeWithReplacement("\xA5\xAE\xBE\xC3\xD0\xE3\xF0"),
        );

        $undefinedDecode = Iso88593::decodeToBuffer("\xA5", 1);
        self::assertSame(Status::Ok, $undefinedDecode->status);
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], $undefinedDecode->codePoints);
        self::assertSame(1, $undefinedDecode->offset);

        try {
            Iso88593::encodeCodePoint(0x20AC);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable ISO-8859-3 code point.');
    }
}
