<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\EucKr;
use Lexbor\Encoding\EucKrData;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class EucKrTest extends TestCase
{
    public function testUpstreamEucKrSingleDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x80" => [$replacement],
            "\xFF" => [$replacement],
            "\x81\x40" => [$replacement, 0x40],
            "\x81\xFF" => [$replacement],
            "\x81\x41" => [0xAC02],
            "\xFD\xFE" => [0x8A70],
            "\xFE\xFE" => [$replacement],
            "\xFD\xFE\xFD\xFE" => [0x8A70, 0x8A70],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, EucKr::decodeWithReplacement($input));
        }
    }

    public function testUpstreamEucKrSingleDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;

        self::assertSame([$replacement, 0x41], EucKr::decodeWithReplacement("\xFE\x41"));
        self::assertSame([$replacement], EucKr::decodeWithReplacement("\xFE\xFE"));
    }

    public function testUpstreamEucKrSingleDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            self::assertSame([$byte], EucKr::decodeWithReplacement(chr($byte)));
        }

        foreach (EucKrData::DECODE_INDEX as $pointer => $codePoint) {
            self::assertSame([$codePoint], EucKr::decodeWithReplacement(self::bytesForPointer($pointer)));
        }
    }

    public function testUpstreamEucKrSingleEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = EucKr::encodeCodePointWithCapacity($codePoint, 1);

            self::assertSame(1, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
            self::assertSame(chr($codePoint), EucKr::encodeCodePoint($codePoint));
        }

        foreach (EucKrData::DECODE_INDEX as $pointer => $codePoint) {
            $expected = self::bytesForPointer($pointer);
            $result = EucKr::encodeCodePointWithCapacity($codePoint, 2);

            self::assertSame(2, $result->status);
            self::assertSame($expected, $result->bytes);
            self::assertSame($expected, EucKr::encodeCodePoint($codePoint));
        }
    }

    public function testUpstreamEucKrSingleEncodeBufferCheck(): void
    {
        $small = EucKr::encodeCodePointWithCapacity(0x8A70, 1);
        self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = EucKr::encodeCodePointWithCapacity(0x8A70, 2);
        self::assertSame(2, $encoded->status);
        self::assertSame("\xFD\xFE", $encoded->bytes);

        self::assertSame(Utf8::ENCODE_ERROR, EucKr::encodeCodePointWithCapacity(0x0080, 2)->status);

        try {
            EucKr::encodeCodePoint(0x0080);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable EUC-KR code point.');
    }

    public function testUpstreamEucKrBufferDecode(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x58" => [0x58],
            "\x80" => [$replacement],
            "\xFF" => [$replacement],
            "\x81\x40" => [$replacement, 0x40],
            "\x81\xFF" => [$replacement],
            "\x81\x41" => [0xAC02],
            "\xFD\xFE" => [0x8A70],
            "\xFE\xFE" => [$replacement],
            "\xFD\xFE\xFD\xFE" => [0x8A70, 0x8A70],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeEucKrFull($input, count($expected)));
            self::assertSame($expected, self::decodeEucKrChunks($input));
        }
    }

    public function testUpstreamEucKrBufferDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;

        self::assertSame([$replacement, 0x41], self::decodeEucKrFull("\xFE\x41", 2));
        self::assertSame([$replacement, 0x41], self::decodeEucKrChunks("\xFE\x41"));
        self::assertSame([$replacement], self::decodeEucKrFull("\xFE\xFE", 1));
        self::assertSame([$replacement], self::decodeEucKrChunks("\xFE\xFE"));
    }

    public function testUpstreamEucKrBufferDecodeMap(): void
    {
        for ($byte = 0; $byte < 0x80; $byte++) {
            $result = EucKr::decodeToBuffer(chr($byte), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$byte], $result->codePoints);
            self::assertSame(1, $result->offset);
        }

        foreach (EucKrData::DECODE_INDEX as $pointer => $codePoint) {
            $result = EucKr::decodeToBuffer(self::bytesForPointer($pointer), 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame([$codePoint], $result->codePoints);
            self::assertSame(2, $result->offset);
        }
    }

    public function testUpstreamEucKrBufferEncodeMap(): void
    {
        for ($codePoint = 0; $codePoint < 0x80; $codePoint++) {
            $result = EucKr::encodeCodePointsToBuffer([$codePoint], 1);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(chr($codePoint), $result->bytes);
        }

        foreach (EucKrData::DECODE_INDEX as $pointer => $codePoint) {
            $result = EucKr::encodeCodePointsToBuffer([$codePoint], 2);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame(self::bytesForPointer($pointer), $result->bytes);
        }
    }

    public function testUpstreamEucKrBufferEncodeBufferCheck(): void
    {
        $small = EucKr::encodeCodePointsToBuffer([0x8A70], 1);
        self::assertSame(Status::SmallBuffer, $small->status);
        self::assertSame('', $small->bytes);

        $encoded = EucKr::encodeCodePointsToBuffer([0x8A70], 2);
        self::assertSame(Status::Ok, $encoded->status);
        self::assertSame("\xFD\xFE", $encoded->bytes);

        self::assertSame(Status::Error, EucKr::encodeCodePointsToBuffer([0x0080], 0)->status);
    }

    /**
     * @return list<int>
     */
    private static function decodeEucKrFull(string $input, int $capacity): array
    {
        $result = EucKr::decodeToBuffer($input, $capacity);

        self::assertSame(Status::Ok, $result->status);
        self::assertSame(strlen($input), $result->offset);

        return $result->codePoints;
    }

    /**
     * @return list<int>
     */
    private static function decodeEucKrChunks(string $input): array
    {
        $codePoints = [];
        $pendingLeadByte = null;
        $capacity = strlen($input) + 1;

        for ($offset = 0, $length = strlen($input); $offset < $length; $offset++) {
            $result = EucKr::decodeToBuffer($input[$offset], $capacity, pendingLeadByte: $pendingLeadByte);

            self::assertContains($result->status, [Status::Ok, Status::Continue]);

            array_push($codePoints, ...$result->codePoints);
            $pendingLeadByte = $result->pendingLeadByte;
        }

        if ($pendingLeadByte !== null) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }

    private static function bytesForPointer(int $pointer): string
    {
        return chr(intdiv($pointer, 190) + 0x81) . chr($pointer % 190 + 0x41);
    }
}
