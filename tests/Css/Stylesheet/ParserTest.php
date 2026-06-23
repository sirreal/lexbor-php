<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Stylesheet;

use Lexbor\Css\Stylesheet\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function upstreamStylesheetSmokeProvider(): iterable
    {
        yield 'stylesheet.c deep_selectors' => [
            str_repeat(':not(', 583) . 'abc',
        ];
        yield 'stylesheet.c prepared_token' => [
            '2.2-.2-.2-.2-2-.2-.2-.2-.2-.2-.2-2-2-.2-.2-.2-2-.2-.2-.2-.'
            . '2-.2-.2-2-.2-.2-.2-2.2-.2-.2-.2-.2-.2-2-22-.2-.2-.2-2-.2-.'
            . '2-.2-.2-.2-.22-.2-.2-.2-2-.2-.2-.2-.2-.2-.2-2-.2-.2-.2-2.2'
            . '-.2-.2-.2-.2-.2-2-22-.2-.2-.2-2-.2-.2-.2-.2-.2-.2-2--2-.2-.22-.2.2.23',
        ];
        yield 'stylesheet.c eof_offset' => [
            'url((\\',
        ];
        yield 'stylesheet.c colon_lookup' => [
            "div {width\\\n: 100%",
        ];
        yield 'stylesheet.c deep_nested' => [
            str_repeat('{', 3998) . 'width: 2px',
        ];
    }

    #[DataProvider('upstreamStylesheetSmokeProvider')]
    public function testUpstreamStylesheetSmokeFixtures(string $css): void
    {
        $stylesheet = (new Parser())->parse($css);

        self::assertSame('stylesheet', $stylesheet['type']);
        self::assertCount(1, $stylesheet['rules']);
        self::assertSame('qualified-rule', $stylesheet['rules'][0]['type']);
    }
}
