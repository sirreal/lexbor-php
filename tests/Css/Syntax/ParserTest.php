<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Syntax;

use Lexbor\Css\Syntax\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{type: string, prelude: list<array{type: string, value: string}>, block: list<array<string, mixed>>}>}>
     */
    public static function upstreamQualifiedPreludeProvider(): iterable
    {
        yield 'qualified.ton #1 selector prelude' => ['#id .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #2 leading whitespace skipped' => ['    #id .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #3 trailing whitespace retained' => ['#id .class    ', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                    ['type' => 'whitespace', 'value' => '    '],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #4 mismatched square block keeps curly tokens in prelude' => ['#id [{]{ .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'left-square-bracket', 'value' => '['],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'right-square-bracket', 'value' => ']'],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #5 mismatched parenthesis block keeps curly tokens in prelude' => ['#id ({){ .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'left-parenthesis', 'value' => '('],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'right-parenthesis', 'value' => ')'],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #6 mismatched function block keeps curly token in prelude' => ['#id last({) .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'function', 'value' => 'last('],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'right-parenthesis', 'value' => ')'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #7 empty block after spaced prelude' => ['#id {}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #8 empty block after compact prelude' => ['#id{}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                ],
                'block' => [],
            ],
        ]];
    }

    /**
     * @param list<array{type: string, prelude: list<array{type: string, value: string}>, block: list<array<string, mixed>>}> $expected
     */
    #[DataProvider('upstreamQualifiedPreludeProvider')]
    public function testUpstreamQualifiedPreludeFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseListRules($css));
    }
}
