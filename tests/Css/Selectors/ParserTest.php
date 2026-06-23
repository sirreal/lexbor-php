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
        yield 'selectors.c #82 selector list with attribute selector' => ["div, [refs='link'], #hash", 'div, [refs="link"], #hash', []];
        yield 'selectors.c #83 rejects invalid selector in list' => ['div, .class 1%, #hash', '', ['Syntax error. Selectors. Unexpected token: 1%']];
        yield 'selectors.c #84 rejects combinator before selector-list comma' => ['div, .class >, #hash', '', ['Syntax error. Selectors. Unexpected token: ,']];
        yield 'selectors.c #85 rejects leading child combinator' => ['> .class', '', ['Syntax error. Selectors. Unexpected token: >']];
        yield 'selectors.c #86 rejects trailing child combinator' => ['.class >', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE']];
        yield 'selectors.c #87 rejects leading selector-list comma' => [', .class', '', ['Syntax error. Selectors. Unexpected token: ,']];
        yield 'selectors.c #88 rejects trailing selector-list comma' => ['.class ,', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE']];
        yield 'selectors.c #89 rejects leading combinator in selector-list item' => ['div, > .class, #hash', '', ['Syntax error. Selectors. Unexpected token: >']];
        yield 'selectors.c #90 complex combinator selector' => ['div > .class + #hash ~ [refs=a].super || #id', 'div > .class + #hash ~ [refs="a"].super || #id', []];
        yield 'selectors.c #91 pseudo class on type selector' => ['div:disabled', 'div:disabled', []];
        yield 'selectors.c #92 rejects unknown pseudo class' => [':godofwar', '', ['Syntax error. Selectors. Unexpected token: godofwar']];
        yield 'selectors.c #93 has selector with pseudo class' => [':has(:disabled)', ':has(:disabled)', []];
        yield 'selectors.c #94 rejects has selector with unknown pseudo class' => [':has(:godofwar)', '', ['Syntax error. Selectors. Unexpected token: godofwar', "Syntax error. Selectors. Pseudo function can't be empty: has()"]];
        yield 'selectors.c #95 rejects unknown pseudo element' => ['::godofwar', '', ['Syntax error. Selectors. Unexpected token: godofwar']];
        yield 'selectors.c #96 rejects has selector with unknown pseudo element' => [':has(::godofwar)', '', ['Syntax error. Selectors. Unexpected token: godofwar', "Syntax error. Selectors. Pseudo function can't be empty: has()"]];
        yield 'selectors.c #97 has selector skips unknown pseudo element' => [':has(div, ::godofwar)', ':has(div)', ['Syntax error. Selectors. Unexpected token: godofwar']];
        yield 'selectors.c #98 has selector skips unknown pseudo element between valid selectors' => [':has(div, ::godofwar, .class)', ':has(div, .class)', ['Syntax error. Selectors. Unexpected token: godofwar']];
        yield 'selectors.c #99 rejects empty selector' => ['', '', ['Syntax error. Selectors. Unexpected token: END-OF-FILE']];
        yield 'selectors.c #105 nth-child with An+B expression' => [':nth-child(2n+2)', ':nth-child(2n+2)', []];
        yield 'selectors.c #106 rejects incomplete nth-child An+B expression' => [':nth-child(2n+)', '', ["Syntax error. Selectors. Pseudo function can't be empty: nth-child()"]];
        yield 'selectors.c #107 rejects has selector with invalid nth-child' => [':has(:nth-child(2n+))', '', ["Syntax error. Selectors. Pseudo function can't be empty: nth-child()", "Syntax error. Selectors. Pseudo function can't be empty: has()"]];
        yield 'selectors.c #108 rejects EOF in invalid nth-child' => [':nth-child(2n+', '', ['Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: nth-child()"]];
        yield 'selectors.c #109 has selector skips invalid nth-child after valid selector' => [':has(div, :nth-child(2n+))', ':has(div)', ["Syntax error. Selectors. Pseudo function can't be empty: nth-child()"]];
        yield 'selectors.c #110 has selector skips EOF invalid nth-child after valid selector' => [':has(div, :nth-child(2n+', ':has(div)', ['Syntax error. Selectors. End Of File in pseudo function', "Syntax error. Selectors. Pseudo function can't be empty: nth-child()", 'Syntax error. Selectors. End Of File in pseudo function']];
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamRelativeSelectorsProvider(): iterable
    {
        yield 'selectors.c #100 relative child selector' => ['> div.class', '> div.class', []];
        yield 'selectors.c #101 relative next-sibling selector' => ['+ div.class', '+ div.class', []];
        yield 'selectors.c #102 relative following-sibling selector' => ['~ div.class', '~ div.class', []];
        yield 'selectors.c #103 relative column selector' => ['|| div.class', '|| div.class', []];
        yield 'selectors.c #104 relative complex combinator selector' => ['> div > .class + #hash ~ [refs=a].super || #id', '> div > .class + #hash ~ [refs="a"].super || #id', []];
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('upstreamSelectorsProvider')]
    public function testUpstreamSelectorFixtures(string $selector, string $value, array $errors): void
    {
        self::assertSame(['value' => $value, 'errors' => $errors], (new Parser())->parse($selector));
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('upstreamRelativeSelectorsProvider')]
    public function testUpstreamRelativeSelectorFixtures(string $selector, string $value, array $errors): void
    {
        self::assertSame(['value' => $value, 'errors' => $errors], (new Parser())->parseRelativeList($selector));
    }

    public function testAttributePresenceRejectsTrailingTokens(): void
    {
        $expected = ['value' => '', 'errors' => ['Syntax error. Selectors. Unexpected token: junk']];

        self::assertSame($expected, (new Parser())->parse('[refs]junk'));
        self::assertSame($expected, (new Parser())->parse('[refs] junk'));
    }

    public function testSelectorListReportsCommaAfterColumnCombinator(): void
    {
        self::assertSame(
            ['value' => '', 'errors' => ['Syntax error. Selectors. Unexpected token: ,']],
            (new Parser())->parse('div, .class ||, #hash'),
        );
    }

    public function testNthChildRejectsFractionalAnPlusBNumbers(): void
    {
        self::assertSame(['value' => ':nth-child(even)', 'errors' => []], (new Parser())->parse(':nth-child(+2n)'));
        self::assertSame(['value' => ':nth-child(odd)', 'errors' => []], (new Parser())->parse(':nth-child(+2n+1)'));
        self::assertSame(['value' => ':nth-child(even)', 'errors' => []], (new Parser())->parse(':nth-child(02n)'));
        self::assertSame(['value' => ':nth-child(+n+1)', 'errors' => []], (new Parser())->parse(':nth-child(1n+01)'));

        self::assertSame(
            [
                'value' => '',
                'errors' => ['Syntax error. Selectors. Unexpected token: 2.0n', "Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
            ],
            (new Parser())->parse(':nth-child(2.0n)'),
        );

        self::assertSame(
            [
                'value' => '',
                'errors' => ['Syntax error. Selectors. Unexpected token: 1.0', "Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
            ],
            (new Parser())->parse(':nth-child(1n+1.0)'),
        );

        self::assertSame(
            [
                'value' => '',
                'errors' => ['Syntax error. Selectors. Unexpected token: 2.0\\6e', "Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
            ],
            (new Parser())->parse(':nth-child(2.0\\6e)'),
        );

        self::assertSame(
            [
                'value' => '',
                'errors' => ['Syntax error. Selectors. Unexpected token: 1.0\\6e+1', "Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
            ],
            (new Parser())->parse(':nth-child(1.0\\6e+1)'),
        );

        self::assertSame(
            [
                'value' => '',
                'errors' => ["Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
            ],
            (new Parser())->parse(':nth-child(2e0\\6e)'),
        );

        self::assertSame(
            [
                'value' => '',
                'errors' => ["Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
            ],
            (new Parser())->parse(':nth-child(+2e0\\6e+1)'),
        );

        foreach (['.0\\6e', '+.0\\6e', '-.0\\6e'] as $source) {
            self::assertSame(
                [
                    'value' => '',
                    'errors' => ['Syntax error. Selectors. Unexpected token: ' . $source, "Syntax error. Selectors. Pseudo function can't be empty: nth-child()"],
                ],
                (new Parser())->parse(':nth-child(' . $source . ')'),
            );
        }
    }

    public function testNthChildAcceptsEscapedAnPlusBKeywords(): void
    {
        self::assertSame(['value' => ':nth-child(odd)', 'errors' => []], (new Parser())->parse(':nth-child(o\\64 d)'));
        self::assertSame(['value' => ':nth-child(even)', 'errors' => []], (new Parser())->parse(':nth-child(e\\76 en)'));
        self::assertSame(['value' => ':nth-child(odd)', 'errors' => []], (new Parser())->parse(':nth-child(2\\6e+1)'));
        self::assertSame(['value' => ':nth-child(odd)', 'errors' => []], (new Parser())->parse(':nth-child(+2\\6e+1)'));
        self::assertSame(['value' => ':nth-child(+n-1)', 'errors' => []], (new Parser())->parse(':nth-child(1n-\\31)'));
        self::assertSame(['value' => ':nth-child(+n-1)', 'errors' => []], (new Parser())->parse(':nth-child(1n\\2d 1)'));
        self::assertSame(['value' => ':nth-child(+n+1)', 'errors' => []], (new Parser())->parse(':nth-child(1n\\2b 1)'));
    }
}
