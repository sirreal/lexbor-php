<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Selectors;

use Lexbor\Css\Selectors\SpecificityCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpecificityCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{a: int, b: int, c: int}}>
     */
    public static function upstreamSpecificityProvider(): iterable
    {
        yield 'specificity.c #1 id class type' => ['#id .class tag', ['a' => 1, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #2 two ids class type' => ['#id #id .class tag', ['a' => 2, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #3 id two classes type' => ['#id .class .class tag', ['a' => 1, 'b' => 2, 'c' => 1]];
        yield 'specificity.c #4 id class two types' => ['#id .class tag tag', ['a' => 1, 'b' => 1, 'c' => 2]];
        yield 'specificity.c #5 nth-child descendant of selector' => ['#id tag :nth-child(even of #item .class)', ['a' => 2, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #6 nth-child compound of selector' => ['#id tag :nth-child(even of #item.class)', ['a' => 2, 'b' => 2, 'c' => 1]];
        yield 'specificity.c #7 nth-child selector list' => ['#id tag :nth-child(even of #item, .class)', ['a' => 2, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #8 is selector list' => ['#id :is(#item.class, #id)', ['a' => 2, 'b' => 1, 'c' => 0]];
        yield 'specificity.c #9 nth-child without of selector' => ['#id tag :nth-child(even)', ['a' => 1, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #10 compound nth-child' => ['tag#id:nth-child(even)', ['a' => 1, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #11 attribute selector' => ['[a=b]', ['a' => 0, 'b' => 1, 'c' => 0]];
        yield 'specificity.c #12 unterminated attribute selector' => ['[a=b', ['a' => 0, 'b' => 1, 'c' => 0]];
        yield 'specificity.c #13 type with unterminated attribute selector' => ['tag[a', ['a' => 0, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #14 is id wins class' => ['#id tag :is(#item, .class)', ['a' => 2, 'b' => 0, 'c' => 1]];
        yield 'specificity.c #15 not id wins class' => ['#id tag :not(#item, .class)', ['a' => 2, 'b' => 0, 'c' => 1]];
        yield 'specificity.c #16 has id wins class' => ['#id tag :has(#item, .class)', ['a' => 2, 'b' => 0, 'c' => 1]];
        yield 'specificity.c #17 where is zero specificity' => ['#id tag :where(#item, .class)', ['a' => 1, 'b' => 0, 'c' => 1]];
        yield 'specificity.c #18 nested is specificity' => ['#id:is(#item:is(#new))', ['a' => 2, 'b' => 0, 'c' => 0]];
        yield 'specificity.c #19 universal selector' => ['*', ['a' => 0, 'b' => 0, 'c' => 0]];
        yield 'specificity.c #20 uppercase type selector' => ['LI', ['a' => 0, 'b' => 0, 'c' => 1]];
        yield 'specificity.c #21 descendant type selectors' => ['UL LI', ['a' => 0, 'b' => 0, 'c' => 2]];
        yield 'specificity.c #22 adjacent type selectors' => ['UL OL+LI', ['a' => 0, 'b' => 0, 'c' => 3]];
        yield 'specificity.c #23 universal with attribute' => ['H1 + *[REL=up]', ['a' => 0, 'b' => 1, 'c' => 1]];
        yield 'specificity.c #24 class on descendant type selector' => ['UL OL LI.red', ['a' => 0, 'b' => 1, 'c' => 3]];
        yield 'specificity.c #25 two classes on type selector' => ['LI.red.level', ['a' => 0, 'b' => 2, 'c' => 1]];
        yield 'specificity.c #26 hash id' => ['#x34y', ['a' => 1, 'b' => 0, 'c' => 0]];
        yield 'specificity.c #27 not type selector' => ['#s12:not(FOO)', ['a' => 1, 'b' => 0, 'c' => 1]];
        yield 'specificity.c #28 is id wins class with leading class' => ['.foo :is(.bar, #baz)', ['a' => 1, 'b' => 1, 'c' => 0]];
    }

    /**
     * @param array{a: int, b: int, c: int} $expected
     */
    #[DataProvider('upstreamSpecificityProvider')]
    public function testUpstreamSpecificityFixtures(string $selector, array $expected): void
    {
        self::assertSame($expected, (new SpecificityCalculator())->calculate($selector));
    }
}
