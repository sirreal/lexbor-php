<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\Status;
use Lexbor\Encoding\Utf16;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Utf16Test extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function upstreamUtf16BigEndianDecodeProvider(): iterable
    {
        $replacement = Utf16::REPLACEMENT_CODE_POINT;

        yield 'single/utf-16.c decode_be #1 BMP upper body' => ["\x9F\xFF", [0x9FFF]];
        yield 'single/utf-16.c decode_be #2 U+0080' => ["\x00\x80", [0x0080]];
        yield 'single/utf-16.c decode_be #3 ascii' => ["\x00\x32", [0x0032]];
        yield 'single/utf-16.c decode_be #4 surrogate lower edge' => ["\xD8\x00\xDC\x00", [0x10000]];
        yield 'single/utf-16.c decode_be #5 surrogate upper edge' => ["\xDB\xFF\xDF\xFF", [0x10FFFF]];
        yield 'single/utf-16.c decode_be #6 two low surrogates' => [
            "\xDC\x00\xDC\x00",
            [$replacement, $replacement],
        ];
        yield 'single/utf-16.c decode_be #7 low surrogate then valid pair' => [
            "\xDC\x00\xD8\x00\xDC\x00",
            [$replacement, 0x10000],
        ];
        yield 'single/utf-16.c decode_be #8 two upper-edge pairs' => [
            "\xDB\xFF\xDF\xFF\xDB\xFF\xDF\xFF",
            [0x10FFFF, 0x10FFFF],
        ];
        yield 'single/utf-16.c decode_be_prepend #1 high surrogate before valid pair' => [
            "\xD8\x00\xD8\x00\xDC\x00",
            [$replacement, 0x10000],
        ];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('upstreamUtf16BigEndianDecodeProvider')]
    public function testUpstreamUtf16BigEndianSingleDecode(string $input, array $expected): void
    {
        self::assertSame($expected, Utf16::decodeBigEndianWithReplacement($input));
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('upstreamUtf16BigEndianDecodeProvider')]
    public function testUpstreamUtf16BigEndianBufferDecode(string $input, array $expected): void
    {
        $result = Utf16::decodeBigEndianToBuffer($input, count($expected));

        self::assertSame(Status::Ok, $result->status);
        self::assertSame($expected, $result->codePoints);
        self::assertSame(strlen($input), $result->offset);
        self::assertNull($result->pendingLeadByte);
        self::assertNull($result->pendingSurrogate);
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function upstreamUtf16LittleEndianDecodeProvider(): iterable
    {
        $replacement = Utf16::REPLACEMENT_CODE_POINT;

        yield 'single/utf-16.c decode_le #1 BMP upper body' => ["\xFF\x9F", [0x9FFF]];
        yield 'single/utf-16.c decode_le #2 U+0080' => ["\x80\x00", [0x0080]];
        yield 'single/utf-16.c decode_le #3 ascii' => ["\x32\x00", [0x0032]];
        yield 'single/utf-16.c decode_le #4 surrogate lower edge' => ["\x00\xD8\x00\xDC", [0x10000]];
        yield 'single/utf-16.c decode_le #5 surrogate upper edge' => ["\xFF\xDB\xFF\xDF", [0x10FFFF]];
        yield 'single/utf-16.c decode_le #6 two low surrogates' => [
            "\x00\xDC\x00\xDC",
            [$replacement, $replacement],
        ];
        yield 'single/utf-16.c decode_le #7 low surrogate then valid pair' => [
            "\x00\xDC\x00\xD8\x00\xDC",
            [$replacement, 0x10000],
        ];
        yield 'single/utf-16.c decode_le_prepend #1 high surrogate before valid pair' => [
            "\x00\xD8\x00\xD8\x00\xDC",
            [$replacement, 0x10000],
        ];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('upstreamUtf16LittleEndianDecodeProvider')]
    public function testUpstreamUtf16LittleEndianSingleDecode(string $input, array $expected): void
    {
        self::assertSame($expected, Utf16::decodeLittleEndianWithReplacement($input));
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('upstreamUtf16LittleEndianDecodeProvider')]
    public function testUpstreamUtf16LittleEndianBufferDecode(string $input, array $expected): void
    {
        $result = Utf16::decodeLittleEndianToBuffer($input, count($expected));

        self::assertSame(Status::Ok, $result->status);
        self::assertSame($expected, $result->codePoints);
        self::assertSame(strlen($input), $result->offset);
        self::assertNull($result->pendingLeadByte);
        self::assertNull($result->pendingSurrogate);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function upstreamUtf16BufferPrependProvider(): iterable
    {
        yield 'buffer/utf-16.c decode_be_buffer_check_prepend' => ["\xD8\x00\x00\x41", true];
        yield 'buffer/utf-16.c decode_le_buffer_check_prepend' => ["\x00\xD8\x41\x00", false];
    }

    #[DataProvider('upstreamUtf16BufferPrependProvider')]
    public function testUpstreamUtf16BufferDecodePrependResume(string $input, bool $bigEndian): void
    {
        $decode = $bigEndian
            ? Utf16::decodeBigEndianToBuffer(...)
            : Utf16::decodeLittleEndianToBuffer(...);

        $first = $decode($input, 1);
        self::assertSame(Status::SmallBuffer, $first->status);
        self::assertSame([Utf16::REPLACEMENT_CODE_POINT], $first->codePoints);
        self::assertSame(3, $first->offset);
        self::assertSame($bigEndian ? 0x00 : 0x41, $first->pendingLeadByte);
        self::assertNull($first->pendingSurrogate);

        $resume = $decode($input, 1, $first->offset, $first->pendingLeadByte);
        self::assertSame(Status::Ok, $resume->status);
        self::assertSame([0x41], $resume->codePoints);
        self::assertSame(strlen($input), $resume->offset);
        self::assertNull($resume->pendingLeadByte);
        self::assertNull($resume->pendingSurrogate);
    }

    /**
     * @return iterable<string, array{string, string, bool, list<int>}>
     */
    public static function utf16BufferSurrogateChunkProvider(): iterable
    {
        yield 'big-endian valid surrogate pair split across chunks' => [
            "\xD8\x00",
            "\xDC\x00",
            true,
            [0x10000],
        ];
        yield 'little-endian valid surrogate pair split across chunks' => [
            "\x00\xD8",
            "\x00\xDC",
            false,
            [0x10000],
        ];
        yield 'big-endian invalid surrogate pair split across chunks' => [
            "\xD8\x00",
            "\x00\x41",
            true,
            [Utf16::REPLACEMENT_CODE_POINT, 0x41],
        ];
        yield 'little-endian invalid surrogate pair split across chunks' => [
            "\x00\xD8",
            "\x41\x00",
            false,
            [Utf16::REPLACEMENT_CODE_POINT, 0x41],
        ];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('utf16BufferSurrogateChunkProvider')]
    public function testUtf16BufferDecodePreservesPendingSurrogateAcrossChunks(
        string $firstChunk,
        string $secondChunk,
        bool $bigEndian,
        array $expected,
    ): void {
        $decode = $bigEndian
            ? Utf16::decodeBigEndianToBuffer(...)
            : Utf16::decodeLittleEndianToBuffer(...);

        $first = $decode($firstChunk, 2);
        self::assertSame(Status::Continue, $first->status);
        self::assertSame([], $first->codePoints);
        self::assertSame(strlen($firstChunk), $first->offset);
        self::assertNull($first->pendingLeadByte);
        self::assertSame(0xD800, $first->pendingSurrogate);

        $resume = $decode($secondChunk, 2, 0, null, $first->pendingSurrogate);
        self::assertSame(Status::Ok, $resume->status);
        self::assertSame($expected, $resume->codePoints);
        self::assertSame(strlen($secondChunk), $resume->offset);
        self::assertNull($resume->pendingLeadByte);
        self::assertNull($resume->pendingSurrogate);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function upstreamUtf16BigEndianEncodeProvider(): iterable
    {
        yield 'single/utf-16.c encode #1 BMP' => [0x9FFF, "\x9F\xFF"];
        yield 'single/utf-16.c encode #2 surrogate pair lower edge' => [0x10000, "\xD8\x00\xDC\x00"];
    }

    #[DataProvider('upstreamUtf16BigEndianEncodeProvider')]
    public function testUpstreamUtf16BigEndianSingleEncode(int $codePoint, string $expected): void
    {
        self::assertSame($expected, Utf16::encodeBigEndianCodePoint($codePoint));
    }

    #[DataProvider('upstreamUtf16BigEndianEncodeProvider')]
    public function testUpstreamUtf16BigEndianBufferEncode(int $codePoint, string $expected): void
    {
        $result = Utf16::encodeBigEndianCodePointsToBuffer([$codePoint], strlen($expected));

        self::assertSame(Status::Ok, $result->status);
        self::assertSame($expected, $result->bytes);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function upstreamUtf16BigEndianEncodeBufferProvider(): iterable
    {
        yield 'single/utf-16.c encode_buffer_check #1 four-byte one-byte buffer' => [
            0x10000,
            1,
            Utf16::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-16.c encode_buffer_check #2 four-byte two-byte buffer' => [
            0x10000,
            2,
            Utf16::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-16.c encode_buffer_check #3 four-byte three-byte buffer' => [
            0x10000,
            3,
            Utf16::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-16.c encode_buffer_check #4 four-byte exact buffer' => [
            0x10000,
            4,
            4,
            "\xD8\x00\xDC\x00",
        ];
    }

    #[DataProvider('upstreamUtf16BigEndianEncodeBufferProvider')]
    public function testUpstreamUtf16BigEndianSingleEncodeBufferCheck(
        int $codePoint,
        int $capacity,
        int $expectedStatus,
        string $expectedBytes,
    ): void {
        $result = Utf16::encodeBigEndianCodePointWithCapacity($codePoint, $capacity);

        self::assertSame($expectedStatus, $result->status);
        self::assertSame($expectedBytes, $result->bytes);
    }

    /**
     * @return iterable<string, array{int, int, Status, string}>
     */
    public static function upstreamUtf16BigEndianBufferEncodeBufferProvider(): iterable
    {
        yield 'buffer/utf-16.c encode_buffer_check #1 four-byte one-byte buffer' => [
            0x10000,
            1,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-16.c encode_buffer_check #2 four-byte two-byte buffer' => [
            0x10000,
            2,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-16.c encode_buffer_check #3 four-byte three-byte buffer' => [
            0x10000,
            3,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-16.c encode_buffer_check #4 four-byte exact buffer' => [
            0x10000,
            4,
            Status::Ok,
            "\xD8\x00\xDC\x00",
        ];
    }

    #[DataProvider('upstreamUtf16BigEndianBufferEncodeBufferProvider')]
    public function testUpstreamUtf16BigEndianBufferEncodeBufferCheck(
        int $codePoint,
        int $capacity,
        Status $expectedStatus,
        string $expectedBytes,
    ): void {
        $result = Utf16::encodeBigEndianCodePointsToBuffer([$codePoint], $capacity);

        self::assertSame($expectedStatus, $result->status);
        self::assertSame($expectedBytes, $result->bytes);
    }
}
