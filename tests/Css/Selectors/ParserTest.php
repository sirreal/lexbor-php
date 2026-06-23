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
        yield 'selectors.c #32 EOF in compound attribute presence selector' => ['div[refs', 'div[refs]', ['Syntax error. Selectors. End Of File in attribute selector']];
        yield 'selectors.c #33 uppercase i attribute modifier' => ['[refs=link I]', '[refs="link"i]', []];
        yield 'selectors.c #34 uppercase s attribute modifier' => ['[refs=link S]', '[refs="link"s]', []];
        yield 'selectors.c #35 escaped i attribute modifier' => ['[refs=link \\69]', '[refs="link"i]', []];
        yield 'selectors.c #36 escaped uppercase i attribute modifier' => ['[refs=link \\049 ]', '[refs="link"i]', []];
        yield 'selectors.c #37 escaped s attribute modifier' => ['[refs=link \\000073]', '[refs="link"s]', []];
        yield 'selectors.c #38 escaped uppercase s attribute modifier' => ['[refs=link \\53]', '[refs="link"s]', []];
        yield 'selectors.c #39 rejects insensitive attribute modifier' => ['[refs=link insensitive]', '', ['Syntax error. Selectors. Unexpected token: insensitive']];
        yield 'selectors.c #40 rejects iZZZ attribute modifier' => ['[refs=link iZZZ]', '', ['Syntax error. Selectors. Unexpected token: iZZZ']];
        yield 'selectors.c #41 rejects sensitive attribute modifier' => ['[refs=link sensitive]', '', ['Syntax error. Selectors. Unexpected token: sensitive']];
        yield 'selectors.c #42 rejects uppercase Sensitive attribute modifier' => ['[refs=link Sensitive]', '', ['Syntax error. Selectors. Unexpected token: Sensitive']];
        yield 'selectors.c #43 rejects percentage attribute value' => ['[refs=0%]', '', ['Syntax error. Selectors. Unexpected token: 0%']];
        yield 'selectors.c #44 rejects empty attribute selector EOF' => ['[', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE']];
        yield 'selectors.c #45 rejects missing attribute value EOF' => ['[refs=', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE']];
        yield 'selectors.c #46 rejects missing attribute matcher EOF' => ['[refs~', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE']];
        yield 'selectors.c #47 rejects presence selector trailing ident' => ['[refs i]', '', ['Syntax error. Selectors. Unexpected token: i']];
        yield 'selectors.c #48 rejects string attribute name' => ["['refs']", '', ['Syntax error. Selectors. Unexpected token: "refs"']];
        yield 'selectors.c #49 descendant selector with attribute' => ['div #hash [refs=abc]', 'div #hash [refs="abc"]', []];
        yield 'selectors.c #50 rejects empty not pseudo function' => [':not()', '', ["Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #51 not pseudo function with type selector' => [':not(div)', ':not(div)', []];
        yield 'selectors.c #52 not pseudo function name is case-insensitive' => [':NoT(div)', ':not(div)', []];
        yield 'selectors.c #53 not pseudo function selector list' => [':not(div, #hash, .class)', ':not(div, #hash, .class)', []];
        yield 'selectors.c #54 not pseudo function normalizes selector list whitespace' => [':not( div,#hash,.class )', ':not(div, #hash, .class)', []];
        yield 'selectors.c #55 rejects invalid not selector after valid selector' => [':not(div, .class 1%)', '', ['Syntax error. Selectors. Unexpected token: 1%', "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #56 rejects invalid not selector before valid selector' => [':not(.class 1%, div)', '', ['Syntax error. Selectors. Unexpected token: 1%', "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #57 rejects invalid not selector between valid selectors' => [':not(div, .class 1%, #hash)', '', ['Syntax error. Selectors. Unexpected token: 1%', "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #58 rejects invalid nested not selector' => [':not(div, :not(1%), span)', '', ['Syntax error. Selectors. Unexpected token: 1%', "Syntax error. Selectors. Pseudo function can't be empty: not()", "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #59 rejects empty nested not selector' => [':not(div, :not(), span)', '', ["Syntax error. Selectors. Pseudo function can't be empty: not()", "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #60 deeply nested not selector' => [':not(div, :not(:not(:not(:not(:not(:not(:not(:not([x])))))))), span)', ':not(div, :not(:not(:not(:not(:not(:not(:not(:not([x])))))))), span)', []];
        yield 'selectors.c #61 nested not selector list' => [':not(div, :not(.class, :not([x]), #hash), span)', ':not(div, :not(.class, :not([x]), #hash), span)', []];
        yield 'selectors.c #62 EOF in nested not selector list' => [':not(div, :not(.class, :not([x], #hash), span)', ':not(div, :not(.class, :not([x], #hash), span))', ['Syntax error. Selectors. End Of File in pseudo function']];
        yield 'selectors.c #63 EOF in nested not selector' => [':not(div, :not(div', ':not(div, :not(div))', ['Syntax error. Selectors. End Of File in pseudo function', 'Syntax error. Selectors. End Of File in pseudo function']];
        yield 'selectors.c #64 EOF in empty nested not selector' => [':not(div, :not(', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE', 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()", 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #65 has selector skips empty selector after comma' => [':has(div,, .class)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: ,']];
        yield 'selectors.c #66 has selector skips leading and repeated empty selectors' => [':has(,div,, .class,)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: ,', 'Syntax error. Selectors. Unexpected token: ,']];
        yield 'selectors.c #67 has selector skips invalid nested not selector' => [':has(div, :not(1%), .class)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: 1%', "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #68 has selector skips invalid class selector' => [':has(div, .class 1%)', ':has(div)', ['Syntax error. Selectors. Unexpected token: 1%']];
        yield 'selectors.c #69 has selector keeps valid selector after invalid selector' => [':has(div, .class 1%, #hash)', ':has(div, #hash)', ['Syntax error. Selectors. Unexpected token: 1%']];
        yield 'selectors.c #70 rejects unknown pseudo function' => [':godofwar(div)', '', ['Syntax error. Selectors. Unexpected token: godofwar(']];
        yield 'selectors.c #71 has selector skips unknown pseudo function' => [':has(div, :godofwar(div), .class)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: godofwar(']];
        yield 'selectors.c #72 has selector skips invalid nested not with blocks' => [':has(div, :not(1% {la}, (be), [], :fun(x)), .class)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: 1%', "Syntax error. Selectors. Pseudo function can't be empty: not()"]];
        yield 'selectors.c #73 has selector skips invalid nested has with blocks' => [':has(div, :has(1% {la}, (be), [], :fun(x)), .class)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: 1%', 'Syntax error. Selectors. Unexpected token: (', 'Syntax error. Selectors. Unexpected token: ]', 'Syntax error. Selectors. Unexpected token: fun(', "Syntax error. Selectors. Pseudo function can't be empty: has()"]];
        yield 'selectors.c #74 has selector skips unknown unclosed pseudo function' => [':has(div, :godofwar(', ':has(div)', ['Syntax error. Selectors. Unexpected token: godofwar(', 'Syntax error. Selectors. End Of File in pseudo function']];
        yield 'selectors.c #75 has selector keeps EOF-recovered nested not selector' => [':has(div, :not(div', ':has(div, :not(div))', ['Syntax error. Selectors. End Of File in pseudo function', 'Syntax error. Selectors. End Of File in pseudo function']];
        yield 'selectors.c #76 has selector skips empty EOF nested not selector' => [':has(div, :not(', ':has(div)', ['Syntax error. Selectors. Unexpected token: END-OF-FILE', 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()", 'Syntax error. Selectors. End Of File in pseudo function']];
        yield 'selectors.c #77 has selector skips doubly empty EOF nested not selector' => [':has(div, :not(:not(', ':has(div)', ['Syntax error. Selectors. Unexpected token: END-OF-FILE', 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()", 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()", 'Syntax error. Selectors. End Of File in pseudo function']];
        yield 'selectors.c #78 rejects has selector with only invalid EOF nested not selector' => [':has(:not(:not(', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE', 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()", 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: not()", 'Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: has()"]];
        yield 'selectors.c #79 rejects has selector with unknown pseudo and extra close' => [':has(div, :godofwar()))', '', ['Syntax error. Selectors. Unexpected token: godofwar(', 'Syntax error. Selectors. Unexpected token: )']];
        yield 'selectors.c #80 has selector skips nested unknown pseudo function' => [':has(div, :godofwar(:godofwar()))', ':has(div)', ['Syntax error. Selectors. Unexpected token: godofwar(']];
        yield 'selectors.c #81 rejects has selector with nested unknown pseudo and extra close' => [':has(div, :godofwar(:godofwar())))', '', ['Syntax error. Selectors. Unexpected token: godofwar(', 'Syntax error. Selectors. Unexpected token: )']];
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
