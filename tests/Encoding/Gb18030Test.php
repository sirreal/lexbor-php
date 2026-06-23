<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\DecodeResult;
use Lexbor\Encoding\Gb18030;
use Lexbor\Encoding\Gb18030Data;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class Gb18030Test extends TestCase
{
    public function testUpstreamGb18030SingleDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x80" => [0x20AC],
            "\xFF" => [$replacement],
            "\x81\x7E" => [0x4E8A],
            "\x81\x8F" => [0x4EB8],
            "\x81\x29" => [$replacement, 0x29],
            "\x81\x40" => [0x4E02],
            "\x81\x30\x80" => [$replacement, 0x30, 0x20AC],
            "\x81\x30\x81\x29" => [$replacement, 0x30, $replacement, 0x29],
            "\x81\xFF" => [$replacement],
            "\x81\x37\x81\x31" => [0x23ED],
            "\x81\x30\x81\x30" => [0x80],
            "\x81\x39\x81\x39" => [0x2E9B],
            "\xFE\x30\x81\x30" => [$replacement],
            "\xFE\x30\xFE\x30" => [$replacement],
            "\xFE\x39\xFE\x39" => [$replacement],
            "\x81\x30\xFE\x30" => [0x0600],
            "\x81\x39\xFE\x39" => [0x34A2],
            "\x81\x39\xFE\x39\x81\x39\xFE\x39" => [0x34A2, 0x34A2],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, Gb18030::decodeWithReplacement($input));
        }
    }

    public function testUpstreamGb18030SingleDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x81\x7F" => [$replacement, 0x7F],
            "\x81\x30\x20" => [$replacement, 0x30, 0x20],
            "\x81\x30\x80" => [$replacement, 0x30, 0x20AC],
            "\x81\x30\xFF" => [$replacement, 0x30, $replacement],
            "\x81\x30\xFF\x81\x37\x81\x31" => [$replacement, 0x30, $replacement, 0x23ED],
            "\x81\xFF\x81\x81\x37\x81\x31" => [$replacement, 0x4E96, 0x37, Utf8::DECODE_CONTINUE],
            "\x81\xFF\x81\x81\x37\x81\x31\x81\x31" => [$replacement, 0x4E96, 0x37, 0x060B],
            "\x81\x30\x81\x81\x37\x81\x31" => [$replacement, 0x30, 0x4E96, 0x37, Utf8::DECODE_CONTINUE],
            "\x81\x30\x81\x40" => [$replacement, 0x30, 0x4E02],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, Gb18030::decodeWithReplacement($input));
        }
    }

    public function testUpstreamGb18030SingleDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Gb18030::decodeWithReplacement(chr($byte)));
        }

        self::assertSame([0x20AC], Gb18030::decodeWithReplacement("\x80"));

        foreach (Gb18030Data::DECODE_INDEX as $pointer => $codePoint) {
            self::assertSame([$codePoint], Gb18030::decodeWithReplacement(Gb18030::bytesForPointer($pointer)));
        }
    }

    public function testUpstreamGb18030SingleDecodeRanges(): void
    {
        foreach (Gb18030Data::RANGE_INDEX as [$pointer, $codePoint]) {
            self::assertSame([$codePoint], Gb18030::decodeWithReplacement(Gb18030::fourBytesForPointer($pointer)));
        }

        self::assertSame([0xE7C7], Gb18030::decodeWithReplacement(Gb18030::fourBytesForPointer(7457)));
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], Gb18030::decodeWithReplacement(Gb18030::fourBytesForPointer(39419)));
        self::assertSame([Utf8::REPLACEMENT_CODE_POINT], Gb18030::decodeWithReplacement(Gb18030::fourBytesForPointer(188999)));
        self::assertSame([0x10FFFF], Gb18030::decodeWithReplacement(Gb18030::fourBytesForPointer(1237575)));
    }

    public function testUpstreamGb18030SingleEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Gb18030::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Gb18030::encodeCodePoint($codePoint));
        }

        foreach (Gb18030Data::ENCODE_INDEX as $codePoint => $pointer) {
            $expected = Gb18030::bytesForPointer($pointer);
            $result = Gb18030::encodeCodePointWithCapacity($codePoint, 2);

            self::assertSame(2, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, Gb18030::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamGb18030SingleEncodeRanges(): void
    {
        foreach (Gb18030Data::RANGE_INDEX as [$pointer, $codePoint]) {
            if (Gb18030::encodePointer($codePoint) !== null) {
                continue;
            }

            $expected = Gb18030::fourBytesForPointer($pointer);
            $result = Gb18030::encodeCodePointWithCapacity($codePoint, 4);

            self::assertSame(4, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, Gb18030::encodeCodePoint($codePoint));
        }

        self::assertSame(Gb18030::fourBytesForPointer(7457), Gb18030::encodeCodePoint(0xE7C7));
        self::assertSame(Gb18030::fourBytesForPointer(1237575), Gb18030::encodeCodePoint(0x10FFFF));
    }

    public function testUpstreamGb18030SingleEncodeBufferCheck(): void
    {
        foreach ([1, 2, 3] as $capacity) {
            $small = Gb18030::encodeCodePointWithCapacity(0x022E, $capacity);
            self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
            self::assertSame('', $small->bytes);
        }

        $encodedFour = Gb18030::encodeCodePointWithCapacity(0x022E, 4);
        self::assertSame(4, $encodedFour->status);
        self::assertSame("\x81\x30\xA8\x33", $encodedFour->bytes);

        $smallTwo = Gb18030::encodeCodePointWithCapacity(0x5ABE, 1);
        self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $smallTwo->status);
        self::assertSame('', $smallTwo->bytes);

        $encodedTwo = Gb18030::encodeCodePointWithCapacity(0x5ABE, 2);
        self::assertSame(2, $encodedTwo->status);
        self::assertSame("\xE6\xC5", $encodedTwo->bytes);

        self::assertSame(Utf8::ENCODE_ERROR, Gb18030::encodeCodePointWithCapacity(0xE5E5, 4)->status);

        try {
            Gb18030::encodeCodePoint(0xE5E5);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable GB18030 code point.');
    }

    public function testUpstreamGb18030BufferDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x80" => [0x20AC],
            "\xFF" => [$replacement],
            "\x81\x7E" => [0x4E8A],
            "\x81\x8F" => [0x4EB8],
            "\x81\x29" => [$replacement, 0x29],
            "\x81\x40" => [0x4E02],
            "\x81\x30\x80" => [$replacement, 0x30, 0x20AC],
            "\x81\x30\x81\x29" => [$replacement, 0x30, $replacement, 0x29],
            "\x81\xFF" => [$replacement],
            "\x81\x37\x81\x31" => [0x23ED],
            "\x81\x30\x81\x30" => [0x80],
            "\x81\x39\x81\x39" => [0x2E9B],
            "\xFE\x30\x81\x30" => [$replacement],
            "\xFE\x30\xFE\x30" => [$replacement],
            "\xFE\x39\xFE\x39" => [$replacement],
            "\x81\x30\xFE\x30" => [0x0600],
            "\x81\x39\xFE\x39" => [0x34A2],
            "\x81\x39\xFE\x39\x81\x39\xFE\x39" => [0x34A2, 0x34A2],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeGb18030Full($input, count($expected)));
            self::assertSame($expected, self::decodeGb18030Chunks($input));
        }
    }

    public function testUpstreamGb18030BufferDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x81\x7F" => [$replacement, 0x7F],
            "\x81\x30\x20" => [$replacement, 0x30, 0x20],
            "\x81\x30\x80" => [$replacement, 0x30, 0x20AC],
            "\x81\x30\xFF" => [$replacement, 0x30, $replacement],
            "\x81\x30\xFF\x81\x37\x81\x31" => [$replacement, 0x30, $replacement, 0x23ED],
            "\x81\xFF\x81\x81\x37\x81\x31" => [$replacement, 0x4E96, 0x37, Utf8::DECODE_CONTINUE],
            "\x81\xFF\x81\x81\x37\x81\x31\x81\x31" => [$replacement, 0x4E96, 0x37, 0x060B],
            "\x81\x30\x81\x81\x37\x81\x31" => [$replacement, 0x30, 0x4E96, 0x37, Utf8::DECODE_CONTINUE],
            "\x81\x30\x81\x40" => [$replacement, 0x30, 0x4E02],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeGb18030Full($input, count($expected)));
            self::assertSame($expected, self::decodeGb18030Chunks($input));
        }
    }

    public function testUpstreamGb18030BufferDecodeBufferChecks(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;

        $invalidThird = "\x81\x31\x20";
        $third = Gb18030::decodeToBuffer($invalidThird, 1);
        self::assertSame(Status::SmallBuffer, $third->status);
        self::assertSame([$replacement], $third->codePoints);
        self::assertSame(2, $third->offset);
        self::assertSame(0x31, $third->pendingLeadByte);
        self::assertTrue($third->pendingGb18030Prepend);

        $thirdResume = self::resumeDecode($invalidThird, $third, 4);
        self::assertSame(Status::Ok, $thirdResume->status);
        self::assertSame([0x31, 0x20], $thirdResume->codePoints);
        self::assertSame(3, $thirdResume->offset);

        $invalidFourth = "\x81\x30\x81\x20";
        $fourth = Gb18030::decodeToBuffer($invalidFourth, 1);
        self::assertSame(Status::SmallBuffer, $fourth->status);
        self::assertSame([$replacement], $fourth->codePoints);
        self::assertSame(3, $fourth->offset);
        self::assertSame(0x01, $fourth->pendingLeadByte);
        self::assertSame(0x30, $fourth->pendingGb18030Second);
        self::assertSame(0x81, $fourth->pendingGb18030Third);
        self::assertTrue($fourth->pendingGb18030Prepend);

        $fourthResume = self::resumeDecode($invalidFourth, $fourth, 4);
        self::assertSame(Status::Ok, $fourthResume->status);
        self::assertSame([0x30, $replacement, 0x20], $fourthResume->codePoints);
        self::assertSame(4, $fourthResume->offset);

        $fourthWithSecond = Gb18030::decodeToBuffer($invalidFourth, 2);
        self::assertSame(Status::SmallBuffer, $fourthWithSecond->status);
        self::assertSame([$replacement, 0x30], $fourthWithSecond->codePoints);
        self::assertSame(3, $fourthWithSecond->offset);
        self::assertSame(0x81, $fourthWithSecond->pendingLeadByte);
        self::assertTrue($fourthWithSecond->pendingGb18030Prepend);

        $fourthWithSecondResume = self::resumeDecode($invalidFourth, $fourthWithSecond, 4);
        self::assertSame(Status::Ok, $fourthWithSecondResume->status);
        self::assertSame([$replacement, 0x20], $fourthWithSecondResume->codePoints);
        self::assertSame(4, $fourthWithSecondResume->offset);
    }

    public function testUpstreamGb18030BufferDecodeMapAndRanges(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Gb18030::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        $euro = Gb18030::decodeToBuffer("\x80", 1);
        self::assertSame(Status::Ok, $euro->status);
        self::assertSame([0x20AC], $euro->codePoints);

        foreach (Gb18030Data::DECODE_INDEX as $pointer => $codePoint) {
            $result = Gb18030::decodeToBuffer(Gb18030::bytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(2, $result->offset);
        }

        foreach (Gb18030Data::RANGE_INDEX as [$pointer, $codePoint]) {
            $result = Gb18030::decodeToBuffer(Gb18030::fourBytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(4, $result->offset);
        }
    }

    public function testUpstreamGb18030BufferEncodeMapAndRanges(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Gb18030::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (Gb18030Data::ENCODE_INDEX as $codePoint => $pointer) {
            $result = Gb18030::encodeCodePointsToBuffer([$codePoint], 2);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(Gb18030::bytesForPointer($pointer), $result->bytes);
        }

        foreach (Gb18030Data::RANGE_INDEX as [$pointer, $codePoint]) {
            if (Gb18030::encodePointer($codePoint) !== null) {
                continue;
            }

            $result = Gb18030::encodeCodePointsToBuffer([$codePoint], 4);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(Gb18030::fourBytesForPointer($pointer), $result->bytes);
        }
    }

    public function testUpstreamGb18030BufferEncodeBufferCheck(): void
    {
        foreach ([1, 2, 3] as $capacity) {
            $small = Gb18030::encodeCodePointsToBuffer([0x022E], $capacity);
            self::assertSame(Status::SmallBuffer, $small->status);
            self::assertSame('', $small->bytes);
        }

        $encodedFour = Gb18030::encodeCodePointsToBuffer([0x022E], 4);
        self::assertSame(Status::Ok, $encodedFour->status);
        self::assertSame("\x81\x30\xA8\x33", $encodedFour->bytes);

        $smallTwo = Gb18030::encodeCodePointsToBuffer([0x5ABE], 1);
        self::assertSame(Status::SmallBuffer, $smallTwo->status);
        self::assertSame('', $smallTwo->bytes);

        $encodedTwo = Gb18030::encodeCodePointsToBuffer([0x5ABE], 2);
        self::assertSame(Status::Ok, $encodedTwo->status);
        self::assertSame("\xE6\xC5", $encodedTwo->bytes);

        self::assertSame(Status::Error, Gb18030::encodeCodePointsToBuffer([0xE5E5], 4)->status);
    }

    /**
     * @return list<int>
     */
    private static function decodeGb18030Full(string $input, int $capacity): array
    {
        $result = Gb18030::decodeToBuffer($input, $capacity);
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
    private static function decodeGb18030Chunks(string $input): array
    {
        $codePoints = [];
        $pendingLeadByte = null;
        $pendingSecond = null;
        $pendingThird = null;
        $pendingPrepend = false;
        $capacity = strlen($input) + 4;

        for ($offset = 0, $length = strlen($input); $offset < $length; $offset++) {
            $result = Gb18030::decodeToBuffer(
                $input[$offset],
                $capacity,
                pendingLeadByte: $pendingLeadByte,
                pendingGb18030Second: $pendingSecond,
                pendingGb18030Third: $pendingThird,
                pendingGb18030Prepend: $pendingPrepend,
            );

            self::assertContains($result->status, [Status::Ok, Status::Continue]);

            array_push($codePoints, ...$result->codePoints);
            $pendingLeadByte = $result->pendingLeadByte;
            $pendingSecond = $result->pendingGb18030Second;
            $pendingThird = $result->pendingGb18030Third;
            $pendingPrepend = $result->pendingGb18030Prepend;
        }

        if ($pendingLeadByte !== null) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }

    private static function resumeDecode(string $input, DecodeResult $state, int $capacity): DecodeResult
    {
        return Gb18030::decodeToBuffer(
            $input,
            $capacity,
            $state->offset,
            pendingLeadByte: $state->pendingLeadByte,
            pendingGb18030Second: $state->pendingGb18030Second,
            pendingGb18030Third: $state->pendingGb18030Third,
            pendingGb18030Prepend: $state->pendingGb18030Prepend,
        );
    }
}
