<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

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
}
