<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Syntax;

use Lexbor\Css\Syntax\AnPlusBParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnPlusBParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamAnPlusBProvider(): iterable
    {
        yield 'an_plus_b.c #1 odd keyword' => ['odd', 'odd', []];
        yield 'an_plus_b.c #2 odd expression' => ['2n+1', 'odd', []];
        yield 'an_plus_b.c #3 two n minus one' => ['2n-1', '2n-1', []];
        yield 'an_plus_b.c #4 even expression' => ['2n', 'even', []];
        yield 'an_plus_b.c #5 even plus zero' => ['2n+0', 'even', []];
        yield 'an_plus_b.c #6 even minus zero' => ['2n-0', 'even', []];
        yield 'an_plus_b.c #7 three n' => ['3n', '3n', []];
        yield 'an_plus_b.c #8 negative two n' => ['-2n', '-2n', []];
        yield 'an_plus_b.c #9 positive zero n plus zero' => ['+0n+0', '0n', []];
        yield 'an_plus_b.c #10 zero n plus zero' => ['0n+0', '0n', []];
        yield 'an_plus_b.c #11 negative zero n plus zero' => ['-0n+0', '0n', []];
        yield 'an_plus_b.c #12 negative zero n minus zero' => ['-0n-0', '0n', []];
        yield 'an_plus_b.c #13 positive zero n minus zero' => ['+0n-0', '0n', []];
        yield 'an_plus_b.c #14 negative n' => ['-n', '-n', []];
        yield 'an_plus_b.c #15 positive n' => ['+n', '+n', []];
        yield 'an_plus_b.c #16 one n canonicalizes positive n' => ['1n', '+n', []];
        yield 'an_plus_b.c #17 negative one n canonicalizes negative n' => ['-1n', '-n', []];
        yield 'an_plus_b.c #18 one n minus one' => ['1n-1', '+n-1', []];
        yield 'an_plus_b.c #19 one n minus spaced one' => ['1n- 1', '+n-1', []];
        yield 'an_plus_b.c #20 one n space negative one' => ['1n -1', '+n-1', []];
        yield 'an_plus_b.c #21 one n spaced minus one' => ['1n - 1', '+n-1', []];
        yield 'an_plus_b.c #22 one n plus one' => ['1n+1', '+n+1', []];
        yield 'an_plus_b.c #23 one n plus spaced one' => ['1n+ 1', '+n+1', []];
        yield 'an_plus_b.c #24 one n spaced plus one' => ['1n + 1', '+n+1', []];
        yield 'an_plus_b.c #25 minus plus is invalid' => ['1n-+1', '', ['Syntax error. An+B. Unexpected token: 1']];
        yield 'an_plus_b.c #26 double minus is invalid' => ['1n--1', '', ['Syntax error. An+B. Unexpected token: 1n--1']];
        yield 'an_plus_b.c #27 plus negative is invalid' => ['1n+-1', '', ['Syntax error. An+B. Unexpected token: -1']];
        yield 'an_plus_b.c #28 double plus is invalid' => ['1n++1', '', ['Syntax error. An+B. Unexpected token: 1']];
        yield 'an_plus_b.c #29 negative one n double plus is invalid' => ['-1n++1', '', ['Syntax error. An+B. Unexpected token: 1']];
        yield 'an_plus_b.c #30 fractional coefficient is invalid' => ['1.1n+1', '', ['Syntax error. An+B. Unexpected token: 1.1n']];
        yield 'an_plus_b.c #31 fractional offset is invalid' => ['1n+1.2', '', ['Syntax error. An+B. Unexpected token: 1.2']];
        yield 'an_plus_b.c #32 percentage offset is invalid' => ['1n-1%', '', ['Syntax error. An+B. Unexpected token: %']];
        yield 'an_plus_b.c #33 space after leading plus is invalid' => ['+ n+1', '', ['Syntax error. An+B. Unexpected token:  ']];
        yield 'an_plus_b.c #34 space after leading minus is invalid' => ['- n+1', '', ['Syntax error. An+B. Unexpected token: -']];
        yield 'an_plus_b.c #35 large values' => ['100000n+100000', '100000n+100000', []];
    }

    /**
     * @param list<string> $errors
     */
    #[DataProvider('upstreamAnPlusBProvider')]
    public function testUpstreamAnPlusBFixtures(string $source, string $value, array $errors): void
    {
        self::assertSame(['value' => $value, 'errors' => $errors], (new AnPlusBParser())->parse($source));
    }
}
