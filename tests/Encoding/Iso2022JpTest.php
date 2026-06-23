<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\DecodeResult;
use Lexbor\Encoding\Iso2022Jp;
use Lexbor\Encoding\Iso2022JpData;
use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\TestCase;

final class Iso2022JpTest extends TestCase
{
    public function testUpstreamIso2022JpSingleDecode(): void
    {
        foreach (self::decodeCases() as [$input, $expected]) {
            self::assertSame($expected, Iso2022Jp::decodeWithReplacement($input));
        }
    }

    public function testUpstreamIso2022JpSingleDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;

        self::assertSame([$replacement, 0x26, 0x32], Iso2022Jp::decodeWithReplacement("\x1B\x26\x32"));
        self::assertSame([$replacement, 0x24, 0x32], Iso2022Jp::decodeWithReplacement("\x1B\x24\x32"));
    }

    public function testUpstreamIso2022JpSingleDecodeMap(): void
    {
        foreach (self::mapFixtureRows() as [$bytes, $codePoint]) {
            self::assertSame([$codePoint], Iso2022Jp::decodeWithReplacement($bytes));
        }

        for ($byte = 0x21; $byte <= 0x5F; $byte++) {
            self::assertSame(
                [0xFF61 - 0x21 + $byte],
                Iso2022Jp::decodeWithReplacement("\x1B\x28\x49" . chr($byte)),
            );
        }
    }

    public function testUpstreamIso2022JpSingleEncodeMap(): void
    {
        foreach (self::mapFixtureRows() as [$bytes, $codePoint]) {
            self::assertSame($bytes, Iso2022Jp::encodeCodePoint($codePoint));
        }

        foreach (Iso2022JpData::KATAKANA_REVERSE_MAP as $codePoint => $jis0208CodePoint) {
            $pointer = Iso2022Jp::encodePointer($codePoint);
            self::assertNotNull($pointer);
            self::assertSame(Iso2022Jp::encodePointer($jis0208CodePoint), $pointer);
        }
    }

    public function testUpstreamIso2022JpSingleEncodeBufferCheck(): void
    {
        foreach ([1, 2, 3] as $capacity) {
            $small = Iso2022Jp::encodeCodePointWithCapacity(0x00A5, $capacity);
            self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
            self::assertSame('', $small->bytes);
        }

        $roman = Iso2022Jp::encodeCodePointWithCapacity(0x00A5, 4);
        self::assertSame(4, $roman->status);
        self::assertSame("\x1B\x28\x4A\x5C", $roman->bytes);

        foreach ([1, 2, 3, 4] as $capacity) {
            $small = Iso2022Jp::encodeCodePointWithCapacity(0xFF61, $capacity);
            self::assertSame(Utf8::ENCODE_SMALL_BUFFER, $small->status);
            self::assertSame('', $small->bytes);
        }

        $katakana = Iso2022Jp::encodeCodePointWithCapacity(0xFF61, 5);
        self::assertSame(5, $katakana->status);
        self::assertSame("\x1B\x24\x42\x21\x23", $katakana->bytes);

        self::assertSame(Utf8::ENCODE_ERROR, Iso2022Jp::encodeCodePointWithCapacity(0x001B, 1)->status);

        try {
            Iso2022Jp::encodeCodePoint(0x001B);
        } catch (LexborException $exception) {
            self::assertSame(Status::ErrorUnexpectedData, $exception->status);
            return;
        }

        self::fail('Expected LexborException for unmappable ISO-2022-JP code point.');
    }

    public function testUpstreamIso2022JpSingleEncodeSequence(): void
    {
        self::assertSame("\x1B\x28\x4A\x5C\x7E\x1B\x28\x42", Iso2022Jp::encodeCodePointsToBuffer([0x00A5, 0x203E], 8)->bytes);
        self::assertSame("\x1B\x28\x4A\x7E\x5C\x1B\x28\x42", Iso2022Jp::encodeCodePointsToBuffer([0x203E, 0x00A5], 8)->bytes);
    }

    public function testUpstreamIso2022JpBufferDecode(): void
    {
        foreach (self::decodeCases() as [$input, $expected]) {
            self::assertSame($expected, self::decodeIso2022JpFull($input, strlen($input) + 8));
            self::assertSame($expected, self::decodeIso2022JpChunks($input));
        }

        $exactCases = [
            ["A", [0x41], Iso2022Jp::STATE_ASCII],
            ["\x1B\x28\x4A\x5C", [0x00A5], Iso2022Jp::STATE_ROMAN],
            ["\x1B\x28\x49\x21", [0xFF61], Iso2022Jp::STATE_KATAKANA],
            ["\x1B\x24\x40\x21\x21", [0x3000], Iso2022Jp::STATE_LEAD],
        ];

        foreach ($exactCases as [$input, $expected, $expectedState]) {
            $result = Iso2022Jp::decodeToBuffer($input, count($expected));

            self::assertSame(Status::Ok, $result->status);
            self::assertSame($expected, $result->codePoints);
            self::assertSame(strlen($input), $result->offset);
            self::assertSame($expectedState, $result->pendingIso2022JpState);
        }
    }

    public function testUpstreamIso2022JpBufferDecodePrepend(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;
        $cases = [
            "\x1B\x26\x32" => [$replacement, 0x26, 0x32],
            "\x1B\x24\x32" => [$replacement, 0x24, 0x32],
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, self::decodeIso2022JpFull($input, strlen($input) + 8));
            self::assertSame($expected, self::decodeIso2022JpChunks($input));
        }
    }

    public function testUpstreamIso2022JpBufferDecodeEscapeChecks(): void
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;

        $dollar = Iso2022Jp::decodeToBuffer("\x1B\x24\x32", 1);
        self::assertSame(Status::SmallBuffer, $dollar->status);
        self::assertSame([$replacement], $dollar->codePoints);
        self::assertSame(2, $dollar->offset);
        self::assertSame(0x24, $dollar->pendingIso2022JpPrepend);

        $dollarResume = self::resumeDecode("\x1B\x24\x32", $dollar, 4);
        self::assertSame(Status::Ok, $dollarResume->status);
        self::assertSame([0x24, 0x32], $dollarResume->codePoints);
        self::assertSame(3, $dollarResume->offset);

        $paren = Iso2022Jp::decodeToBuffer("\x1B\x28\x32", 1);
        self::assertSame(Status::SmallBuffer, $paren->status);
        self::assertSame([$replacement], $paren->codePoints);
        self::assertSame(2, $paren->offset);
        self::assertSame(0x28, $paren->pendingIso2022JpPrepend);

        $parenResume = self::resumeDecode("\x1B\x28\x32", $paren, 4);
        self::assertSame(Status::Ok, $parenResume->status);
        self::assertSame([0x28, 0x32], $parenResume->codePoints);
        self::assertSame(3, $parenResume->offset);

        $asciiReset = Iso2022Jp::decodeToBuffer("\x1B\x28\x42", 1);
        self::assertSame(Status::Continue, $asciiReset->status);
        self::assertSame([], $asciiReset->codePoints);
        self::assertSame(3, $asciiReset->offset);
        self::assertSame(Iso2022Jp::STATE_ASCII, $asciiReset->pendingIso2022JpState);

        $finishedAsciiReset = Iso2022Jp::decodeFinish($asciiReset);
        self::assertSame(Status::Ok, $finishedAsciiReset->status);
        self::assertSame([], $finishedAsciiReset->codePoints);
    }

    public function testUpstreamIso2022JpBufferDecodeMap(): void
    {
        foreach (self::mapFixtureRows() as [$bytes, $codePoint]) {
            self::assertSame([$codePoint], self::decodeIso2022JpFull($bytes, 4));
        }
    }

    public function testUpstreamIso2022JpBufferEncodeMap(): void
    {
        foreach (self::mapFixtureRows() as [$bytes, $codePoint]) {
            $result = Iso2022Jp::encodeCodePointsToBuffer([$codePoint], 16);

            self::assertSame(Status::Ok, $result->status);
            self::assertSame($bytes, $result->bytes);
        }
    }

    public function testUpstreamIso2022JpBufferEncodeBufferCheck(): void
    {
        foreach ([1, 2, 3] as $capacity) {
            $small = Iso2022Jp::encodeCodePointsToBuffer([0x00A5], $capacity, finish: false);
            self::assertSame(Status::SmallBuffer, $small->status);
            self::assertSame('', $small->bytes);
        }

        $roman = Iso2022Jp::encodeCodePointsToBuffer([0x00A5], 4, finish: false);
        self::assertSame(Status::Ok, $roman->status);
        self::assertSame("\x1B\x28\x4A\x5C", $roman->bytes);

        foreach ([1, 2, 3, 4] as $capacity) {
            $small = Iso2022Jp::encodeCodePointsToBuffer([0xFF61], $capacity, finish: false);
            self::assertSame(Status::SmallBuffer, $small->status);
            self::assertSame('', $small->bytes);
        }

        $katakana = Iso2022Jp::encodeCodePointsToBuffer([0xFF61], 5, finish: false);
        self::assertSame(Status::Ok, $katakana->status);
        self::assertSame("\x1B\x24\x42\x21\x23", $katakana->bytes);
    }

    public function testUpstreamIso2022JpBufferEncodeSequenceAndSizeFix(): void
    {
        self::assertSame("\x1B\x28\x4A\x5C\x7E\x1B\x28\x42", Iso2022Jp::encodeCodePointsToBuffer([0x00A5, 0x203E], 1024)->bytes);
        self::assertSame("\x1B\x28\x4A\x7E\x5C\x1B\x28\x42", Iso2022Jp::encodeCodePointsToBuffer([0x203E, 0x00A5], 1024)->bytes);

        $sizeFix = Iso2022Jp::encodeCodePointsToBuffer([0x3042, 0x0400, 0x0400, 0x0400], 4096);
        self::assertSame(Status::Error, $sizeFix->status);
        self::assertLessThanOrEqual(4096, strlen($sizeFix->bytes));

        $jis0208ToAsciiEscape = Iso2022Jp::encodeCodePointsToBuffer([0x3042, 0x001B], 9);
        self::assertSame(Status::Ok, $jis0208ToAsciiEscape->status);
        self::assertSame("\x1B\x24\x42\x24\x22\x1B\x28\x42\x1B", $jis0208ToAsciiEscape->bytes);
    }

    /**
     * @return list<array{string, list<int>}>
     */
    private static function decodeCases(): array
    {
        $replacement = Utf8::REPLACEMENT_CODE_POINT;

        return [
            ["\x1B\x24\x40\x1B\x24\x40", [$replacement]],
            ["\x1B\x23", [$replacement, 0x23]],
            ["\x1B\x24\x40\x21\x1B", [$replacement]],
            ["\x1B\x24\x40\x21\x10\x21\x21", [$replacement, 0x3000]],
            ["\x1B\x24\x40\x21\x21", [0x3000]],
            ["\x1B\x24\x40\x20\x21\x21", [$replacement, 0x3000]],
            ["\x1B\x24\x40\x21\x20\x21\x21", [$replacement, 0x3000]],
            ["\x1B\x24\x40\x7E\x21", [$replacement]],
            ["\x1B\x24\x40\x7E\x7E", [$replacement]],
            ["\x1B\x23\x32", [$replacement, 0x23, 0x32]],
            ["\x1B\x28\x42\x32", [0x32]],
            ["\x32", [0x32]],
            ["\x1B\x28\x42\x0E", [$replacement]],
            ["\x1B\x28\x42\x0F", [$replacement]],
            ["\x1B\x28\x42\x0F\x1B\x24\x40\x21\x1B\x24\x40\x21\x21", [$replacement, $replacement, 0x3000]],
            ["\x1B\x28\x4A\x1B\x24\x40\x21\x21", [$replacement, 0x3000]],
            ["\x1B\x28\x4A\x1B\x24\x40\x21\x1B\x24\x40\x21\x21", [$replacement, $replacement, 0x3000]],
            ["\x1B\x28\x4A\x5C\x1B\x24\x40\x21\x1B\x24\x40\x21\x21", [0x00A5, $replacement, 0x3000]],
            ["\x1B\x28\x4A\x5C", [0x00A5]],
            ["\x1B\x28\x4A\x7E", [0x203E]],
            ["\x1B\x28\x4A\x32\x1B\x24\x40\x21\x1B\x24\x40\x21\x21", [0x32, $replacement, 0x3000]],
            ["\x1B\x28\x4A\x32", [0x32]],
            ["\x1B\x28\x4A\x0E", [$replacement]],
            ["\x1B\x28\x4A\x0F", [$replacement]],
            ["\x1B\x28\x4A\x0F\x1B\x24\x40\x21\x1B\x24\x40\x21\x21", [$replacement, $replacement, 0x3000]],
            ["\x1B\x28\x49\x1B\x24\x40\x21\x21", [$replacement, 0x3000]],
            ["\x1B\x28\x49\x21", [0xFF61]],
            ["\x1B\x28\x49\x20", [$replacement]],
            ["\x1B\x28\x49\x5F", [0xFF9F]],
            ["\x1B\x28\x49\x60", [$replacement]],
            ["\x1B\x28\x49\x0F\x1B\x24\x40\x21\x1B\x24\x40\x21\x21", [$replacement, $replacement, 0x3000]],
            ["\x1B\x28\x49\x5F\x1B\x28\x49\x5F", [0xFF9F, 0xFF9F]],
        ];
    }

    /**
     * @return list<array{string, int}>
     */
    private static function mapFixtureRows(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $path = dirname(__DIR__, 2) . '/upstream/lexbor/test/files/lexbor/encoding/iso_2022_jp_map_decode.txt';
        $rows = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^\s*((?:\\\\x[0-9A-Fa-f]{2})+)\s+0x([0-9A-Fa-f]+)/', $line, $matches) !== 1) {
                continue;
            }

            $rows[] = [
                preg_replace_callback(
                    '/\\\\x([0-9A-Fa-f]{2})/',
                    static fn (array $byte): string => chr(hexdec($byte[1])),
                    $matches[1],
                ),
                hexdec($matches[2]),
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private static function decodeIso2022JpFull(string $input, int $capacity): array
    {
        $result = Iso2022Jp::decodeToBuffer($input, $capacity);
        $codePoints = $result->codePoints;

        if ($result->status === Status::Continue) {
            if ($result->pendingIso2022JpState !== Iso2022Jp::STATE_ASCII) {
                $codePoints[] = Utf8::DECODE_CONTINUE;
            }

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
    private static function decodeIso2022JpChunks(string $input): array
    {
        $codePoints = [];
        $state = Iso2022Jp::STATE_ASCII;
        $outState = Iso2022Jp::STATE_ASCII;
        $outFlag = false;
        $lead = null;
        $prepend = null;
        $capacity = strlen($input) + 8;
        $lastStatus = Status::Ok;

        for ($offset = 0, $length = strlen($input); $offset < $length; $offset++) {
            $result = Iso2022Jp::decodeToBuffer(
                $input[$offset],
                $capacity,
                pendingIso2022JpState: $state,
                pendingIso2022JpOutState: $outState,
                pendingIso2022JpOutFlag: $outFlag,
                pendingIso2022JpLead: $lead,
                pendingIso2022JpPrepend: $prepend,
            );

            self::assertContains($result->status, [Status::Ok, Status::Continue]);

            array_push($codePoints, ...$result->codePoints);
            $lastStatus = $result->status;
            $state = $result->pendingIso2022JpState;
            $outState = $result->pendingIso2022JpOutState;
            $outFlag = $result->pendingIso2022JpOutFlag;
            $lead = $result->pendingIso2022JpLead;
            $prepend = $result->pendingIso2022JpPrepend;
        }

        if ($lastStatus === Status::Continue && $state !== Iso2022Jp::STATE_ASCII) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }

    private static function resumeDecode(string $input, DecodeResult $state, int $capacity): DecodeResult
    {
        return Iso2022Jp::decodeToBuffer(
            $input,
            $capacity,
            $state->offset,
            pendingIso2022JpState: $state->pendingIso2022JpState,
            pendingIso2022JpOutState: $state->pendingIso2022JpOutState,
            pendingIso2022JpOutFlag: $state->pendingIso2022JpOutFlag,
            pendingIso2022JpLead: $state->pendingIso2022JpLead,
            pendingIso2022JpPrepend: $state->pendingIso2022JpPrepend,
        );
    }
}
