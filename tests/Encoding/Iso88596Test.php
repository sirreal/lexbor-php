<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Iso88596;
use Lexbor\Encoding\SingleByteEncoding;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class Iso88596Test extends TestCase
{
    /**
     * @var list<int>
     */
    private const array DECODE_INDEX = [
        0x0080, 0x0081, 0x0082, 0x0083, 0x0084, 0x0085, 0x0086, 0x0087,
        0x0088, 0x0089, 0x008A, 0x008B, 0x008C, 0x008D, 0x008E, 0x008F,
        0x0090, 0x0091, 0x0092, 0x0093, 0x0094, 0x0095, 0x0096, 0x0097,
        0x0098, 0x0099, 0x009A, 0x009B, 0x009C, 0x009D, 0x009E, 0x009F,
        0x00A0, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x00A4, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x060C, 0x00AD, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x061B, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, 0x061F,
        SingleByteEncoding::ERROR_CODE_POINT, 0x0621, 0x0622, 0x0623, 0x0624, 0x0625, 0x0626, 0x0627,
        0x0628, 0x0629, 0x062A, 0x062B, 0x062C, 0x062D, 0x062E, 0x062F,
        0x0630, 0x0631, 0x0632, 0x0633, 0x0634, 0x0635, 0x0636, 0x0637,
        0x0638, 0x0639, 0x063A, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        0x0640, 0x0641, 0x0642, 0x0643, 0x0644, 0x0645, 0x0646, 0x0647,
        0x0648, 0x0649, 0x064A, 0x064B, 0x064C, 0x064D, 0x064E, 0x064F,
        0x0650, 0x0651, 0x0652, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
        SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT, SingleByteEncoding::ERROR_CODE_POINT,
    ];

    public function testUpstreamIso88596SingleDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Iso88596::decodeWithReplacement(chr($byte)));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            self::assertSame([$codePoint], Iso88596::decodeWithReplacement(chr($offset + 0x80)));
        }
    }

    public function testUpstreamIso88596SingleEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Iso88596::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Iso88596::encodeCodePoint($codePoint));
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Iso88596::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
            self::assertSame(chr($offset + 0x80), Iso88596::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamIso88596BufferDecode(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Iso88596::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Iso88596::decodeToBuffer(chr($offset + 0x80), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(1, $result->offset);
        }
    }

    public function testUpstreamIso88596BufferEncode(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Iso88596::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (self::DECODE_INDEX as $offset => $codePoint) {
            if ($codePoint === SingleByteEncoding::ERROR_CODE_POINT) {
                continue;
            }

            $result = Iso88596::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($offset + 0x80), $result->bytes);
        }
    }

    public function testIso88596BufferStatusEdges(): void
    {
        $decode = Iso88596::decodeToBuffer("\x41\x42", 1);
        self::assertSame(Status::SmallBuffer, $decode->status);
        self::assertSame([0x41], $decode->codePoints);
        self::assertSame(1, $decode->offset);

        $encode = Iso88596::encodeCodePointsToBuffer([0x41, 0x42], 1);
        self::assertSame(Status::SmallBuffer, $encode->status);
        self::assertSame("\x41", $encode->bytes);

        self::assertSame(Status::Error, Iso88596::encodeCodePointsToBuffer([0x20AC], 1)->status);
        self::assertSame(Status::Error, Iso88596::encodeCodePointsToBuffer([-1], 1)->status);
        self::assertSame(-1, Iso88596::encodeCodePointWithCapacity(0x20AC, 1)->status);
        self::assertSame([0x0080, 0x009F, 0x060C, 0x0621, 0x0640, 0x0652], Iso88596::decodeWithReplacement("\x80\x9F\xAC\xC1\xE0\xF2"));

        self::assertSame(
            array_fill(0, 8, Utf8::REPLACEMENT_CODE_POINT),
            Iso88596::decodeWithReplacement("\xA1\xA8\xAE\xB0\xC0\xDB\xF3\xFF"),
        );

        $undefinedDecode = Iso88596::decodeToBuffer("\xA1", 1);
        self::assertSame(Status::Ok, $undefinedDecode->status);
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], $undefinedDecode->codePoints);
        self::assertSame(1, $undefinedDecode->offset);

        try {
            Iso88596::encodeCodePoint(0x20AC);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable ISO-8859-6 code point.');
    }
}
