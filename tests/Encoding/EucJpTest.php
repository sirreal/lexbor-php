<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\EucJp;
use Lexbor\Encoding\EucJpData;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class EucJpTest extends TestCase
{
    public function testUpstreamEucJpSingleDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x8D" => [$replacement],
            "\x90" => [$replacement],
            "\xA0" => [$replacement],
            "\xFF" => [$replacement],
            "\x8E\xA1" => [0xFF61],
            "\x8E\xDF" => [0xFF9F],
            "\x8E\xA0" => [$replacement],
            "\x8E\xE0" => [$replacement],
            "\x8F\xA1\xA1" => [$replacement],
            "\x8F\xA2\xAF" => [0x02D8],
            "\x8F\xCC\xE3" => [0x74AF],
            "\x8F\xED\xE3" => [0x9FA5],
            "\x8F\xFE\xFE" => [$replacement],
            "\xFC\xFE" => [0xFF02],
            "\xFE\xFE" => [$replacement],
            "\xFC\xFE\xFC\xFE" => [0xFF02, 0xFF02],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, EucJp::decodeWithReplacement($input));
        }
    }

    public function testUpstreamEucJpSingleDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\xFF\xFC\xFE" => [$replacement, 0xFF02],
            "\xFF\x8F\xA2\xAF" => [$replacement, 0x02D8],
            "\x8F\xA2\xFF\xAF" => [$replacement, Utf8::DECODE_CONTINUE],
            "\xA2\x32\xFC\xFE" => [$replacement, 0x32, 0xFF02],
            "\x8F\xED\x32\xFC\xFE" => [$replacement, 0x32, 0xFF02],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, EucJp::decodeWithReplacement($input));
        }
    }

    public function testUpstreamEucJpSingleDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], EucJp::decodeWithReplacement(chr($byte)));
        }

        for ($codePoint = 0xFF61; $codePoint <= 0xFF9F; $codePoint++) {
            self::assertSame([$codePoint], EucJp::decodeWithReplacement(chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1)));
        }

        foreach (EucJpData::JIS0208_DECODE_INDEX as $pointer => $codePoint) {
            if ($pointer > 8835) {
                continue;
            }

            self::assertSame([$codePoint], EucJp::decodeWithReplacement(EucJp::bytesForPointer($pointer)));
        }

        foreach (EucJpData::JIS0212_DECODE_INDEX as $pointer => $codePoint) {
            self::assertSame([$codePoint], EucJp::decodeWithReplacement(EucJp::jis0212BytesForPointer($pointer)));
        }
    }

    public function testUpstreamEucJpSingleEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = EucJp::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), EucJp::encodeCodePoint($codePoint));
        }

        self::assertSame("\x5C", EucJp::encodeCodePoint(0x00A5));
        self::assertSame("\x7E", EucJp::encodeCodePoint(0x203E));

        for ($codePoint = 0xFF61; $codePoint <= 0xFF9F; $codePoint++) {
            $expected = chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1);
            $result = EucJp::encodeCodePointWithCapacity($codePoint, 2);

            self::assertSame(2, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, EucJp::encodeCodePoint($codePoint));
        }

        foreach (EucJpData::ENCODE_INDEX as $codePoint => $pointer) {
            $expected = EucJp::bytesForPointer($pointer);
            $result = EucJp::encodeCodePointWithCapacity($codePoint, 2);

            self::assertSame(2, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, EucJp::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamEucJpSingleEncodeBufferCheck(): void
    {
        $small = EucJp::encodeCodePointWithCapacity(0xFA1F, 1);
        self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = EucJp::encodeCodePointWithCapacity(0xFA1F, 2);
        self::assertSame(2, $encoded->status);
        self::assertSame("\xFB\xE1", $encoded->bytes);

        self::assertSame(Utf8::ENCODE_SMALL_BUFFER, EucJp::encodeCodePointWithCapacity(0x0080, 1)->status);
        self::assertSame(Utf8::ENCODE_ERROR, EucJp::encodeCodePointWithCapacity(0x0080, 2)->status);

        try {
            EucJp::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable EUC-JP code point.');
    }

    public function testUpstreamEucJpBufferDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x8D" => [$replacement],
            "\x90" => [$replacement],
            "\xA0" => [$replacement],
            "\xFF" => [$replacement],
            "\x8E\xA1" => [0xFF61],
            "\x8E\xDF" => [0xFF9F],
            "\x8E\xA0" => [$replacement],
            "\x8E\xE0" => [$replacement],
            "\x8F\xA1\xA1" => [$replacement],
            "\x8F\xA2\xAF" => [0x02D8],
            "\x8F\xCC\xE3" => [0x74AF],
            "\x8F\xED\xE3" => [0x9FA5],
            "\x8F\xFE\xFE" => [$replacement],
            "\xFC\xFE" => [0xFF02],
            "\xFE\xFE" => [$replacement],
            "\xFC\xFE\xFC\xFE" => [0xFF02, 0xFF02],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeEucJpFull($input, count($expected)));
            self::assertSame($expected, self::decodeEucJpChunks($input));
        }
    }

    public function testUpstreamEucJpBufferDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\xFF\xFC\xFE" => [$replacement, 0xFF02],
            "\xFF\x8F\xA2\xAF" => [$replacement, 0x02D8],
            "\x8F\xA2\xFF\xAF" => [$replacement, Utf8::DECODE_CONTINUE],
            "\xA2\x32\xFC\xFE" => [$replacement, 0x32, 0xFF02],
            "\x8F\xED\x32\xFC\xFE" => [$replacement, 0x32, 0xFF02],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeEucJpFull($input, count($expected)));
            self::assertSame($expected, self::decodeEucJpChunks($input));
        }
    }

    public function testUpstreamEucJpBufferDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = EucJp::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        for ($codePoint = 0xFF61; $codePoint <= 0xFF9F; $codePoint++) {
            $result = EucJp::decodeToBuffer(chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(2, $result->offset);
        }

        foreach (EucJpData::JIS0208_DECODE_INDEX as $pointer => $codePoint) {
            if ($pointer > 8835) {
                continue;
            }

            $result = EucJp::decodeToBuffer(EucJp::bytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(2, $result->offset);
        }

        foreach (EucJpData::JIS0212_DECODE_INDEX as $pointer => $codePoint) {
            $result = EucJp::decodeToBuffer(EucJp::jis0212BytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(3, $result->offset);
        }
    }

    public function testUpstreamEucJpBufferEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = EucJp::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        self::assertSame("\x5C", EucJp::encodeCodePointsToBuffer([0x00A5], 1)->bytes);
        self::assertSame("\x7E", EucJp::encodeCodePointsToBuffer([0x203E], 1)->bytes);

        for ($codePoint = 0xFF61; $codePoint <= 0xFF9F; $codePoint++) {
            $result = EucJp::encodeCodePointsToBuffer([$codePoint], 2);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr(0x8E) . chr($codePoint - 0xFF61 + 0xA1), $result->bytes);
        }

        foreach (EucJpData::ENCODE_INDEX as $codePoint => $pointer) {
            $result = EucJp::encodeCodePointsToBuffer([$codePoint], 2);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(EucJp::bytesForPointer($pointer), $result->bytes);
        }
    }

    public function testUpstreamEucJpBufferEncodeBufferCheck(): void
    {
        $small = EucJp::encodeCodePointsToBuffer([0xFA1F], 1);
        self::assertSame(Status::SmallBuffer, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = EucJp::encodeCodePointsToBuffer([0xFA1F], 2);
        self::assertSame(Status::Ok, $encoded->status);
        self::assertSame("\xFB\xE1", $encoded->bytes);

        self::assertSame(Status::Error, EucJp::encodeCodePointsToBuffer([0x0080], 1)->status);
    }

    /**
     * @return list<int>
     */
    private static function decodeEucJpFull(string $input, int $capacity): array
    {
        $result = EucJp::decodeToBuffer($input, $capacity);
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
    private static function decodeEucJpChunks(string $input): array
    {
        $codePoints = [];
        $pendingLeadByte = null;
        $pendingJis0212 = false;
        $capacity = strlen($input) + 1;

        for ($offset = 0, $length = strlen($input); $offset < $length; $offset++) {
            $result = EucJp::decodeToBuffer(
                $input[$offset],
                $capacity,
                pendingLeadByte: $pendingLeadByte,
                pendingJis0212: $pendingJis0212,
            );

            self::assertContains($result->status, [Status::Ok, Status::Continue]);

            array_push($codePoints, ...$result->codePoints);
            $pendingLeadByte = $result->pendingLeadByte;
            $pendingJis0212 = $result->pendingJis0212;
        }

        if ($pendingLeadByte !== null) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }
}
