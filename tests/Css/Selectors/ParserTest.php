<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Selectors;

use Lexbor\Css\Selectors\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamSelectorsProvider(): iterable
    {
        yield 'selectors.c #1 type selector' => ['a', 'a', []];
        yield 'selectors.c #2 class selector' => ['.super', '.super', []];
        yield 'selectors.c #3 non-ascii class selector' => [".\xC3\x9C" . 'ber', ".\xC3\x9C" . 'ber', []];
        yield 'selectors.c #4 middle-dot class selector' => [".a\xC2\xB7" . 'b', ".a\xC2\xB7" . 'b', []];
        yield 'selectors.c #5 valid Greek class selector' => [".\xCD\xBD" . 'x', ".\xCD\xBD" . 'x', []];
        yield 'selectors.c #6 invalid division-sign class selector' => [".\xC3\xB7" . 'x', '', ["Syntax error. Selectors. Unexpected token: \xC3\xB7"]];
        yield 'selectors.c #7 invalid Greek class selector' => [".\xCD\xBE" . 'x', '', ["Syntax error. Selectors. Unexpected token: \xCD\xBE"]];
        yield 'selectors.c #8 semicolon after class dot' => ['.;x', '', ['Syntax error. Selectors. Unexpected token: ;']];
        yield 'selectors.c #9 trims selector whitespace' => [' .super ', '.super', []];
        yield 'selectors.c #10 unexpected percentage after class selector' => ['.super 1%', '', ['Syntax error. Selectors. Unexpected token: 1%']];
        yield 'selectors.c #11 hash selector' => ['#hash', '#hash', []];
        yield 'selectors.c #12 universal namespace universal selector' => ['*|*', '*|*', []];
        yield 'selectors.c #13 universal namespace type selector' => ['*|div', '*|div', []];
        yield 'selectors.c #14 empty namespace type selector' => ['|div', '|div', []];
        yield 'selectors.c #15 spaced universal namespace type selector' => ['* |div', '* |div', []];
        yield 'selectors.c #16 named namespace type selector' => ['html|div', 'html|div', []];
        yield 'selectors.c #17 spaced named namespace type selector' => ['html |div', 'html |div', []];
        yield 'selectors.c #18 attribute string selector' => ["[refs='link']", '[refs="link"]', []];
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('upstreamSelectorsProvider')]
    public function testUpstreamSelectorFixtures(string $selector, string $value, array $errors): void
    {
        self::assertSame(['value' => $value, 'errors' => $errors], (new Parser())->parse($selector));
    }
}
