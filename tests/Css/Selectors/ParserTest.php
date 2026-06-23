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
        yield 'selectors.c #19 spaced attribute string selector' => [" [ refs = 'link' ] ", '[refs="link"]', []];
        yield 'selectors.c #20 double-quoted attribute string selector' => ['[refs="link"]', '[refs="link"]', []];
        yield 'selectors.c #21 attribute string selector with tight i modifier' => ["[refs='link'i]", '[refs="link"i]', []];
        yield 'selectors.c #22 attribute string selector with spaced i modifier' => ["[refs='link' i]", '[refs="link"i]', []];
        yield 'selectors.c #23 attribute string selector with trailing spaced i modifier' => ["[refs='link' i ]", '[refs="link"i]', []];
        yield 'selectors.c #24 attribute presence selector' => ['[refs]', '[refs]', []];
        yield 'selectors.c #25 unquoted attribute value selector' => ['[refs=link]', '[refs="link"]', []];
        yield 'selectors.c #26 unquoted attribute value selector with i modifier' => ['[refs=link i]', '[refs="link"i]', []];
        yield 'selectors.c #27 attribute string selector escapes double quotes' => ['[refs=\'a"b"c\']', '[refs="a\\000022b\\000022c"]', []];
        yield 'selectors.c #28 EOF in attribute presence selector' => ['[refs', '[refs]', ['Syntax error. Selectors. End Of File in attribute selector']];
        yield 'selectors.c #29 EOF in unquoted attribute value selector' => ['[refs=link', '[refs="link"]', ['Syntax error. Selectors. End Of File in attribute selector']];
        yield 'selectors.c #30 EOF in string attribute value selector' => ["[refs='a b", '[refs="a b"]', ['Syntax error. Selectors. End Of File in attribute selector']];
        yield 'selectors.c #31 EOF after attribute i modifier' => ['[refs=link i', '[refs="link"i]', ['Syntax error. Selectors. End Of File in attribute selector']];
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('upstreamSelectorsProvider')]
    public function testUpstreamSelectorFixtures(string $selector, string $value, array $errors): void
    {
        self::assertSame(['value' => $value, 'errors' => $errors], (new Parser())->parse($selector));
    }

    public function testAttributePresenceRejectsTrailingTokens(): void
    {
        $expected = ['value' => '', 'errors' => ['Syntax error. Selectors. Unexpected token: junk']];

        self::assertSame($expected, (new Parser())->parse('[refs]junk'));
        self::assertSame($expected, (new Parser())->parse('[refs] junk'));
    }
}
