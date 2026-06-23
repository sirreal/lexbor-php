<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Utf8Test extends TestCase
{
    private const int BUFFER_CONTINUE_CODE_POINT = 0xFFFFFF;

    /**
     * @return iterable<string, array{string, string, string, string, string, string}>
     */
    public static function upstreamBomProvider(): iterable
    {
        yield 'encoding.c #1 valid BOMs' => [
            "\xEF\xBB\xBFЯΩ",
            'ЯΩ',
            "\xFE\xFFЯΩ",
            'ЯΩ',
            "\xFF\xFEЯΩ",
            'ЯΩ',
        ];
        yield 'encoding.c #2 partial BOM prefix' => [
            "\xEF\xBFЯΩ",
            "\xEF\xBFЯΩ",
            "\xFEЯΩ",
            "\xFEЯΩ",
            "\xFFЯΩ",
            "\xFFЯΩ",
        ];
        yield 'encoding.c #3 incomplete UTF-8 BOM only' => [
            "\xEFЯΩ",
            "\xEFЯΩ",
            'ЯΩ',
            'ЯΩ',
            'ЯΩ',
            'ЯΩ',
        ];
        yield 'encoding.c #4 no BOM or empty input' => [
            'ЯΩ',
            'ЯΩ',
            '',
            '',
            '',
            '',
        ];
    }

    #[DataProvider('upstreamBomProvider')]
    public function testUpstreamBomSkipping(
        string $utf8,
        string $utf8Expected,
        string $utf16Be,
        string $utf16BeExpected,
        string $utf16Le,
        string $utf16LeExpected,
    ): void {
        self::assertSame($utf8Expected, Utf8::skipUtf8Bom($utf8));
        self::assertSame($utf16BeExpected, Utf8::skipUtf16BeBom($utf16Be));
        self::assertSame($utf16LeExpected, Utf8::skipUtf16LeBom($utf16Le));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function upstreamUtf8EncodeProvider(): iterable
    {
        yield 'single/utf-8.c encode #1 one byte upper edge' => [0x7F, "\x7F"];
        yield 'single/utf-8.c encode #2 two byte lower edge' => [0x80, "\xC2\x80"];
        yield 'single/utf-8.c encode #3 two byte upper edge' => [0x07FF, "\xDF\xBF"];
        yield 'single/utf-8.c encode #4 three byte lower edge' => [0x0800, "\xE0\xA0\x80"];
        yield 'single/utf-8.c encode #5 three byte upper edge' => [0xFFFF, "\xEF\xBF\xBF"];
        yield 'single/utf-8.c encode #6 four byte lower edge' => [0x10000, "\xF0\x90\x80\x80"];
        yield 'single/utf-8.c encode #7 four byte upper edge' => [0x10FFFF, "\xF4\x8F\xBF\xBF"];
    }

    #[DataProvider('upstreamUtf8EncodeProvider')]
    public function testUpstreamUtf8SingleEncode(int $codePoint, string $expected): void
    {
        self::assertSame($expected, Utf8::encodeCodePoint($codePoint));
        self::assertSame(strlen($expected), strlen(Utf8::encodeCodePoint($codePoint)));
    }

    public function testUpstreamUtf8SingleEncodeRejectsOutOfRangeCodePoint(): void
    {
        $result = Utf8::encodeCodePointWithCapacity(0x110000, 4);
        self::assertSame(Utf8::ENCODE_ERROR, $result->status);
        self::assertSame('', $result->bytes);

        try {
            Utf8::encodeCodePoint(0x110000);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for out-of-range UTF-8 code point.');
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function upstreamUtf8DecodeProvider(): iterable
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $continue = Utf8::DECODE_CONTINUE;

        yield 'single/utf-8.c decode #1 ascii' => ["\x32", [0x32]];
        yield 'single/utf-8.c decode #2 invalid two-byte continuation' => ["\xC2\x32", [$replacement, 0x32]];
        yield 'single/utf-8.c decode #3 two-byte lower edge' => ["\xC2\x80", [0x80]];
        yield 'single/utf-8.c decode #4 two-byte upper body' => ["\xDF\x80", [0x07C0]];
        yield 'single/utf-8.c decode #5 three-byte lower edge' => ["\xE0\xA0\x80", [0x0800]];
        yield 'single/utf-8.c decode #6 repeated three-byte lower edge' => ["\xE0\xA0\x80", [0x0800]];
        yield 'single/utf-8.c decode #7 invalid E0 second then valid two-byte' => [
            "\xE0\x7F\xC2\x80",
            [$replacement, 0x7F, 0x80],
        ];
        yield 'single/utf-8.c decode #8 E0 upper second edge' => ["\xE0\xBF\x80", [0x0FC0]];
        yield 'single/utf-8.c decode #9 invalid E0 second and overlong lead' => [
            "\xE0\xC0\xC2\x80",
            [$replacement, $replacement, 0x80],
        ];
        yield 'single/utf-8.c decode #10 invalid E0 second then incomplete ED' => [
            "\xE0\xED\x80",
            [$replacement, $continue],
        ];
        yield 'single/utf-8.c decode #11 ED upper non-surrogate edge' => ["\xED\x9F\x80", [0xD7C0]];
        yield 'single/utf-8.c decode #12 surrogate sequence' => [
            "\xED\xA0\x80",
            [$replacement, $replacement, $replacement],
        ];
        yield 'single/utf-8.c decode #13 four-byte lower edge' => ["\xF0\x90\x80\x80", [0x10000]];
        yield 'single/utf-8.c decode #14 invalid F0 second' => [
            "\xF0\x8F\x80\x80",
            [$replacement, $replacement, $replacement, $replacement],
        ];
        yield 'single/utf-8.c decode #15 invalid F0 second then valid two-byte' => [
            "\xF0\x8F\x80\xC2\x80",
            [$replacement, $replacement, $replacement, 0x80],
        ];
        yield 'single/utf-8.c decode #16 F4 upper edge' => ["\xF4\x8F\x80\x80", [0x10F000]];
        yield 'single/utf-8.c decode #17 invalid F4 second' => [
            "\xF4\x90\x80\x80",
            [$replacement, $replacement, $replacement, $replacement],
        ];
        yield 'single/utf-8.c decode #18 invalid F4 second then valid two-byte' => [
            "\xF4\x90\x80\xC2\x80",
            [$replacement, $replacement, $replacement, 0x80],
        ];
        yield 'single/utf-8.c decode #19 invalid fourth byte resumes ascii' => [
            "\xF4\x8F\x80\x32",
            [$replacement, 0x32],
        ];
        yield 'single/utf-8.c decode #20 Cyrillic letters' => ["\xD0\xB8\xD0\xBD", [0x0438, 0x043D]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('upstreamUtf8DecodeProvider')]
    public function testUpstreamUtf8SingleDecode(string $input, array $expected): void
    {
        self::assertSame($expected, Utf8::decodeWithReplacement($input));
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function upstreamUtf8BufferDecodeProvider(): iterable
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $continue = self::BUFFER_CONTINUE_CODE_POINT;

        yield 'buffer/utf-8.c decode #1 ascii' => ["\x32", [0x32]];
        yield 'buffer/utf-8.c decode #2 invalid two-byte continuation' => ["\xC2\x32", [$replacement, 0x32]];
        yield 'buffer/utf-8.c decode #3 two-byte lower edge' => ["\xC2\x80", [0x80]];
        yield 'buffer/utf-8.c decode #4 two-byte upper body' => ["\xDF\x80", [0x07C0]];
        yield 'buffer/utf-8.c decode #5 three-byte lower edge' => ["\xE0\xA0\x80", [0x0800]];
        yield 'buffer/utf-8.c decode #6 repeated three-byte lower edge' => ["\xE0\xA0\x80", [0x0800]];
        yield 'buffer/utf-8.c decode #7 invalid E0 second then valid two-byte' => [
            "\xE0\x7F\xC2\x80",
            [$replacement, 0x7F, 0x80],
        ];
        yield 'buffer/utf-8.c decode #8 E0 upper second edge' => ["\xE0\xBF\x80", [0x0FC0]];
        yield 'buffer/utf-8.c decode #9 invalid E0 second and overlong lead' => [
            "\xE0\xC0\xC2\x80",
            [$replacement, $replacement, 0x80],
        ];
        yield 'buffer/utf-8.c decode #10 invalid E0 second then incomplete ED' => [
            "\xE0\xED\x80",
            [$replacement, $continue],
        ];
        yield 'buffer/utf-8.c decode #11 ED upper non-surrogate edge' => ["\xED\x9F\x80", [0xD7C0]];
        yield 'buffer/utf-8.c decode #12 surrogate sequence' => [
            "\xED\xA0\x80",
            [$replacement, $replacement, $replacement],
        ];
        yield 'buffer/utf-8.c decode #13 four-byte lower edge' => ["\xF0\x90\x80\x80", [0x10000]];
        yield 'buffer/utf-8.c decode #14 invalid F0 second' => [
            "\xF0\x8F\x80\x80",
            [$replacement, $replacement, $replacement, $replacement],
        ];
        yield 'buffer/utf-8.c decode #15 invalid F0 second then valid two-byte' => [
            "\xF0\x8F\x80\xC2\x80",
            [$replacement, $replacement, $replacement, 0x80],
        ];
        yield 'buffer/utf-8.c decode #16 F4 upper edge' => ["\xF4\x8F\x80\x80", [0x10F000]];
        yield 'buffer/utf-8.c decode #17 invalid F4 second' => [
            "\xF4\x90\x80\x80",
            [$replacement, $replacement, $replacement, $replacement],
        ];
        yield 'buffer/utf-8.c decode #18 invalid F4 second then valid two-byte' => [
            "\xF4\x90\x80\xC2\x80",
            [$replacement, $replacement, $replacement, 0x80],
        ];
        yield 'buffer/utf-8.c decode #19 invalid fourth byte resumes ascii' => [
            "\xF4\x8F\x80\x32",
            [$replacement, 0x32],
        ];
        yield 'buffer/utf-8.c decode #20 Cyrillic letters' => ["\xD0\xB8\xD0\xBD", [0x0438, 0x043D]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('upstreamUtf8BufferDecodeProvider')]
    public function testUpstreamUtf8BufferDecode(string $input, array $expected): void
    {
        self::assertSame($expected, self::decodeUtf8Full($input, count($expected)));
        self::assertSame($expected, self::decodeUtf8Chunks($input, count($expected)));
    }

    /**
     * @return iterable<string, array{int, Status, string}>
     */
    public static function upstreamUtf8BufferEncodeProvider(): iterable
    {
        yield 'buffer/utf-8.c encode #1 one byte upper edge' => [0x7F, Status::Ok, "\x7F"];
        yield 'buffer/utf-8.c encode #2 two byte lower edge' => [0x80, Status::Ok, "\xC2\x80"];
        yield 'buffer/utf-8.c encode #3 two byte upper edge' => [0x07FF, Status::Ok, "\xDF\xBF"];
        yield 'buffer/utf-8.c encode #4 three byte lower edge' => [0x0800, Status::Ok, "\xE0\xA0\x80"];
        yield 'buffer/utf-8.c encode #5 three byte upper edge' => [0xFFFF, Status::Ok, "\xEF\xBF\xBF"];
        yield 'buffer/utf-8.c encode #6 four byte lower edge' => [0x10000, Status::Ok, "\xF0\x90\x80\x80"];
        yield 'buffer/utf-8.c encode #7 four byte upper edge' => [0x10FFFF, Status::Ok, "\xF4\x8F\xBF\xBF"];
        yield 'buffer/utf-8.c encode #8 out-of-range error' => [0x110000, Status::Error, ''];
    }

    #[DataProvider('upstreamUtf8BufferEncodeProvider')]
    public function testUpstreamUtf8BufferEncode(int $codePoint, Status $expectedStatus, string $expectedBytes): void
    {
        $result = Utf8::encodeCodePointsToBuffer([$codePoint], 4);

        self::assertSame($expectedStatus, $result->status);
        self::assertSame($expectedBytes, $result->bytes);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function upstreamUtf8EncodeBufferProvider(): iterable
    {
        yield 'single/utf-8.c encode_buffer_check #1 two-byte small buffer' => [
            0x07FF,
            1,
            Utf8::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-8.c encode_buffer_check #2 two-byte exact buffer' => [0x07FF, 2, 2, "\xDF\xBF"];
        yield 'single/utf-8.c encode_buffer_check #3 three-byte one-byte buffer' => [
            0xFFFF,
            1,
            Utf8::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-8.c encode_buffer_check #4 three-byte two-byte buffer' => [
            0xFFFF,
            2,
            Utf8::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-8.c encode_buffer_check #5 three-byte exact buffer' => [0xFFFF, 3, 3, "\xEF\xBF\xBF"];
        yield 'single/utf-8.c encode_buffer_check #6 four-byte one-byte buffer' => [
            0x10FFFF,
            1,
            Utf8::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-8.c encode_buffer_check #7 four-byte two-byte buffer' => [
            0x10FFFF,
            2,
            Utf8::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-8.c encode_buffer_check #8 four-byte three-byte buffer' => [
            0x10FFFF,
            3,
            Utf8::ENCODE_SMALL_BUFFER,
            '',
        ];
        yield 'single/utf-8.c encode_buffer_check #9 four-byte exact buffer' => [
            0x10FFFF,
            4,
            4,
            "\xF4\x8F\xBF\xBF",
        ];
    }

    #[DataProvider('upstreamUtf8EncodeBufferProvider')]
    public function testUpstreamUtf8SingleEncodeBufferCheck(
        int $codePoint,
        int $capacity,
        int $expectedStatus,
        string $expectedBytes,
    ): void {
        $result = Utf8::encodeCodePointWithCapacity($codePoint, $capacity);

        self::assertSame($expectedStatus, $result->status);
        self::assertSame($expectedBytes, $result->bytes);
    }

    /**
     * @return iterable<string, array{int, int, Status, string}>
     */
    public static function upstreamUtf8BufferEncodeBufferProvider(): iterable
    {
        yield 'buffer/utf-8.c encode_buffer_check #1 two-byte small buffer' => [
            0x07FF,
            1,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-8.c encode_buffer_check #2 two-byte exact buffer' => [0x07FF, 2, Status::Ok, "\xDF\xBF"];
        yield 'buffer/utf-8.c encode_buffer_check #3 three-byte one-byte buffer' => [
            0xFFFF,
            1,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-8.c encode_buffer_check #4 three-byte two-byte buffer' => [
            0xFFFF,
            2,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-8.c encode_buffer_check #5 three-byte exact buffer' => [0xFFFF, 3, Status::Ok, "\xEF\xBF\xBF"];
        yield 'buffer/utf-8.c encode_buffer_check #6 four-byte one-byte buffer' => [
            0x10FFFF,
            1,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-8.c encode_buffer_check #7 four-byte two-byte buffer' => [
            0x10FFFF,
            2,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-8.c encode_buffer_check #8 four-byte three-byte buffer' => [
            0x10FFFF,
            3,
            Status::SmallBuffer,
            '',
        ];
        yield 'buffer/utf-8.c encode_buffer_check #9 four-byte exact buffer' => [
            0x10FFFF,
            4,
            Status::Ok,
            "\xF4\x8F\xBF\xBF",
        ];
    }

    #[DataProvider('upstreamUtf8BufferEncodeBufferProvider')]
    public function testUpstreamUtf8BufferEncodeBufferCheck(
        int $codePoint,
        int $capacity,
        Status $expectedStatus,
        string $expectedBytes,
    ): void {
        $result = Utf8::encodeCodePointsToBuffer([$codePoint], $capacity);

        self::assertSame($expectedStatus, $result->status);
        self::assertSame($expectedBytes, $result->bytes);
    }

    /**
     * @return list<int>
     */
    private static function decodeUtf8Full(string $input, int $expectedCount): array
    {
        $result = Utf8::decodeToBuffer($input, $expectedCount);
        $codePoints = $result->codePoints;

        if ($result->status === Status::Continue) {
            $codePoints[] = self::BUFFER_CONTINUE_CODE_POINT;
        } else {
            self::assertSame(Status::Ok, $result->status);
        }

        self::assertSame(strlen($input), $result->offset);

        return $codePoints;
    }

    /**
     * @return list<int>
     */
    private static function decodeUtf8Chunks(string $input, int $expectedCount): array
    {
        $codePoints = [];
        $pendingCodePoint = null;
        $pendingNeed = 0;
        $pendingLower = 0;
        $pendingUpper = 0;
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $result = Utf8::decodeToBuffer(
                $input[$i],
                $expectedCount,
                pendingCodePoint: $pendingCodePoint,
                pendingNeed: $pendingNeed,
                pendingLower: $pendingLower,
                pendingUpper: $pendingUpper,
            );

            array_push($codePoints, ...$result->codePoints);

            if ($result->status === Status::Continue) {
                if ($i + 1 >= $length) {
                    $codePoints[] = self::BUFFER_CONTINUE_CODE_POINT;
                    break;
                }

                $pendingCodePoint = $result->pendingUtf8CodePoint;
                $pendingNeed = $result->pendingUtf8Need;
                $pendingLower = $result->pendingUtf8Lower;
                $pendingUpper = $result->pendingUtf8Upper;
                continue;
            }

            self::assertSame(Status::Ok, $result->status);
            $pendingCodePoint = null;
            $pendingNeed = 0;
            $pendingLower = 0;
            $pendingUpper = 0;
        }

        return $codePoints;
    }
}
