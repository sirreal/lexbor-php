<?php

declare(strict_types=1);

namespace Lexbor\Tests\Punycode;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;
use Lexbor\Encoding\Utf8;
use Lexbor\Punycode\Punycode;
use PHPUnit\Framework\TestCase;

final class PunycodeTest extends TestCase
{
    public function testEncodeCharacters(): void
    {
        self::assertSame('90ahpcsme', Punycode::encode('лексбор'));
    }

    public function testEncodeBigBuffer(): void
    {
        $input = str_repeat("\xD1\x91", intdiv(4096 * 4, 2));
        $encoded = Punycode::encode($input);

        self::assertNotSame('', $encoded);
        self::assertSame($input, Punycode::decode($encoded));
    }

    public function testEncodeBigBufferEdge(): void
    {
        $input = str_repeat("\xD1\x91", intdiv(4096 * 2, 2));
        $encoded = Punycode::encode($input);

        self::assertNotSame('', $encoded);
        self::assertSame($input, Punycode::decode($encoded));
    }

    public function testDecodeCharacters(): void
    {
        self::assertSame('лексбор', Punycode::decode('90ahpcsme'));
    }

    public function testDecodeBigBuffer(): void
    {
        $decoded = Punycode::decode(str_repeat('61a', intdiv(4096 * 6, 3)));

        self::assertNotSame('', $decoded);
    }

    public function testDecodeBigBufferEdge(): void
    {
        $decoded = Punycode::decode(str_repeat('61a', intdiv(4096 * 3, 3)));

        self::assertNotSame('', $decoded);
    }

    public function testCodePointApiRoundTrips(): void
    {
        $source = Utf8::decode('лексбор');
        $encoded = Punycode::encodeCodePoints($source);

        self::assertSame('90ahpcsme', $encoded);
        self::assertSame($source, Punycode::decodeToCodePoints($encoded));
    }

    public function testEncodeResultReportsUnchangedAsciiInput(): void
    {
        $result = Punycode::encodeResult('hello');

        self::assertSame('hello', $result->data);
        self::assertTrue($result->unchanged);
    }

    public function testEncodeResultReportsChangedNonAsciiInput(): void
    {
        $result = Punycode::encodeResult('bücher');

        self::assertSame('bcher-kva', $result->data);
        self::assertFalse($result->unchanged);
    }

    public function testDecodeRejectsLeadingDelimiterLikeUpstream(): void
    {
        $this->assertLexborStatus(Status::ErrorUnexpectedData, static fn () => Punycode::decode('-'));
    }

    public function testDecodeRejectsLeadingDelimiterBeforeDigitsLikeUpstream(): void
    {
        $this->assertLexborStatus(Status::ErrorUnexpectedData, static fn () => Punycode::decode('-abc'));
    }

    public function testDecodeRejectsInvalidDigit(): void
    {
        $this->assertLexborStatus(Status::ErrorUnexpectedData, static fn () => Punycode::decode('$'));
    }

    public function testEncodeUsesLexborUtf8ByteShapeDecoder(): void
    {
        $result = Punycode::encodeResult("\xC1\x81");

        self::assertSame('A', $result->data);
        self::assertTrue($result->unchanged);
    }

    public function testEncodeRejectsInvalidUtf8LeadingByte(): void
    {
        $this->assertLexborStatus(Status::ErrorUnexpectedData, static fn () => Punycode::encode("\x80"));
    }

    private function assertLexborStatus(Status $status, callable $operation): void
    {
        try {
            $operation();
        } catch (LexborException $exception) {
            self::assertSame($status, $exception->status);
            return;
        }

        self::fail(sprintf('Expected LexborException with status %s.', $status->value));
    }
}
