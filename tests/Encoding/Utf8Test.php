<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Encoding\Utf8;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Utf8Test extends TestCase
{
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
}
