<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Big5;
use Lexbor\Encoding\Big5Data;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class Big5Test extends TestCase
{
    public function testUpstreamBig5SingleDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x80" => [$replacement],
            "\xFF" => [$replacement],
            "\x81\x7F" => [$replacement, 0x7F],
            "\x81\x40" => [$replacement, 0x40],
            "\xFE\xFE" => [0x79D4],
            "\xFE\x7E" => [0x24A8C],
            "\x88\x62" => [0x00CA, 0x0304],
            "\x88\x64" => [0x00CA, 0x030C],
            "\x88\xA3" => [0x00EA, 0x0304],
            "\x88\xA5" => [0x00EA, 0x030C],
            "\xFE\x7E\xFE\x7E" => [0x24A8C, 0x24A8C],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, Big5::decodeWithReplacement($input));
        }

        self::assertSame([0x43F0], Big5::decodeWithReplacement(Big5::bytesForPointer(942)));
    }

    public function testUpstreamBig5SingleDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x8F\x7F" => [$replacement, 0x7F],
            "\x81\x32" => [$replacement, 0x32],
            "\x81\xFF" => [$replacement],
            "\x81\xFF\xFE\x7E" => [$replacement, 0x24A8C],
            "\x81\x40\x32" => [$replacement, 0x40, 0x32],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, Big5::decodeWithReplacement($input));
        }
    }

    public function testUpstreamBig5SingleDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], Big5::decodeWithReplacement(chr($byte)));
        }

        foreach (Big5Data::DECODE_INDEX as $pointer => $codePoint) {
            self::assertSame([$codePoint], Big5::decodeWithReplacement(Big5::bytesForPointer($pointer)));
        }
    }

    public function testUpstreamBig5SingleEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Big5::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), Big5::encodeCodePoint($codePoint));
        }

        foreach (Big5Data::ENCODE_INDEX as $codePoint => $pointer) {
            $expected = Big5::bytesForPointer($pointer);
            $result = Big5::encodeCodePointWithCapacity($codePoint, 2);

            self::assertSame(2, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, Big5::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamBig5SingleEncodeBufferCheck(): void
    {
        $small = Big5::encodeCodePointWithCapacity(0x9AD0, 1);
        self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = Big5::encodeCodePointWithCapacity(0x9AD0, 2);
        self::assertSame(2, $encoded->status);
        self::assertSame("\xF7\xA4", $encoded->bytes);

        self::assertSame(Utf8::ENCODE_ERROR, Big5::encodeCodePointWithCapacity(0x43F0, 2)->status);

        try {
            Big5::encodeCodePoint(0x43F0);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable Big5 code point.');
    }

    public function testUpstreamBig5BufferDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x80" => [$replacement],
            "\xFF" => [$replacement],
            "\x81\x7F" => [$replacement, 0x7F],
            "\x81\x40" => [$replacement, 0x40],
            "\xFE\xFE" => [0x79D4],
            "\xFE\x7E" => [0x24A8C],
            "\x88\x62" => [0x00CA, 0x0304],
            "\x88\x64" => [0x00CA, 0x030C],
            "\x88\xA3" => [0x00EA, 0x0304],
            "\x88\xA5" => [0x00EA, 0x030C],
            "\xFE\x7E\xFE\x7E" => [0x24A8C, 0x24A8C],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeBig5Full($input, count($expected)));
            self::assertSame($expected, self::decodeBig5Chunks($input));
        }
    }

    public function testUpstreamBig5BufferDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x8F\x7F" => [$replacement, 0x7F],
            "\x81\x32" => [$replacement, 0x32],
            "\x81\xFF" => [$replacement],
            "\x81\xFF\xFE\x7E" => [$replacement, 0x24A8C],
            "\x81\x40\x32" => [$replacement, 0x40, 0x32],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeBig5Full($input, count($expected)));
            self::assertSame($expected, self::decodeBig5Chunks($input));
        }
    }

    public function testUpstreamBig5BufferDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = Big5::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (Big5Data::DECODE_INDEX as $pointer => $codePoint) {
            $result = Big5::decodeToBuffer(Big5::bytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(2, $result->offset);
        }
    }

    public function testUpstreamBig5BufferEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = Big5::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (Big5Data::ENCODE_INDEX as $codePoint => $pointer) {
            $result = Big5::encodeCodePointsToBuffer([$codePoint], 2);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(Big5::bytesForPointer($pointer), $result->bytes);
        }
    }

    public function testUpstreamBig5BufferEncodeBufferCheck(): void
    {
        $small = Big5::encodeCodePointsToBuffer([0x9AD0], 1);
        self::assertSame(Status::SmallBuffer, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = Big5::encodeCodePointsToBuffer([0x9AD0], 2);
        self::assertSame(Status::Ok, $encoded->status);
        self::assertSame("\xF7\xA4", $encoded->bytes);

        $special = Big5::decodeToBuffer("\x88\x62", 1);
        self::assertSame(Status::SmallBuffer, $special->status);
        self::assertSame([], $special->codePoints);
        self::assertSame(2, $special->offset);
        self::assertSame(0x00CA, $special->pendingFirstCodePoint);
        self::assertSame(0x0304, $special->pendingSecondCodePoint);

        $resumed = Big5::decodeToBuffer(
            '',
            2,
            pendingFirstCodePoint: $special->pendingFirstCodePoint,
            pendingSecondCodePoint: $special->pendingSecondCodePoint,
        );
        self::assertSame(Status::Ok, $resumed->status);
        self::assertSame([0x00CA, 0x0304], $resumed->codePoints);
    }

    /**
     * @return list<int>
     */
    private static function decodeBig5Full(string $input, int $capacity): array
    {
        $result = Big5::decodeToBuffer($input, $capacity);

        self::assertSame(Status::Ok, $result->status);
        self::assertSame(strlen($input), $result->offset);

        return $result->codePoints;
    }

    /**
     * @return list<int>
     */
    private static function decodeBig5Chunks(string $input): array
    {
        $codePoints = [];
        $pendingLeadByte = null;
        $pendingFirstCodePoint = null;
        $pendingSecondCodePoint = null;
        $capacity = strlen($input) + 2;

        for ($offset = 0, $length = strlen($input); $offset < $length; $offset++) {
            $result = Big5::decodeToBuffer(
                $input[$offset],
                $capacity,
                pendingLeadByte: $pendingLeadByte,
                pendingFirstCodePoint: $pendingFirstCodePoint,
                pendingSecondCodePoint: $pendingSecondCodePoint,
            );

            self::assertContains($result->status, [Status::Ok, Status::Continue]);

            array_push($codePoints, ...$result->codePoints);
            $pendingLeadByte = $result->pendingLeadByte;
            $pendingFirstCodePoint = $result->pendingFirstCodePoint;
            $pendingSecondCodePoint = $result->pendingSecondCodePoint;
        }

        if ($pendingLeadByte !== null) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        if ($pendingFirstCodePoint !== null && $pendingSecondCodePoint !== null) {
            $codePoints[] = $pendingFirstCodePoint;
            $codePoints[] = $pendingSecondCodePoint;
        }

        return $codePoints;
    }
}
